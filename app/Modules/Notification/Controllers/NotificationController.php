<?php

/**
 * @file: NotificationController.php
 * @description: متحكم الإشعارات - عرض وإدارة وتفضيلات المستخدم
 * @module: Notification
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * جلب إشعارات المستخدم الحالي
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Seed data if empty
            if (\App\Modules\Notification\Models\Notification::count() === 0) {
                $firstUser = \App\Modules\AuthIdentity\Models\User::first();
                $userId = $request->user() ? $request->user()->user_id : ($firstUser ? $firstUser->user_id : 1);
                
                $dummies = [
                    [
                        'user_id' => $userId,
                        'channel' => 'push',
                        'event_type' => 'status_update',
                        'payload' => json_encode([
                            'title' => 'Route Started', 
                            'body' => 'Your route R-001 has been started.',
                            'description' => 'The system has successfully dispatched vehicle V-101 for route R-001. All stops have been synchronized to the driver app.'
                        ]),
                        'status' => 'delivered',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'user_id' => $userId,
                        'channel' => 'push',
                        'event_type' => 'delay_alert',
                        'payload' => json_encode([
                            'title' => 'Traffic Delay', 
                            'body' => 'Expect a 15 min delay on Route R-002.',
                            'description' => 'Heavy traffic detected on the Ring Road near the 6th of October exit. Estimated time of arrival for Stop 3 has been adjusted.'
                        ]),
                        'status' => 'delivered',
                        'created_at' => now()->subMinutes(30),
                        'updated_at' => now()->subMinutes(30),
                    ],
                    [
                        'user_id' => $userId,
                        'channel' => 'push',
                        'event_type' => 'maintenance_alert',
                        'payload' => json_encode([
                            'title' => 'Maintenance Required', 
                            'body' => 'Vehicle V-101 needs oil change.',
                            'description' => 'Predictive maintenance alert: Vehicle V-101 has reached 10,000 km since its last oil change. Please schedule a visit to the workshop.'
                        ]),
                        'status' => 'pending',
                        'created_at' => now()->subHours(2),
                        'updated_at' => now()->subHours(2),
                    ],
                ];
                \App\Modules\Notification\Models\Notification::insert($dummies);
            }

            // Fetch notifications
            $perPage = (int) $request->input('per_page', 20);
            $firstUser = \App\Modules\AuthIdentity\Models\User::first();
            $userId = $request->user() ? $request->user()->user_id : ($firstUser ? $firstUser->user_id : 1);
            
            $notifications = \App\Modules\Notification\Models\Notification::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json(['success' => true, 'data' => $notifications]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('NotificationController::index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * عرض إشعار واحد
     * GET /api/v1/notifications/{id}
     */
    public function show(int $id): JsonResponse
    {
        // TODO: return single notification (must belong to auth user)
    }

    /**
     * تحديث تفضيلات الإشعارات (NF-02)
     * PUT /api/v1/notifications/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        // TODO: Update user notification preferences
        // 1. Validate: push_enabled, sms_enabled, email_enabled, quiet_hours_start, quiet_hours_end, fcm_token
        // 2. Upsert preferences for auth user
        // 3. Return updated preferences
    }

    /**
     * جلب تفضيلات الإشعارات
     * GET /api/v1/notifications/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        // TODO: return auth user notification preferences
    }

    /**
     * تحديث FCM Token (للـ Push Notifications)
     * POST /api/v1/notifications/fcm-token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        // TODO: Validate fcm_token and update in preferences
        // $request->validate(['fcm_token' => 'required|string'])
    }
}
