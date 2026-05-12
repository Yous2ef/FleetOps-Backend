<?php

/**
 * @file: FuelService.php
 * @description: خدمة تدقيق الوقود ومقارنة الكفاءة - Reporting & Analytics Service
 *               Serves two UI tabs:
 *               1. Fuel Expense Audit  — discrepancy detection (GPS distance vs actual fuel)
 *               2. Fuel Efficiency Comparator — km/L per vehicle vs fleet average with trend
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Services;

use App\Modules\ReportingAnalytics\Repositories\FuelAuditLogRepository;
use App\Modules\ReportingAnalytics\Models\FuelAuditLog;
use App\Modules\RouteDispatch\Models\Vehicle;
use App\Modules\RouteDispatch\Models\Route;
use Illuminate\Support\Facades\DB;

class FuelService
{
    // km/L baseline per vehicle type (used to compute expected fuel from GPS distance)
    protected const BASELINE_KM_PER_LITRE = [
        'light'        => 12.0,
        'heavy'        => 5.0,
        'refrigerated' => 4.5,
    ];

    // Discrepancy thresholds → flag level
    protected const FLAG_INVESTIGATE = 0.20; // > 20% over expected → Investigate
    protected const FLAG_REVIEW      = 0.10; // > 10% over expected → Review

    protected FuelAuditLogRepository $fuelRepo;

    public function __construct(FuelAuditLogRepository $fuelRepo)
    {
        $this->fuelRepo = $fuelRepo;
    }

    // =========================================================================
    // TAB 1 — Fuel Expense Audit
    // =========================================================================

    /**
     * Build the Fuel Expense Audit table data.
     * For each vehicle: GPS distance in period, expected fuel, actual fuel, discrepancy %, flag.
     *
     * @param  string $periodStart  YYYY-MM-DD
     * @param  string $periodEnd    YYYY-MM-DD
     * @return array{
     *   vehicles_tracked: int,
     *   flagged_count: int,
     *   rows: array
     * }
     */
    public function getFuelExpenseAudit(string $periodStart, string $periodEnd): array
    {
        // 1. Aggregate actual fuel consumed per vehicle from fuel_audit_logs
        $fuelAgg = $this->fuelRepo->aggregateByVehicle($periodStart, $periodEnd)->keyBy('vehicle_id');

        // 2. Aggregate GPS distance per vehicle from completed routes in the period
        try {
            $gpsDistances = Route::select('vehicle_id', DB::raw('SUM(total_distance) AS gps_distance_km'))
                ->whereBetween('actual_start_time', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
                ->whereNotNull('total_distance')
                ->groupBy('vehicle_id')
                ->get()
                ->keyBy('vehicle_id');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FuelService] GPS distance query failed: ' . $e->getMessage());
            $gpsDistances = collect();
        }

        // 3. Load vehicle info for all relevant vehicle IDs
        $vehicleIds = $fuelAgg->keys()->merge($gpsDistances->keys())->unique()->values()->all();
        $vehicles   = Vehicle::whereIn('vehicle_id', $vehicleIds)
            ->get(['vehicle_id', 'VehicleModel', 'VehicleType', 'VehicleLicense'])
            ->keyBy('vehicle_id');

        // 4. Build rows
        $rows        = [];
        $flaggedCount = 0;

        foreach ($vehicleIds as $vehicleId) {
            $vehicle      = $vehicles->get($vehicleId);
            $fuel         = $fuelAgg->get($vehicleId);
            $gps          = $gpsDistances->get($vehicleId);

            $actualFuel   = $fuel  ? (float) $fuel->total_fuel_litres : 0.0;
            $gpsKm        = $gps   ? (float) $gps->gps_distance_km    : 0.0;

            // Expected fuel = GPS distance / baseline km/L for this vehicle type
            $vehicleType  = strtolower($vehicle?->VehicleType ?? 'light');
            $baseline     = self::BASELINE_KM_PER_LITRE[$vehicleType]
                            ?? self::BASELINE_KM_PER_LITRE['light'];
            $expectedFuel = $baseline > 0 ? round($gpsKm / $baseline, 1) : 0.0;

            // Discrepancy % = (actual - expected) / expected
            $discrepancyPct = ($expectedFuel > 0)
                ? round(($actualFuel - $expectedFuel) / $expectedFuel, 3)
                : 0.0;

            // Flag level
            $flag = 'none';
            if ($discrepancyPct > self::FLAG_INVESTIGATE) {
                $flag = 'investigate';
                $flaggedCount++;
            } elseif ($discrepancyPct > self::FLAG_REVIEW) {
                $flag = 'review';
                $flaggedCount++;
            }

            $rows[] = [
                'vehicle_id'       => $vehicleId,
                'vehicle_license'  => $vehicle?->VehicleLicense,
                'vehicle_model'    => $vehicle?->VehicleModel,
                'vehicle_type'     => $vehicle?->VehicleType,
                'period'           => $this->formatPeriodLabel($periodStart, $periodEnd),
                'gps_distance_km'  => round($gpsKm, 1),
                'expected_fuel_l'  => $expectedFuel,
                'actual_fuel_l'    => round($actualFuel, 1),
                'discrepancy_pct'  => round($discrepancyPct * 100, 1), // as percentage e.g. 25.7
                'flag'             => $flag,
            ];
        }

        // 5. Sort by discrepancy descending (highest risk first)
        usort($rows, fn($a, $b) => $b['discrepancy_pct'] <=> $a['discrepancy_pct']);

        return [
            'vehicles_tracked' => count($rows),
            'flagged_count'    => $flaggedCount,
            'rows'             => $rows,
        ];
    }

    /**
     * Add a new fuel invoice (fill-up record).
     *
     * @param  array $data  vehicle_plate, fill_date, liters_filled, total_cost_egp, odometer_km, supplier
     * @return FuelAuditLog
     */
    public function addFuelInvoice(array $data): FuelAuditLog
    {
        // Resolve vehicle by license plate
        $vehicle = Vehicle::where('VehicleLicense', $data['vehicle_plate'])->firstOrFail();

        $liters    = (float) $data['liters_filled'];
        $totalCost = (float) $data['total_cost_egp'];
        $unitPrice = $liters > 0 ? round($totalCost / $liters, 4) : 0.0;

        return $this->fuelRepo->create([
            'vehicle_id'       => $vehicle->vehicle_id,
            'log_ts'           => $data['fill_date'],
            'fuel_quantity'    => $liters,
            'unit_price'       => $unitPrice,
            'odometer_reading' => (float) $data['odometer_km'],
            // supplier/station stored in notes if column exists, otherwise ignored
        ]);
    }

    // =========================================================================
    // TAB 2 — Fuel Efficiency Comparator
    // =========================================================================

    /**
     * Build the Fuel Efficiency Comparator data.
     * Computes km/L per vehicle, fleet average, most/least efficient, and trend vs previous period.
     *
     * @param  string $periodStart  YYYY-MM-DD
     * @param  string $periodEnd    YYYY-MM-DD
     * @return array{
     *   vehicles_tracked: int,
     *   fleet_average_km_per_litre: float,
     *   most_efficient: array,
     *   least_efficient: array,
     *   chart_data: array,
     *   table: array
     * }
     */
    public function getFuelEfficiencyComparator(string $periodStart, string $periodEnd): array
    {
        // Current period aggregates
        $currentAgg = $this->fuelRepo->aggregateByVehicle($periodStart, $periodEnd)->keyBy('vehicle_id');

        // GPS distance for current period
        try {
            $currentGps = Route::select('vehicle_id', DB::raw('SUM(total_distance) AS gps_distance_km'))
                ->whereBetween('actual_start_time', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
                ->whereNotNull('total_distance')
                ->groupBy('vehicle_id')
                ->get()
                ->keyBy('vehicle_id');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FuelService] GPS current query failed: ' . $e->getMessage());
            $currentGps = collect();
        }

        // Previous period (same length) for trend calculation
        [$prevStart, $prevEnd] = $this->previousPeriod($periodStart, $periodEnd);
        $prevAgg = $this->fuelRepo->aggregateByVehicle($prevStart, $prevEnd)->keyBy('vehicle_id');
        try {
            $prevGps = Route::select('vehicle_id', DB::raw('SUM(total_distance) AS gps_distance_km'))
                ->whereBetween('actual_start_time', [$prevStart . ' 00:00:00', $prevEnd . ' 23:59:59'])
                ->whereNotNull('total_distance')
                ->groupBy('vehicle_id')
                ->get()
                ->keyBy('vehicle_id');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FuelService] GPS prev query failed: ' . $e->getMessage());
            $prevGps = collect();
        }

        // Vehicle info
        $vehicleIds = $currentAgg->keys()->merge($currentGps->keys())->unique()->values()->all();
        $vehicles   = Vehicle::whereIn('vehicle_id', $vehicleIds)
            ->get(['vehicle_id', 'VehicleModel', 'VehicleType', 'VehicleLicense'])
            ->keyBy('vehicle_id');

        // Build per-vehicle efficiency rows
        $rows = [];
        foreach ($vehicleIds as $vehicleId) {
            $vehicle  = $vehicles->get($vehicleId);
            $fuel     = $currentAgg->get($vehicleId);
            $gps      = $currentGps->get($vehicleId);

            $totalFuel = $fuel ? (float) $fuel->total_fuel_litres : 0.0;
            $totalKm   = $gps  ? (float) $gps->gps_distance_km   : 0.0;

            // km/L = distance / fuel consumed
            $kmPerLitre = ($totalFuel > 0) ? round($totalKm / $totalFuel, 1) : 0.0;

            // Previous period efficiency for trend
            $prevFuel   = $prevAgg->get($vehicleId);
            $prevGpsRow = $prevGps->get($vehicleId);
            $prevKm     = $prevGpsRow ? (float) $prevGpsRow->gps_distance_km   : 0.0;
            $prevFuelL  = $prevFuel   ? (float) $prevFuel->total_fuel_litres   : 0.0;
            $prevKmPerL = ($prevFuelL > 0) ? round($prevKm / $prevFuelL, 1) : null;

            // Trend = current - previous (positive = improved efficiency)
            $trend = ($prevKmPerL !== null) ? round($kmPerLitre - $prevKmPerL, 1) : null;

            $rows[] = [
                'vehicle_id'      => $vehicleId,
                'vehicle_license' => $vehicle?->VehicleLicense,
                'vehicle_model'   => $vehicle?->VehicleModel,
                'vehicle_type'    => $vehicle?->VehicleType,
                'total_km'        => round($totalKm, 1),
                'total_fuel_l'    => round($totalFuel, 1),
                'km_per_litre'    => $kmPerLitre,
                'trend'           => $trend, // null if no previous data
            ];
        }

        // Filter out vehicles with no data
        $rows = array_filter($rows, fn($r) => $r['km_per_litre'] > 0);

        // Sort by km/L descending (most efficient first)
        usort($rows, fn($a, $b) => $b['km_per_litre'] <=> $a['km_per_litre']);
        $rows = array_values($rows);

        // Fleet average
        $efficiencies  = array_column($rows, 'km_per_litre');
        $fleetAverage  = count($efficiencies) > 0
            ? round(array_sum($efficiencies) / count($efficiencies), 1)
            : 0.0;

        // Add vs-fleet-average percentage to each row
        foreach ($rows as &$row) {
            $row['vs_fleet_avg_pct'] = $fleetAverage > 0
                ? round((($row['km_per_litre'] - $fleetAverage) / $fleetAverage) * 100, 1)
                : 0.0;
        }
        unset($row);

        // Most / least efficient
        $mostEfficient  = !empty($rows) ? $rows[0]                  : null;
        $leastEfficient = !empty($rows) ? $rows[count($rows) - 1]   : null;

        // Chart data (bar chart — vehicle license + km/L + above/below average flag)
        $chartData = array_map(fn($r) => [
            'label'          => $r['vehicle_license'],
            'km_per_litre'   => $r['km_per_litre'],
            'above_average'  => $r['km_per_litre'] >= $fleetAverage,
        ], $rows);

        // Ranked table
        $table = array_map(function ($r, $idx) {
            return [
                'rank'            => $idx + 1,
                'vehicle_license' => $r['vehicle_license'],
                'vehicle_type'    => $r['vehicle_type'],
                'avg_efficiency'  => $r['km_per_litre'],
                'vs_fleet_avg'    => $r['vs_fleet_avg_pct'],
                'trend'           => $r['trend'],
            ];
        }, $rows, array_keys($rows));

        return [
            'vehicles_tracked'          => count($rows),
            'fleet_average_km_per_litre' => $fleetAverage,
            'most_efficient'            => $mostEfficient ? [
                'vehicle_license' => $mostEfficient['vehicle_license'],
                'km_per_litre'    => $mostEfficient['km_per_litre'],
            ] : null,
            'least_efficient'           => $leastEfficient ? [
                'vehicle_license' => $leastEfficient['vehicle_license'],
                'km_per_litre'    => $leastEfficient['km_per_litre'],
            ] : null,
            'chart_data'                => $chartData,
            'table'                     => $table,
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Compute the previous period of the same duration.
     * e.g. April 1–30 → March 1–31
     */
    protected function previousPeriod(string $periodStart, string $periodEnd): array
    {
        $start    = \Carbon\Carbon::parse($periodStart);
        $end      = \Carbon\Carbon::parse($periodEnd);
        $duration = $start->diffInDays($end) + 1;

        $prevEnd   = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($duration - 1);

        return [$prevStart->toDateString(), $prevEnd->toDateString()];
    }

    /**
     * Format a human-readable period label (e.g. "April 2026").
     */
    protected function formatPeriodLabel(string $periodStart, string $periodEnd): string
    {
        $start = \Carbon\Carbon::parse($periodStart);
        $end   = \Carbon\Carbon::parse($periodEnd);

        // Same month → "April 2026"
        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->format('F Y');
        }

        return $start->format('M d') . ' – ' . $end->format('M d, Y');
    }
}
