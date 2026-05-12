<?php

/**
 * @file: CustomerTrackingController.php
 * @description: HTTP entry-point for the Customer Tracking Portal.
 *
 *               All endpoints are PUBLIC (no auth:sanctum) — access control is
 *               enforced by the tracking token itself inside the service layer.
 *
 *               Standard response envelope:
 *               ────────────────────────────
 *               { "success": bool, "message": string, "data": object|array }
 *
 *               Error envelope:
 *               ────────────────
 *               { "success": false, "message": string, "data": [], "errors": {} }
 *
 * @module: OrderManagement
 * @prefix: api/v1/customer/tracking/{tracking_code}
 */

namespace App\Modules\OrderManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OrderManagement\Requests\TrackingFeedbackRequest;
use App\Modules\OrderManagement\Requests\TrackingPreferencesRequest;
use App\Modules\OrderManagement\Resources\CustomerOrderResource;
use App\Modules\OrderManagement\Services\CustomerTrackingService;
use Illuminate\Http\JsonResponse;

class CustomerTrackingController extends Controller
{
    public function __construct(
        private readonly CustomerTrackingService $trackingService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // GET api/v1/customer/tracking/{tracking_code}
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the full order details for the given tracking code.
     *
     * Includes:
     *   - Current status (mapped to frontend screen identifiers)
     *   - Expected arrival time & delivery address
     *   - Driver details (from drivers table — name/phone absent from schema)
     *   - Tracking timeline (synthesised from current Status)
     *   - Order contents & pricing
     *   - isExpired flag (delivered > 48 h ago)
     */
    public function show(string $trackingCode): JsonResponse
    {
        $result = $this->trackingService->getOrderByTrackingCode($trackingCode);

        if (! ($result['success'] ?? false)) {
            return $this->errorResponse($result);
        }

        // Pass the enriched stdClass through the Resource to get clean camelCase JSON.
        $resource = new CustomerOrderResource($result['data']);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $resource->toArray(request()),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST api/v1/customer/tracking/{tracking_code}/preferences
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Persist customer delivery preferences for the order.
     *
     * Body (JSON):
     *   ring_doorbell (bool) — optional
     *   leave_at_door (bool) — optional
     *   notes         (str)  — optional, max 500 chars
     */
    public function storePreferences(
        TrackingPreferencesRequest $request,
        string $trackingCode
    ): JsonResponse {
        $result = $this->trackingService->savePreferences(
            $trackingCode,
            $request->validated()
        );

        if (! ($result['success'] ?? false)) {
            return $this->errorResponse($result);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST api/v1/customer/tracking/{tracking_code}/feedback
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record post-delivery feedback from the customer.
     *
     * Body (JSON):
     *   rating    (int 1–5)                    — required
     *   condition (Excellent|Good|Damaged)      — optional
     *   comments  (str, max 1000)               — optional
     */
    public function storeFeedback(
        TrackingFeedbackRequest $request,
        string $trackingCode
    ): JsonResponse {
        $result = $this->trackingService->saveFeedback(
            $trackingCode,
            $request->validated()
        );

        if (! ($result['success'] ?? false)) {
            return $this->errorResponse($result);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ], 201);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function errorResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'An error occurred.',
            'data'    => [],
            'errors'  => $result['errors']  ?? [],
        ], $result['status_code'] ?? 422);
    }
}
