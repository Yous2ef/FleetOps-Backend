<?php

/**
 * @file: KpiService.php
 * @description: خدمة حساب مؤشرات الأداء الرئيسية - Reporting & Analytics Service (AN-01/02/03/04)
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KpiService
{
    /**
     * حساب نسبة التسليم في الموعد (AN-04 / fn41)
     * @param string $periodStart
     * @param string $periodEnd
     * @param int|null $driverId  null = fleet-wide
     * @return array  ['on_time_percentage' => float, 'total' => int, 'on_time' => int]
     */
    public function calculateOnTimeRate(string $periodStart, string $periodEnd, ?int $driverId = null): array
    {
        $query = DB::table('order')
            ->where('Status', 'Delivered')
            ->whereBetween('DeliveredAt', [$periodStart, $periodEnd]);
            
        if ($driverId) {
            $query->where('DriverID(FK)', $driverId);
        }
        
        $orders = $query->get();
        $total = $orders->count();
        $onTime = 0;
        
        foreach ($orders as $order) {
            if ($order->PromisedWindow && $order->DeliveredAt <= $order->PromisedWindow) {
                $onTime++;
            }
        }
        
        $percentage = $total > 0 ? round(($onTime / $total) * 100, 2) : 0;
        
        return [
            'on_time_percentage' => $percentage,
            'total' => $total,
            'on_time' => $onTime
        ];
    }

    /**
     * حساب نقاط أداء السائق (AN-02 / fn22)
     * Score = (delivery_speed × A) + (fuel_efficiency × B) + (customer_rating × C)
     * Weights A, B, C configurable via config file
     * @param int $driverId
     * @param string $periodStart
     * @param string $periodEnd
     * @return array  ['composite_score' => float, 'breakdown' => array]
     */
    public function calculateDriverPerformanceScore(int $driverId, string $periodStart, string $periodEnd): array
    {
        // 1. Get weights from config
        $weights = config('analytics.performance_weights', [
            'delivery_speed' => 0.4,
            'fuel_efficiency' => 0.3,
            'customer_rating' => 0.3
        ]);

        // 2. Calculate each component
        
        // - on_time_rate: deliveries on time / total deliveries
        $orders = DB::table('order')
            ->where('DriverID(FK)', $driverId)
            ->where('Status', 'Delivered')
            ->whereBetween('DeliveredAt', [$periodStart, $periodEnd])
            ->get();
            
        $totalDeliveries = $orders->count();
        $onTime = 0;
        foreach ($orders as $order) {
            if ($order->PromisedWindow && $order->DeliveredAt <= $order->PromisedWindow) {
                $onTime++;
            }
        }
        $onTimeRate = $totalDeliveries > 0 ? ($onTime / $totalDeliveries) * 100 : 0;

        // - fuel_efficiency_score: normalize (km/L vs fleet average)
        $driverRoutes = DB::table('routes')
            ->where('driver_id', $driverId)
            ->whereBetween('scheduled_start_time', [$periodStart, $periodEnd])
            ->get();
            
        $driverDistance = $driverRoutes->sum('total_distance');
        $driverFuel = $driverRoutes->sum('fuel_consumption_est');
        $driverKmL = $driverFuel > 0 ? $driverDistance / $driverFuel : 0;

        $fleetRoutes = DB::table('routes')
            ->whereBetween('scheduled_start_time', [$periodStart, $periodEnd])
            ->get();
            
        $fleetDistance = $fleetRoutes->sum('total_distance');
        $fleetFuel = $fleetRoutes->sum('fuel_consumption_est');
        $fleetKmL = $fleetFuel > 0 ? $fleetDistance / $fleetFuel : 0;

        $fuelEfficiencyScore = 0;
        if ($fleetKmL > 0) {
            $ratio = $driverKmL / $fleetKmL;
            $fuelEfficiencyScore = min(100, max(0, $ratio * 100));
        }

        // - customer_rating_avg: avg from post-delivery feedback
        // Fallback to driver_performance table since feedback table doesn't exist
        $perf = DB::table('driver_performance')
            ->where('driver_id', $driverId)
            ->where('period_start', '>=', $periodStart)
            ->where('period_end', '<=', $periodEnd)
            ->avg('avg_customer_rating');
            
        $customerRatingAvg = $perf ? (float) $perf : 0;
        $customerRatingScore = ($customerRatingAvg / 5) * 100;

        // 3. composite_score = sum of (component × weight)
        $compositeScore = ($onTimeRate * $weights['delivery_speed']) +
                          ($fuelEfficiencyScore * $weights['fuel_efficiency']) +
                          ($customerRatingScore * $weights['customer_rating']);

        $scoreData = [
            'on_time_rate' => round($onTimeRate, 2),
            'fuel_efficiency_score' => round($fuelEfficiencyScore, 2),
            'customer_rating_avg' => round($customerRatingAvg, 2),
            'composite_score' => round($compositeScore, 2),
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $totalDeliveries, // All in list are Delivered
            'breakdown' => [
                'on_time_deliveries' => $onTime,
                'driver_km_l' => round($driverKmL, 2),
                'fleet_km_l' => round($fleetKmL, 2),
                'weights_used' => $weights
            ]
        ];

        // 4. Save to driver_performance_scores table
        $repo = app(\App\Modules\ReportingAnalytics\Repositories\DriverPerformanceRepository::class);
        $repo->upsertScore($driverId, $periodStart, $periodEnd, $scoreData);

        // 5. Return score with breakdown
        return array_merge([
            'driver_id' => $driverId, 
            'period_start' => $periodStart, 
            'period_end' => $periodEnd
        ], $scoreData);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Analytics Page Endpoints
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * مؤشرات الأداء الرئيسية لصفحة التحليلات (analytics-kpis)
     * Returns: Revenue, Active Vehicles, Delivered, Late, Efficiency
     *
     * @param string $range  'today' | '7d' | '30d'
     * @return array
     */
    public function getAnalyticsKpis(string $range = '30d'): array
    {
        $now = Carbon::now();

        switch($range) {
        case 'today':
            $start = $now->copy()->startOfDay();
            break;
        case '7d':
            $start = $now->copy()->subDays(7)->startOfDay();
            break;
        case '30d':
        default:
            $start = $now->copy()->subDays(30)->startOfDay();
            break;
        }
        $end = $now->copy()->endOfDay();

        $periodDays = max(1, (int) $start->diffInDays($end));

        $prevEnd   = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays($periodDays)->startOfDay();

        $revenue = DB::table('cash_ledger')
            ->where('payment_status','collected')->whereBetween('transaction_ts',[$start,$end])
            ->sum('amount_collected');

        $prevRevenue = DB::table('cash_ledger')
            ->where('payment_status','collected')->whereBetween('transaction_ts',[$prevStart,$prevEnd])
            ->sum('amount_collected');

        $activeVehicles = DB::table('vehicles')->where('Status','active')->count();

        $totalVehicles = DB::table('vehicles')->count();

        $delivered = DB::table('order')->where('Status','delivered')
            ->whereBetween('DeliveredAt',[$start,$end])->count();

        $prevDelivered = DB::table('order')->where('Status','delivered')
            ->whereBetween('DeliveredAt',[$prevStart,$prevEnd])->count();

        $lateDeliveries = DB::table('order')->where('Status', 'delivered')
            ->whereBetween('DeliveredAt', [$start, $end])
            ->whereNotNull('PromisedWindow')
            ->whereColumn('DeliveredAt', '>', 'PromisedWindow')
            ->count();

        $prevLate = DB::table('order')->where('Status', 'delivered')
            ->whereBetween('DeliveredAt', [$prevStart, $prevEnd])
            ->whereNotNull('PromisedWindow')
            ->whereColumn('DeliveredAt', '>', 'PromisedWindow')
            ->count();


        $efficiency = $delivered > 0 ? round(($delivered - $lateDeliveries)/ $delivered * 100) : 0;
        $prevEfficiency = $prevDelivered > 0 ? round(($prevDelivered - $prevLate) / $prevDelivered * 100) : 0;

        return [
            'range' => $range,
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'kpis' => [
                'revenue' => [
                    'value'  => round($revenue, 2),
                    'change' => $this->pctChange($revenue, $prevRevenue),
                    'unit'   => 'EGP',
                ],
                'active_vehicles' => [
                    'value'  => $activeVehicles,
                    'total'  => $totalVehicles,
                    'change' => null, // snapshot, no previous comparison
                ],
                'delivered' => [
                    'value'  => $delivered,
                    'change' => $this->pctChange($delivered, $prevDelivered),
                ],
                'late' => [
                    'value'  => $lateDeliveries,
                    'change' => $this->pctChange($lateDeliveries, $prevLate),
                ],
                'efficiency' => [
                    'value'  => $efficiency,
                    'change' => $this->pctChange($efficiency, $prevEfficiency),
                    'unit'   => '%',
                ],
            ],
        ];
    }

    /**
     * توزيع حالة الأسطول (analytics-fleet-distribution)
     * Returns count and percentage of vehicles per status.
     *
     * @return array
     */
    // for the fleet utilization tab
    public function getFleetDistribution(): array
    {
        $total = DB::table('vehicles')->count();

        if ($total === 0) {
            return ['total' => 0, 'distribution' => []];
        }

        $statuses = DB::table('vehicles')
            ->select('Status', DB::raw('COUNT(*) as count'))
            ->groupBy('Status')
            ->get();

        $distribution = [];
        foreach ($statuses as $row) {
            $distribution[] = [
                'status'     => $row->Status,
                'count'      => (int) $row->count,
                'percentage' => round(($row->count / $total) * 100, 1),
            ];
        }

        // Sort by count descending
        usort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total'        => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * كشف مراجعة الوقود (analytics-fuel-audit / fn24)
     * Fetches fuel audit logs directly from the fuel_audit_logs table.
     *
     * @param string $range  'today' | '7d' | '30d'
     * @return array
     */
    public function getFuelAudit(string $range = '30d'): array
    {
        $now = Carbon::now();

        switch ($range) {
            case 'today':
                $start = $now->copy()->startOfDay();
                break;
            case '7d':
                $start = $now->copy()->subDays(7)->startOfDay();
                break;
            case '30d':
            default:
                $start = $now->copy()->subDays(30)->startOfDay();
                break;
        }
        $end = $now->copy()->endOfDay();

        // Actual fuel cost per vehicle from fuel_audit_logs
        $actualPerVehicle = DB::table('fuel_audit_logs')
            ->join('vehicles', 'fuel_audit_logs.vehicle_id', '=', 'vehicles.vehicle_id')
            ->whereBetween('fuel_audit_logs.log_ts', [$start, $end])
            ->select(
                'vehicles.vehicle_id',
                'vehicles.VehicleLicense',
                'vehicles.VehicleType',
                DB::raw('SUM(fuel_audit_logs.fuel_quantity) as actual_litres'),
                DB::raw('SUM(fuel_audit_logs.total_cost)    as actual_invoice')
            )
            ->groupBy('vehicles.vehicle_id', 'vehicles.VehicleLicense', 'vehicles.VehicleType')
            ->get()
            ->keyBy('vehicle_id');

        // GPS distance & expected fuel from routes
        $expected = DB::table('routes')
            ->whereBetween('scheduled_start_time', [$start, $end])
            ->whereNotNull('fuel_consumption_est')
            ->select(
                'vehicle_id',
                DB::raw('SUM(total_distance)       as gps_distance_km'),
                DB::raw('SUM(fuel_consumption_est) as expected_fuel')
            )
            ->groupBy('vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        // Merge into rows
        $allVehicleIds = $actualPerVehicle->keys()->merge($expected->keys())->unique();

        $rows = [];
        foreach ($allVehicleIds as $vid) {
            $act = $actualPerVehicle[$vid] ?? null;
            $exp = $expected[$vid]         ?? null;

            $gpsDistance   = $exp ? round($exp->gps_distance_km, 1) : 0;
            $expectedFuel  = $exp ? round($exp->expected_fuel, 2)   : 0;
            $actualInvoice = $act ? round($act->actual_invoice, 2)  : 0;
            $actualLitres  = $act ? round($act->actual_litres, 2)   : 0;

            // Status: flagged if actual fuel exceeds expected by > 15%
            $status = ($expectedFuel > 0 && $actualLitres > $expectedFuel * 1.15)
                ? 'flagged'
                : 'ok';

            $rows[] = [
                'vehicle_id'     => $vid,
                'license'        => $act->VehicleLicense ?? 'N/A',
                'type'           => $act->VehicleType    ?? 'N/A',
                'gps_distance'   => $gpsDistance,
                'expected_fuel'  => $expectedFuel,
                'actual_invoice' => $actualInvoice,
                'status'         => $status,
            ];
        }

        // Sort flagged first
        usort($rows, function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'flagged' ? -1 : 1;
            }
            return $b['actual_invoice'] <=> $a['actual_invoice'];
        });

        return [
            'range'        => $range,
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'vehicles'     => $rows,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CO2 / Sustainability
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * تقرير انبعاثات CO2 (AN-03 / fn40)
     *
     * Output matches the CO2 Emissions Report table:
     *   Vehicle | Type | Emissions (Tons) | Reduction vs Last Month | Status
     *
     * Uses routes + vehicles only — no extra table needed.
     * Formula: CO2 (kg) = distance_km × emission_factor
     *   light=0.21 | heavy=0.37 | refrigerated=0.43 (kg/km)
     *
     * @param string $period  'monthly' | 'quarterly'
     * @return array
     */
    public function generateCO2Report(string $period = 'monthly'): array
    {
        
        $now = Carbon::now();        

        $factors = ['light' => 0.21, 'heavy' => 0.37, 'refrigerated' => 0.43];

        // Type labels for display
        $typeLabels = ['light' => 'Van', 'heavy' => 'Truck'];

        // Helper: sum distance per vehicle for a given window
        $getDistances = function ($from, $to) {
            return DB::table('routes')
                ->join('vehicles', 'routes.vehicle_id', '=', 'vehicles.vehicle_id')
                ->whereBetween('routes.scheduled_start_time', [$from, $to])
                ->whereNotNull('routes.total_distance')
                ->select(
                    'vehicles.vehicle_id',
                    'vehicles.VehicleLicense',
                    'vehicles.VehicleType',
                    DB::raw('SUM(routes.total_distance) as distance_km')
                )
                ->groupBy('vehicles.vehicle_id', 'vehicles.VehicleLicense', 'vehicles.VehicleType')
                ->get()
                ->keyBy('vehicle_id');
        };

        $current  = $getDistances($curStart, $curEnd);
        $previous = $getDistances($prevStart, $prevEnd);

        // Build per-vehicle rows
        $rows = [];
        
        if ($current->isEmpty()) {
            return [
                'period'             => $period,
                'period_start'       => $curStart->toDateString(),
                'period_end'         => $curEnd->toDateString(),
                'total_emissions_tons' => 0.0,
                'vehicles'           => [],
                'message'            => 'No data found for this period'
            ];
        }
        foreach ($current as $row) {
            $type        = strtolower($row->VehicleType ?? 'light');
            $factor      = $factors[$type] ?? $factors['light'];
            $co2Tons     = round($row->distance_km * $factor / 1000, 2); // kg → tons

            // Reduction vs last period: positive = improved (less CO2), negative = worse
            $prevRow     = $previous[$row->vehicle_id] ?? null;
            $prevCo2Tons = $prevRow
                ? round($prevRow->distance_km * ($factors[strtolower($prevRow->VehicleType ?? 'light')] ?? $factor) / 1000, 2)
                : null;

            if ($prevCo2Tons && $prevCo2Tons > 0) {
                $reductionPct = round(($prevCo2Tons - $co2Tons) / $prevCo2Tons * 100, 1);
                $reductionStr = ($reductionPct >= 0 ? '+' : '') . $reductionPct . '%';
            } else {
                $reductionPct = null;
                $reductionStr = 'N/A';
            }

            // Status label based on reduction
            if ($reductionPct === null) {
                $status = 'N/A';
            } elseif ($reductionPct >= 10) {
                $status = 'Excellent';
            } elseif ($reductionPct >= 0) {
                $status = 'Good';
            } elseif ($reductionPct >= -5) {
                $status = 'Needs Improvement';
            } else {
                $status = 'Poor';
            }

            $rows[] = [
                'vehicle'                  => $row->VehicleLicense,
                'type'                     => $typeLabels[$type] ?? ucfirst($type),
                'emissions_tons'           => $co2Tons,
                'reduction_vs_last_month'  => $reductionStr,
                'status'                   => $status,
            ];
        }

        // Sort by emissions descending
        usort($rows, function($a, $b) {
            return $b['emissions_tons'] <=> $a['emissions_tons'];
        });

        return [
            'period'            => $period,
            'period_start'      => $curStart->toDateString(),
            'period_end'        => $curEnd->toDateString(),
            'total_emissions_tons' => round(array_sum(array_column($rows, 'emissions_tons')), 2),
            'vehicles'          => $rows,
        ];
    }

    /**
     * كشف الشذوذات (AN-07)
     * @param string $date
     * @return array  list of detected anomalies
     */
    public function detectAnomalies(string $date): array
    {
        // TODO: Detect anomalies
        // 1. Missing fuel: compare fuel invoices vs GPS distance traveled (fn24)
        // 2. Unusual speeds: GPS pings with speed > threshold
        // 3. Excessive stop durations: stops > 2x average stop time
        // 4. Return list of anomalies with severity and vehicle/driver info
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Private Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Calculate percentage change string.
     */
    private function pctChange(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }
        $change = round(($current - $previous) / $previous * 100, 1);
        $sign   = $change >= 0 ? '+' : '';
        return $sign . $change . '%';
    }
}
