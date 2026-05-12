<?php

/**
 * @file: NotificationService.php
 * @description: خدمة توجيه الإشعارات متعددة القنوات (Push/SMS/Email) - Notification Service
 * @module: Notification
 * @author: Team Leader (Khalid)
 *
 * Channel Fallback: Push → [fail] → SMS → [fail] → Email
 */

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Repositories\NotificationRepository;
use App\Modules\Notification\Repositories\NotificationPreferenceRepository;
use Exception;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected NotificationRepository $notificationRepository;
    protected NotificationPreferenceRepository $preferenceRepository;
    protected TemplateService $templateService;

    public function __construct(
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository,
        TemplateService $templateService
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository   = $preferenceRepository;
        $this->templateService        = $templateService;
    }

    /**
     * جلب إشعارات المستخدم
     * @param int $userId
     * @param int $perPage
     */
    public function getUserNotifications(int $userId, int $perPage = 15)
    {
        return $this->notificationRepository->getForUser($userId, $perPage);
    }

    /**
     * Send a notification via the appropriate channel with automatic fallback (NF-01).
     *
     * Flow:
     *  1. Deduplication check — skip if same event already sent within 5 min
     *  2. Quiet hours check — skip unless payload flags it as urgent
     *  3. Resolve enabled channels from user preferences (or use provided $channels)
     *  4. Create notification record with status 'pending'
     *  5. Try channels in order: push → sms → email
     *  6. Update record to sent / delivered / failed accordingly
     *
     * @param int    $userId     Target user ID
     * @param string $eventType  Event key (e.g. 'proximity_alert', 'delay_alert')
     * @param array  $payload    Notification content ['title', 'body', 'order_id', ...]
     * @param array  $channels   Override channel order; empty = use user preferences
     * @return array             ['notification_id', 'channel_used', 'status']
     * @throws Exception
     */
    public function send(int $userId, string $eventType, array $payload, array $channels = []): array
    {
        // ── 1. Deduplication guard (NF-09) ────────────────────────────────────
        $orderId  = $payload['order_id'] ?? 'general';
        $dedupKey = "{$userId}_{$eventType}_{$orderId}";

        if ($this->notificationRepository->findByDedupKey($dedupKey)) {
            Log::info("NotificationService: duplicate skipped", ['dedup_key' => $dedupKey]);
            return ['notification_id' => null, 'channel_used' => null, 'status' => 'duplicate'];
        }

        // ── 2. Quiet hours check (NF-10) ──────────────────────────────────────
        $isUrgent = $payload['urgent'] ?? false;
        if (!$isUrgent && $this->preferenceRepository->isInQuietHours($userId)) {
            Log::info("NotificationService: suppressed — quiet hours", ['user_id' => $userId]);
            return ['notification_id' => null, 'channel_used' => null, 'status' => 'quiet_hours'];
        }

        // ── 3. Resolve channels from preferences if not explicitly provided ───
        if (empty($channels)) {
            $channels = $this->resolveChannelsFromPreferences($userId);
        }

        // ── 4. Create notification record (pending) ───────────────────────────
        $notification = $this->notificationRepository->create([
            'user_id'     => $userId,
            'channel'     => $channels[0] ?? 'push',
            'event_type'  => $eventType,
            'payload'     => $payload,
            'status'      => 'pending',
            'dedup_key'   => $dedupKey,
            'retry_count' => 0,
        ]);

        $notificationId = $notification->notification_id;

        // ── 5. Try channels in order with fallback (NF-03/04/05) ─────────────
        foreach ($channels as $channel) {
            try {
                $this->dispatchChannel($channel, $userId, $notification, $payload);

                // Success — mark as sent then delivered
                $this->notificationRepository->markAsSent($notificationId);
                $this->notificationRepository->markAsDelivered($notificationId);

                Log::info("NotificationService: sent via {$channel}", [
                    'notification_id' => $notificationId,
                    'user_id'         => $userId,
                    'event_type'      => $eventType,
                ]);

                return [
                    'notification_id' => $notificationId,
                    'channel_used'    => $channel,
                    'status'          => 'delivered',
                ];
            } catch (Exception $e) {
                Log::warning("NotificationService: channel '{$channel}' failed — trying next", [
                    'notification_id' => $notificationId,
                    'error'           => $e->getMessage(),
                ]);
                // Continue to next channel
            }
        }

        // ── 6. All channels failed — mark as failed (dead-letter) ────────────
        $retryCount = ($notification->retry_count ?? 0) + 1;
        $this->notificationRepository->markAsFailed(
            $notificationId,
            'All channels exhausted',
            $retryCount
        );

        Log::error("NotificationService: all channels failed", [
            'notification_id' => $notificationId,
            'user_id'         => $userId,
        ]);

        return [
            'notification_id' => $notificationId,
            'channel_used'    => null,
            'status'          => 'failed',
        ];
    }

    /**
     * Send proximity alert to a customer when the driver is within 500m (NF-06 / fn20).
     *
     * @param int $orderId
     * @param int $customerId  User ID of the customer
     * @return array
     */
    public function sendProximityAlert(int $orderId, int $customerId): array
    {
        $language = $this->getUserLanguage($customerId);

        $body = $this->templateService->buildMessage('proximity_alert', $language);

        $payload = [
            'title'    => $language === 'ar' ? 'السائق قريب منك' : 'Driver Nearby',
            'body'     => $body,
            'order_id' => $orderId,
            'urgent'   => true, // proximity alerts bypass quiet hours
        ];

        return $this->send($customerId, 'proximity_alert', $payload);
    }

    /**
     * Send a delivery delay alert to a dispatcher when the ETA will be missed (NF-07 / fn10).
     *
     * @param int    $orderId
     * @param int    $dispatcherId  User ID of the dispatcher
     * @param string $newEta        Human-readable new ETA (e.g. "16:45")
     * @return array
     */
    public function sendDeliveryDelayAlert(int $orderId, int $dispatcherId, string $newEta = ''): array
    {
        $language = $this->getUserLanguage($dispatcherId);

        $body = $this->templateService->buildMessage('delay_alert', $language, [
            ':eta' => $newEta ?: now()->addHour()->format('H:i'),
        ]);

        $payload = [
            'title'    => $language === 'ar' ? 'تنبيه تأخر التسليم' : 'Delivery Delay Alert',
            'body'     => $body,
            'order_id' => $orderId,
        ];

        return $this->send($dispatcherId, 'delay_alert', $payload);
    }

    /**
     * Send a maintenance-related alert to a fleet manager (NF-08 / fn23/31/32).
     *
     * @param string $alertType  'odometer' | 'low_stock' | 'annual_inspection' | 'incident'
     * @param int    $managerId  User ID of the fleet manager
     * @param array  $data       Context data for the template variables
     * @return array
     */
    public function sendMaintenanceAlert(string $alertType, int $managerId, array $data): array
    {
        $language = $this->getUserLanguage($managerId);

        $eventTypeMap = [
            'odometer'          => 'maintenance_alert_odometer',
            'low_stock'         => 'low_stock_alert',
            'annual_inspection' => 'maintenance_alert_inspection',
            'incident'          => 'incident_alert',
        ];

        $eventType = $eventTypeMap[$alertType] ?? 'maintenance_alert';

        // Build template variables from $data keys (prefixed with colon)
        $variables = [];
        foreach ($data as $key => $value) {
            $variables[":{$key}"] = $value;
        }

        $body = $this->templateService->buildMessage($eventType, $language, $variables);

        $titleMap = [
            'odometer'          => ['ar' => 'تنبيه عداد الصيانة',    'en' => 'Odometer Service Alert'],
            'low_stock'         => ['ar' => 'تنبيه مخزون منخفض',      'en' => 'Low Stock Alert'],
            'annual_inspection' => ['ar' => 'تنبيه الفحص السنوي',     'en' => 'Annual Inspection Alert'],
            'incident'          => ['ar' => 'تنبيه حادث مركبة',       'en' => 'Vehicle Incident Alert'],
        ];

        $title = $titleMap[$alertType][$language]
            ?? ($language === 'ar' ? 'تنبيه صيانة' : 'Maintenance Alert');

        $payload = array_merge($data, [
            'title'      => $title,
            'body'       => $body,
            'alert_type' => $alertType,
        ]);

        return $this->send($managerId, $eventType, $payload);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Resolve the ordered list of channels to try based on user preferences.
     * Default order: push → email → sms
     *
     * @param int $userId
     * @return array  e.g. ['push', 'email']
     */
    private function resolveChannelsFromPreferences(int $userId): array
    {
        $pref = $this->preferenceRepository->getForUser($userId);

        if (!$pref) {
            return ['push', 'email']; // system defaults
        }

        $channels = [];

        if ($pref->push_enabled)  { $channels[] = 'push'; }
        if ($pref->sms_enabled)   { $channels[] = 'sms'; }
        if ($pref->email_enabled) { $channels[] = 'email'; }

        return empty($channels) ? ['push'] : $channels;
    }

    /**
     * Get the preferred language for a user from their notification preferences.
     * Falls back to 'en' if no preference is set.
     *
     * @param int $userId
     * @return string 'ar' | 'en'
     */
    private function getUserLanguage(int $userId): string
    {
        $pref = $this->preferenceRepository->getForUser($userId);
        return $pref?->preferred_language ?? 'en';
    }

    /**
     * Dispatch a notification through the given channel.
     * Each channel driver would integrate with FCM / Twilio / SMTP here.
     *
     * @param string $channel         'push' | 'sms' | 'email'
     * @param int    $userId
     * @param object $notification    The persisted Notification model
     * @param array  $payload
     * @throws Exception  If the channel fails to deliver
     */
    private function dispatchChannel(string $channel, int $userId, $notification, array $payload): void
    {
        switch ($channel) {
            case 'push':
                // TODO: dispatch PushNotificationJob via Firebase FCM
                // $pref = $this->preferenceRepository->getForUser($userId);
                // if (!$pref?->fcm_token) throw new Exception('No FCM token');
                // PushNotificationJob::dispatch($pref->fcm_token, $payload);
                break;

            case 'sms':
                // TODO: dispatch SmsNotificationJob via Twilio
                // SmsNotificationJob::dispatch($userId, $payload['body']);
                break;

            case 'email':
                // TODO: dispatch EmailNotificationJob via SMTP/SES
                // EmailNotificationJob::dispatch($userId, $payload);
                break;

            default:
                throw new Exception("Unknown notification channel: {$channel}");
        }
    }
}
