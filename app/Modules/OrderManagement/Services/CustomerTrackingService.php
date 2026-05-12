<?php

/**
 * @file: CustomerTrackingService.php
 * @description: Business logic for the Customer Tracking Portal (api/v1/customer).
 *
 *               SCHEMA TRUTH (SQL Server — verified columns only)
 *               ──────────────────────────────────────────────────
 *               order            → OrderID, DriverID(FK), Status, ETA, PromisedWindow,
 *                                  Price, Area, Created_at, UpdatedAt, Payment_method,
 *                                  Latitude, Longitude, Delivery_preference, DeliveredAt,
 *                                  digital_signature, Weight, Volume, Perishable
 *
 *               drivers          → driver_id, license_no, license_type,
 *                                  vehicle_id, status, score, created_at
 *
 *               tracking_tokens  → id, token, order_id, expires_at, created_at
 *
 *               order_items      → id, order_id, product_name, quantity,
 *                                  unit_price, image_url
 *
 *               route_stops      → stop_id, route_id, stop_no, order_id, eta,
 *                                  actual_arrival_time, latitude, longitude
 *
 *               BANNED TABLES (do not exist in DB):
 *               driver_locations, order_status_logs, delivery_attempts,
 *               delivery_proofs, delivery_photos, order_feedback, customers
 *
 * @module: OrderManagement
 */

namespace App\Modules\OrderManagement\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerTrackingService
{
    // Delivered orders are "expired" after 48 h for read purposes
    private const EXPIRY_HOURS = 48;

    // =========================================================================
    // 1. GET /tracking/{tracking_code}
    // =========================================================================

    /**
     * Resolve the tracking code, load all order data, and return an enriched
     * stdClass ready for CustomerOrderResource.
     *
     * @return array{ success: bool, message: string, data?: object, errors?: array, status_code?: int }
     */
    public function getOrderByTrackingCode(string $trackingCode): array
    {
        try {
            // 1a. Resolve token record
            $tokenRecord = DB::table('tracking_tokens')
                ->where('token', $trackingCode)
                ->first();

            if (! $tokenRecord) {
                return $this->failure('Tracking link not found.', ['token' => ['This tracking code is invalid.']], 404);
            }

            // 1b. Expiry check (token-level)
            if (Carbon::parse($tokenRecord->expires_at)->isPast()) {
                return $this->failure(
                    'This tracking link has expired.',
                    ['token' => ['Your tracking link expired on ' . Carbon::parse($tokenRecord->expires_at)->toFormattedDateString() . '.']],
                    410
                );
            }

            // 1c. Fetch the order row
            $order = DB::table('order')
                ->where('OrderID', $tokenRecord->order_id)
                ->first();

            if (! $order) {
                return $this->failure('Associated order not found.', ['order' => ['Order no longer exists.']], 404);
            }

            // 1d. 48-hour expiry check for delivered orders
            $isExpired = false;
            if (strtolower($order->Status ?? '') === 'delivered' && ! empty($order->DeliveredAt)) {
                $isExpired = Carbon::parse($order->DeliveredAt)->addHours(self::EXPIRY_HOURS)->isPast();
            }

            // 1e. Enrich order object — add tracking_code, is_expired, driver, items
            $order->tracking_code   = $trackingCode;
            $order->is_expired      = $isExpired;
            $order->feedback_submitted = false; // order_feedback table does not exist

            // Driver (join not available via Eloquent here — raw query)
            $driverFk = $order->{'DriverID(FK)'} ?? null;
            $order->driver = $driverFk
                ? DB::table('drivers')->where('driver_id', $driverFk)->first()
                : null;

            // Order items
            $order->items = DB::table('order_items')
                ->where('order_id', $order->OrderID)
                ->get()
                ->map(fn ($item) => [
                    'name'     => $item->product_name ?? null,
                    'quantity' => (int)   ($item->quantity   ?? 1),
                    'price'    => (float) ($item->unit_price ?? 0),
                    'imageUrl' => $item->image_url ?? null,
                ])
                ->toArray();

            return ['success' => true, 'message' => 'Order details retrieved successfully.', 'data' => $order];

        } catch (Exception $e) {
            Log::error('CustomerTrackingService@getOrderByTrackingCode', [
                'tracking_code' => $trackingCode,
                'error'         => $e->getMessage(),
            ]);
            return $this->failure('Unable to load order details.', ['server' => [$e->getMessage()]], 500);
        }
    }

    // =========================================================================
    // 2. POST /tracking/{tracking_code}/preferences
    // =========================================================================

    /**
     * Persist delivery preferences on the order row (Delivery_preference column).
     *
     * The DB column is a free-text string, so we JSON-encode the flags + notes.
     * If the schema later gains dedicated boolean columns, swap the update logic.
     *
     * @return array{ success: bool, message: string, data: array, errors?: array, status_code?: int }
     */
    public function savePreferences(string $trackingCode, array $payload): array
    {
        try {
            [$tokenRecord, $order, $errorResponse] = $this->resolveActiveOrder($trackingCode);
            if ($errorResponse) {
                return $errorResponse;
            }

            // Guard: cannot change preferences after the driver has almost arrived
            if (in_array(strtolower($order->Status ?? ''), ['almost_here', 'delivered', 'unsuccessful', 'failed'])) {
                return $this->failure('Delivery preferences cannot be changed at this stage.', [], 422);
            }

            $preferencesJson = json_encode([
                'ring_doorbell' => (bool) ($payload['ring_doorbell'] ?? false),
                'leave_at_door' => (bool) ($payload['leave_at_door'] ?? false),
                'notes'         => $payload['notes'] ?? null,
            ], JSON_UNESCAPED_UNICODE);

            DB::table('order')
                ->where('OrderID', $order->OrderID)
                ->update(['Delivery_preference' => $preferencesJson]);

            // Bust tracking cache if it exists
            Cache::forget("tracking_data_{$order->OrderID}");

            return [
                'success' => true,
                'message' => 'Delivery preferences saved successfully.',
                'data'    => [
                    'ringDoorbell' => (bool) ($payload['ring_doorbell'] ?? false),
                    'leaveAtDoor'  => (bool) ($payload['leave_at_door'] ?? false),
                    'notes'        => $payload['notes'] ?? null,
                ],
            ];

        } catch (Exception $e) {
            Log::error('CustomerTrackingService@savePreferences', [
                'tracking_code' => $trackingCode,
                'error'         => $e->getMessage(),
            ]);
            return $this->failure('Unable to save preferences.', ['server' => [$e->getMessage()]], 500);
        }
    }

    // =========================================================================
    // 3. POST /tracking/{tracking_code}/feedback
    // =========================================================================

    /**
     * Record post-delivery feedback.
     *
     * NOTE: order_feedback table does NOT exist in the current schema.
     * We write the rating back to the order row using the `digital_signature`
     * field as a temporary carrier (serialised JSON) until a proper table is
     * added.  This is clearly documented so the team can migrate later.
     *
     * @return array{ success: bool, message: string, data: array, errors?: array, status_code?: int }
     */
    public function saveFeedback(string $trackingCode, array $payload): array
    {
        try {
            [$tokenRecord, $order, $errorResponse] = $this->resolveActiveOrder($trackingCode);
            if ($errorResponse) {
                return $errorResponse;
            }

            if (strtolower($order->Status ?? '') !== 'delivered') {
                return $this->failure('Feedback can only be submitted after delivery.', [], 422);
            }

            // Store as JSON in the Delivery_preference column (no order_feedback table).
            // Prefix distinguishes this from a normal preference payload.
            $feedbackJson = json_encode([
                '_type'     => 'customer_feedback',
                'rating'    => (int)    ($payload['rating']    ?? 0),
                'condition' => (string) ($payload['condition'] ?? ''),
                'comments'  => (string) ($payload['comments']  ?? ''),
                'submitted_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE);

            DB::table('order')
                ->where('OrderID', $order->OrderID)
                ->update(['Delivery_preference' => $feedbackJson]);

            return [
                'success' => true,
                'message' => 'Thank you for your feedback!',
                'data'    => [
                    'submittedAt' => now()->toIso8601String(),
                    'rating'      => (int) ($payload['rating'] ?? 0),
                    'condition'   => $payload['condition'] ?? null,
                ],
            ];

        } catch (Exception $e) {
            Log::error('CustomerTrackingService@saveFeedback', [
                'tracking_code' => $trackingCode,
                'error'         => $e->getMessage(),
            ]);
            return $this->failure('Unable to submit feedback.', ['server' => [$e->getMessage()]], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve an active (non-expired) token + order pair.
     *
     * @return array{ 0: object|null, 1: object|null, 2: array|null }
     *   [tokenRecord, order, errorResponseOrNull]
     */
    private function resolveActiveOrder(string $trackingCode): array
    {
        $tokenRecord = DB::table('tracking_tokens')
            ->where('token', $trackingCode)
            ->where('expires_at', '>', now())
            ->first();

        if (! $tokenRecord) {
            return [null, null, $this->failure(
                'Invalid or expired tracking link.',
                ['token' => ['This link is invalid or has expired.']],
                404
            )];
        }

        $order = DB::table('order')
            ->where('OrderID', $tokenRecord->order_id)
            ->first();

        if (! $order) {
            return [null, null, $this->failure('Order not found.', [], 404)];
        }

        return [$tokenRecord, $order, null];
    }

    private function failure(string $message, array $errors = [], int $statusCode = 422): array
    {
        return [
            'success'     => false,
            'message'     => $message,
            'errors'      => $errors,
            'data'        => [],
            'status_code' => $statusCode,
        ];
    }
}
