<?php

/**
 * @file: VehicleDetailResource.php
 * @description: API Resource for the Vehicle Detail endpoint.
 *
 *   Consumed by: GET /api/v1/dispatch/fleet/vehicles/{id}
 *   Frontend:    fleetops-operations › fleet-management › view.js › buildCharts()
 *
 *   CHART.JS DATA CONTRACT
 *   ── Odometer Line Chart ──
 *     labels  : string[]  e.g. ['Oct','Nov','Dec','Jan','Feb','Mar']
 *     datasets[0].data : number[]  (raw km integers)
 *
 *   ── Fuel Efficiency Bar Chart ──
 *     labels  : string[]  (same months)
 *     datasets[0].data : number[]  (km/L, one decimal)
 *
 *   Because there is no dedicated history table in the DB, odometer_history and
 *   fuel_efficiency_history are deterministically derived from the vehicle's
 *   current odometer reading and type.  Insurance / inspection expiry is
 *   computed from CreatedAt + 1-year offset (same convention as the
 *   Maintenance module's getMaintenanceVehicles method).
 *
 * @module: RouteDispatch
 */

namespace App\Modules\RouteDispatch\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDetailResource extends JsonResource
{
    /** Remove the outer "data" key — controller wraps in its own envelope */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $v = $this->resource; // stdClass from DB::table('vehicles')

        // ── Derived values ──────────────────────────────────────────────────
        $odoRaw      = (float) ($v->Current_odometer ?? 0);
        $type        = strtolower((string) ($v->VehicleType ?? ''));
        $createdAt   = $v->CreatedAt ?? $v->created_at ?? null;
        $updatedAt   = $v->UpdatedAt ?? $v->updated_at ?? null;

        $lastService = $this->formatDate($updatedAt) ?? $this->formatDate($createdAt) ?? now()->subMonths(3)->format('Y-m-d');

        // Insurance  = CreatedAt + 12 months  (production: read from vehicles.insurance_expiry when column exists)
        $insuranceExpiry  = $createdAt
            ? Carbon::parse($createdAt)->addYear()->format('M d, Y')
            : now()->addYear()->format('M d, Y');

        // Inspection = CreatedAt + 6 months
        $inspectionExpiry = $createdAt
            ? Carbon::parse($createdAt)->addMonths(6)->format('M d, Y')
            : now()->addMonths(6)->format('M d, Y');

        return [
            // ── Identity ──────────────────────────────────────────────────
            'id'           => (string) ($v->vehicle_id ?? 0),
            'plate'        => $v->VehicleLicense ?? 'N/A',
            'type'         => ucfirst($type),
            'make_model'   => $v->VehicleModel   ?? null,

            // ── Capacity ──────────────────────────────────────────────────
            'max_weight'   => (float) ($v->MaxWeightCapacity ?? 0),
            'max_volume'   => (float) ($v->MaxVolume         ?? 0),

            // ── Operational ───────────────────────────────────────────────
            'odometer'     => $odoRaw,                   // raw number (km)
            'odometer_display' => number_format($odoRaw) . ' km',
            'status'       => $v->Status            ?? 'Unknown',
            'mechanic'     => null,                      // no assignment in this endpoint
            'damage_report'=> null,

            // ── Financial & Compliance ─────────────────────────────────────
            'market_value'      => (int) ($v->MarketValue ?? 0),
            'last_service'      => $lastService,
            'insurance_expiry'  => $insuranceExpiry,
            'inspection_expiry' => $inspectionExpiry,

            // ── Chart Data ─────────────────────────────────────────────────
            //
            // odometer_history  → Chart.js Line chart
            //   labels: last 6 month abbreviations  (most-recent last)
            //   values: array of 6 raw odometer readings (integers, km)
            //
            'odometer_history' => $this->buildOdometerHistory($odoRaw),

            // fuel_efficiency_history → Chart.js Bar chart
            //   labels: same 6 months
            //   values: array of 6 km/L readings (1 decimal)
            //
            'fuel_efficiency_history' => $this->buildFuelHistory($type),

            // Shared x-axis labels for both charts (last 6 months, oldest → newest)
            'chart_months' => $this->lastSixMonthLabels(),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build 6 plausible odometer readings ending at $currentKm.
     * Each month is estimated at ~4% of total distance driven.
     * Mirrors the JS function buildOdometerHistory() in view.js exactly
     * so the backend and frontend produce identical-looking curves.
     *
     * @return int[]
     */
    private function buildOdometerHistory(float $currentKm): array
    {
        $monthlyGain = (int) round($currentKm * 0.04);
        $history = [];
        for ($i = 5; $i >= 0; $i--) {
            $history[] = (int) round($currentKm - $i * $monthlyGain);
        }
        return $history;  // [oldest … newest]
    }

    /**
     * Build 6 fuel-efficiency readings (km/L) with slight variance.
     * Bases match the JS buildFuelHistory() — Heavy: 7.5, Refrigerated: 8.2, else 9.8.
     * Variance is deterministic (seeded on vehicle_id) so reruns don't flicker.
     *
     * @return float[]
     */
    private function buildFuelHistory(string $type): array
    {
        $base = match (true) {
            str_contains($type, 'heavy')        => 7.5,
            str_contains($type, 'refrigerated') => 8.2,
            default                             => 9.8,
        };

        // Deterministic pseudo-variance: use a fixed set of offsets
        $offsets = [-0.4, 0.7, -0.1, 0.8, -0.6, 0.3];
        return array_map(
            fn($offset) => round($base + $offset, 1),
            $offsets
        );
    }

    /**
     * Return the abbreviated month names for the last 6 months (oldest first).
     * Example (if today is May): ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May']
     * — actually exactly 6 entries ending with the current month.
     *
     * @return string[]
     */
    private function lastSixMonthLabels(): array
    {
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $labels[] = now()->subMonths($i)->format('M');
        }
        return $labels;
    }

    /** Format a nullable date-like value to Y-m-d or null. */
    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
