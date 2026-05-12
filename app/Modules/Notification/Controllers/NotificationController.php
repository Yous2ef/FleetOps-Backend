<?php

/**
 * @file: NotificationController.php
 * @description: متحكم الإشعارات - عرض وإدارة وتفضيلات المستخدم
 * @module: Notification
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Repositories\NotificationRepository;
use App\Modules\Notification\Repositories\NotificationPreferenceRepository;
use App\Modules\Notification\Requests\NotificationPreferenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected NotificationRepository $notificationRepository;
    protected NotificationPreferenceRepository $preferenceRepository;

    public function __construct(
        NotificationRepository $notificationRepository,
        NotificationPreferenceRepository $preferenceRepository
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->preferenceRepository   = $preferenceRepository;
    }

    /**
     * Get paginated notifications for the authenticated user.
     * GET /api/v1/notifications
     *
     * @queryParam per_page int Number of items per page (default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId  = $request->user()->user_id;
            $perPage = (int) $request->input('per_page', 20);

            $notifications = $this->notificationRepository->getForUser($userId, $perPage);

            return response()->json([
                'success' => true,
                'data'    => $notifications,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationController::index error', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications.',
            ], 500);
        }
    }

    /**
     * Get a single notification by ID (must belong to the authenticated user).
     * GET /api/v1/notifications/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $userId       = $request->user()->user_id;
            $notification = $this->notificationRepository->findById($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            // Ownership check — prevent accessing other users' notifications
            if ($notification->user_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $notification,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationController::show error', [
                'message'         => $e->getMessage(),
                'notification_id' => $id,
                'user_id'         => $request->user()?->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification.',
            ], 500);
        }
    }

    /**
     * Get notification preferences for the authenticated user.
     * Returns system defaults if no preferences have been saved yet.
     * GET /api/v1/notifications/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $pref   = $this->preferenceRepository->getForUser($userId);

            // Return system defaults if the user has never set preferences
            if (!$pref) {
                $pref = new NotificationPreference([
                    'user_id'            => $userId,
                    'push_enabled'       => true,
                    'sms_enabled'        => false,
                    'email_enabled'      => true,
                    'quiet_hours_start'  => null,
                    'quiet_hours_end'    => null,
                    'preferred_language' => 'en',
                    'fcm_token'          => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $pref,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationController::getPreferences error', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve preferences.',
            ], 500);
        }
    }

    /**
     * Create or update notification preferences for the authenticated user.
     * PUT /api/v1/notifications/preferences
     *
     * @bodyParam push_enabled       boolean
     * @bodyParam sms_enabled        boolean
     * @bodyParam email_enabled      boolean
     * @bodyParam quiet_hours_start  string  HH:MM format
     * @bodyParam quiet_hours_end    string  HH:MM format (must be after quiet_hours_start)
     * @bodyParam preferred_language string  'ar' or 'en'
     * @bodyParam fcm_token          string  Firebase Cloud Messaging device token
     */
    public function updatePreferences(NotificationPreferenceRequest $request): JsonResponse
    {
        try {
            $userId     = $request->user()->user_id;
            $validated  = $request->validated();

            $pref = $this->preferenceRepository->upsertForUser($userId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully.',
                'data'    => $pref,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationController::updatePreferences error', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences.',
            ], 500);
        }
    }

    /**
     * Update only the FCM token for the authenticated user's push notifications.
     * POST /api/v1/notifications/fcm-token
     *
     * @bodyParam fcm_token string required Firebase Cloud Messaging device token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fcm_token' => 'required|string|max:512',
            ]);

            $userId = $request->user()->user_id;

            $this->preferenceRepository->upsertForUser($userId, [
                'fcm_token' => $request->input('fcm_token'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationController::updateFcmToken error', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token.',
            ], 500);
        }
    }
}
