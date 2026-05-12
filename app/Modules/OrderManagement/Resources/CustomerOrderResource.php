<?php

/**
 * @file: CustomerOrderResource.php
 * @description: API Resource that formats the raw SQL Server order data into a
 *               clean, camelCase JSON envelope consumed by the React Customer
 *               Tracking Portal.
 *
 *               DESIGN RULES
 *               ─────────────
 *               • All keys are camelCase so React destructuring works without mapping.
 *               • Every nullable field defaults to null — never omitted — so the
 *                 frontend never throws "cannot read property of undefined".
 *               • Booleans are always real booleans, never 0/1 strings.
 *               • Monetary amounts are floats (currency kept in a sibling field).
 *               • Timestamps are ISO-8601 strings or null.
 *               • The resource wraps a plain object (stdClass from DB::table) that
 *                 has been enriched by CustomerTrackingService::buildOrderPayload().
 *
 * @module: OrderManagement
 */

namespace App\Modules\OrderManagement\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    // -------------------------------------------------------------------------
    // Disable the default "data" wrapper so our envelope key stays clean
    // -------------------------------------------------------------------------
    public static $wrap = null;

    /**
     * Transform the enriched order payload into the React-expected structure.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $o = $this->resource; // stdClass enriched by CustomerTrackingService

        return [
            // ── Identity ──────────────────────────────────────────────────
            'orderId'        => $this->val($o, 'OrderID'),
            // The tracking_token is the UUID hash string (NOT the OrderID).
            // Resolved from: tracking_code (injected by service) → LiveTrackingLink (DB column).
            'tracking_token' => $this->resolveToken($o),
            'trackingCode'   => $this->val($o, 'tracking_code'),
            'status'         => $this->mapStatus($this->val($o, 'Status')),
            'isExpired'      => (bool) ($o->is_expired ?? false),

            // Exact format required by the Customer Portal frontend.
            // Uses the UUID tracking_token, never the integer OrderID.
            'tracking_url'   => 'http://127.0.0.1:3002/track?token=' . $this->resolveToken($o),

            // ── Delivery Address ──────────────────────────────────────────
            'deliveryAddress' => [
                'line1'     => $this->val($o, 'Area'),
                'city'      => $this->val($o, 'Area'),   // no separate city column
                'latitude'  => $this->toFloat($o, 'Latitude'),
                'longitude' => $this->toFloat($o, 'Longitude'),
            ],

            // ── ETA / Time Window ─────────────────────────────────────────
            'eta' => [
                'windowStart'  => $this->toIso($o, 'PromisedWindow'),
                'windowEnd'    => null,                              // not in schema
                'etaMinutes'   => $this->toInt($o, 'ETA'),
                'displayText'  => $this->formatEtaDisplay($o),
            ],

            // ── Driver ────────────────────────────────────────────────────
            // drivers table: driver_id, license_no, license_type, vehicle_id,
            //                status, score   (name/phone/photo NOT in schema)
            'driver' => $this->buildDriver($o),

            // ── Tracking Timeline ─────────────────────────────────────────
            // order_status_logs does not exist; timeline is synthesised from
            // the current Status so the UI always has at least one entry.
            'timeline' => $this->buildTimeline($o),

            // ── Order Contents ────────────────────────────────────────────
            'items'   => $o->items ?? [],    // array pre-mapped by service
            'pricing' => [
                'subtotal'       => $this->toFloat($o, 'Price'),
                'deliveryFee'    => null,    // no separate column
                'tax'            => null,
                'total'          => $this->toFloat($o, 'Price'),
                'currency'       => 'EGP',
                'paymentMethod'  => $this->val($o, 'Payment_method') ?? 'cash',
            ],

            // ── Delivery Preferences ──────────────────────────────────────
            'deliveryPreferences' => [
                'ringDoorbell' => (bool) ($o->ring_doorbell ?? false),
                'leaveAtDoor'  => (bool) ($o->leave_at_door ?? false),
                'notes'        => $this->val($o, 'Delivery_preference'),
            ],

            // ── Delivery Outcome ──────────────────────────────────────────
            'deliveredAt'       => $this->toIso($o, 'DeliveredAt'),
            'feedbackSubmitted' => (bool) ($o->feedback_submitted ?? false),
            'proofSignatureUrl' => $this->val($o, 'digital_signature'),

            // ── Timestamps ────────────────────────────────────────────────
            'createdAt'  => $this->toIso($o, 'Created_at'),
            'updatedAt'  => $this->toIso($o, 'UpdatedAt'),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the UUID tracking token from the enriched order object.
     *
     * Priority:
     *   1. $o->tracking_code  — injected at runtime by CustomerTrackingService
     *      (the token string that was looked up from tracking_tokens table).
     *   2. $o->LiveTrackingLink — the DB column where the token is persisted;
     *      available when the resource is used outside the tracking service
     *      (e.g. in an admin order list).
     */
    private function resolveToken(object $o): ?string
    {
        return $this->val($o, 'tracking_code')
            ?? $this->val($o, 'LiveTrackingLink')
            ?? null;
    }

    /**
     * Build the full customer-facing tracking URL for a given token.
     *
     * Strictly uses env('CUSTOMER_PORTAL_URL') so the value is always read
     * from the .env file without any config-cache indirection.
     *
     * Example: "http://127.0.0.1:3002/track?token=f47ac10b-58cc..."
     *
     * Returns null when no tracking token is available (legacy orders).
     */
    private function buildTrackingUrl(?string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        $base = rtrim((string) env('CUSTOMER_PORTAL_URL', 'http://127.0.0.1:3002'), '/');
        return $base . '/track?token=' . $token;
    }

    /** Safely read a property (supports bracket keys like "DriverID(FK)"). */
    private function val(object $o, string $key): mixed
    {
        return $o->{$key} ?? null;
    }

    private function toFloat(object $o, string $key): ?float
    {
        $v = $this->val($o, $key);
        return $v !== null ? (float) $v : null;
    }

    private function toInt(object $o, string $key): ?int
    {
        $v = $this->val($o, $key);
        return $v !== null ? (int) $v : null;
    }

    private function toIso(object $o, string $key): ?string
    {
        $v = $this->val($o, $key);
        if ($v === null) {
            return null;
        }
        try {
            return Carbon::parse((string) $v)->toIso8601String();
        } catch (\Throwable) {
            return (string) $v;
        }
    }

    /**
     * Map the raw SQL Server Status value to the six frontend screen identifiers.
     *
     * Frontend screens: Confirmed | Dispatched | InTransit | Arriving | Delivered | AttemptFailed
     */
    private function mapStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'confirmed'             => 'Confirmed',
            'assigned'              => 'Dispatched',
            'en_route', 'in_route' => 'InTransit',
            'almost_here'          => 'Arriving',
            'delivered'            => 'Delivered',
            'unsuccessful', 'failed' => 'AttemptFailed',
            default                => 'Confirmed',
        };
    }

    /**
     * Build the driver sub-object.
     * Only columns that EXIST in the DB are populated; missing ones → null.
     */
    private function buildDriver(object $o): ?array
    {
        $driver = $o->driver ?? null;  // pre-fetched stdClass or null

        if ($driver === null) {
            return null;
        }

        return [
            'driverId'     => $driver->driver_id ?? null,
            'name'         => null,       // full_name absent from drivers table
            'photoUrl'     => null,       // profile_photo_url absent
            'phone'        => null,       // phone absent
            'vehicleType'  => null,       // vehicle_type absent (requires join to vehicles)
            'vehiclePlate' => null,       // vehicle_plate absent
            'rating'       => isset($driver->score) ? (float) $driver->score : null,
            'licenseType'  => $driver->license_type ?? null,
        ];
    }

    /**
     * Synthesise a timeline array from the current Status.
     *
     * order_status_logs table does NOT exist.  We build a deterministic
     * ordered list of completed milestones so the React timeline component
     * always receives the shape it expects.
     *
     * Shape per entry: { status: string, label: string, completedAt: string|null, done: bool }
     */
    private function buildTimeline(object $o): array
    {
        $rawStatus  = strtolower((string) ($o->Status ?? ''));
        $createdAt  = $this->toIso($o, 'Created_at');
        $deliveredAt = $this->toIso($o, 'DeliveredAt');

        // Ordered milestone stages
        $stages = [
            'Confirmed'   => ['label' => 'Order Confirmed',  'rawStatuses' => ['confirmed']],
            'Dispatched'  => ['label' => 'Driver Dispatched','rawStatuses' => ['assigned']],
            'InTransit'   => ['label' => 'In Transit',       'rawStatuses' => ['en_route', 'in_route']],
            'Arriving'    => ['label' => 'Driver Arriving',  'rawStatuses' => ['almost_here']],
            'Delivered'   => ['label' => 'Delivered',        'rawStatuses' => ['delivered']],
            'AttemptFailed' => ['label' => 'Attempt Failed', 'rawStatuses' => ['unsuccessful', 'failed']],
        ];

        $currentMapped = $this->mapStatus($rawStatus);

        // Determine which stages are "done" (anything at or before current stage).
        $stageKeys  = array_keys($stages);
        $currentIdx = array_search($currentMapped, $stageKeys, true);
        // AttemptFailed is a terminal branch — it doesn't sit after Delivered.
        if ($currentMapped === 'AttemptFailed') {
            $currentIdx = array_search('InTransit', $stageKeys, true);
        }

        $timeline = [];
        foreach ($stageKeys as $idx => $key) {
            if ($key === 'AttemptFailed') {
                // Only include this stage if the order actually failed
                if ($currentMapped !== 'AttemptFailed') {
                    continue;
                }
            }

            $done = $idx <= $currentIdx;

            $completedAt = null;
            if ($done) {
                $completedAt = match (true) {
                    $key === 'Confirmed'  => $createdAt,
                    $key === 'Delivered' => $deliveredAt ?? null,
                    default              => null,  // intermediate timestamps unknown
                };
            }

            $timeline[] = [
                'status'      => $key,
                'label'       => $stages[$key]['label'],
                'completedAt' => $completedAt,
                'done'        => $done,
            ];
        }

        return $timeline;
    }

    /** Format PromisedWindow into a human-readable string for display. */
    private function formatEtaDisplay(object $o): string
    {
        $window = $this->val($o, 'PromisedWindow');
        if ($window === null) {
            return 'To be confirmed';
        }

        try {
            $carbon   = Carbon::parse((string) $window);
            $dayLabel = $carbon->isToday() ? 'Today' : $carbon->format('D, M j');
            return "{$dayLabel} at {$carbon->format('g:i A')}";
        } catch (\Throwable) {
            return (string) $window;
        }
    }
}
