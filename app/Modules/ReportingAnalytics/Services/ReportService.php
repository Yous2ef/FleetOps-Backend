<?php

/**
 * @file: ReportService.php
 * @description: خدمة تصدير التقارير إلى PDF/Excel - Reporting & Analytics Service (AN-06)
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Services;

use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\RouteDispatch\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Modules\RouteDispatch\Models\Route;
use App\Modules\OrderManagement\Models\Order;
use App\Modules\ReportingAnalytics\Models\IncidentReport;
use App\Modules\ReportingAnalytics\Models\FuelAuditLog;

class ReportService
{
    /**
     * تصدير تقرير الأداء إلى Excel (AN-06 / fn42)
     * @param string $reportType  (driver_performance | fleet_kpis | delivery_summary | co2 | maintenance_cost)
     * @param array  $filters     (period_start, period_end, driver_id?, vehicle_id?)
     * @param string $format      ('xlsx' | 'csv' | 'pdf')
     * @return array  ['file_path' => string, 'filename' => string, 'size_bytes' => int]
     * @throws Exception
     */
    public function exportReport(string $reportType, array $filters, string $format = 'xlsx'): array
    {
        // 1. Validate reportType and format
        $validTypes = ['driver_performance', 'fleet_kpis', 'delivery_summary', 'co2', 'maintenance_cost'];
        if (!in_array($reportType, $validTypes)) {
            throw new \InvalidArgumentException("Invalid report type: {$reportType}");
        }

        $validFormats = ['xlsx', 'csv', 'pdf'];
        if (!in_array($format, $validFormats)) {
            throw new \InvalidArgumentException("Invalid format: {$format}");
        }

        $periodStart = $filters['period_start'] ?? Carbon::now()->subDays(30)->toDateString();
        $periodEnd = $filters['period_end'] ?? Carbon::now()->toDateString();
        
        // 2. Fetch data based on reportType and filters
        $data = [];
        $kpiService = app(\App\Modules\ReportingAnalytics\Services\KpiService::class);

        switch ($reportType) {
            case 'driver_performance':
                if (!empty($filters['driver_id'])) {
                    $data = [$kpiService->calculateDriverPerformanceScore($filters['driver_id'], $periodStart, $periodEnd)];
                } else {
                    $repo = app(\App\Modules\ReportingAnalytics\Repositories\DriverPerformanceRepository::class);
                    $data = $repo->getLeaderboard($periodStart, $periodEnd);
                }
                break;
            case 'fleet_kpis':
                $data = [$kpiService->getAnalyticsKpis('30d')];
                break;
            case 'delivery_summary':
                $data = [$this->getDeliverySummary($periodStart, $periodEnd, $filters['driver_id'] ?? null)];
                break;
            case 'co2':
                $res = $kpiService->generateCO2Report('monthly');
                $data = $res['vehicles'] ?? [];
                break;
            case 'maintenance_cost':
                $res = $this->getMaintenanceCostReport($periodStart, $periodEnd);
                $data = $res['data'];
                break;
        }

        // Flatten data for CSV/XLSX
        $flatData = [];
        foreach ($data as $item) {
            $flatData[] = $this->flattenArray((array) $item);
        }

        // 4. Generate filename
        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = "{$reportType}_{$periodStart}_{$periodEnd}_{$timestamp}.{$format}";
        $path = "exports/{$filename}";

        // 3. Based on format & 5. Store file temporarily
        if ($format === 'pdf' && class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML("<h1>{$reportType} Report</h1><pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>");
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, $pdf->output());
        } elseif (($format === 'xlsx' || $format === 'csv') && class_exists('\Maatwebsite\Excel\Facades\Excel')) {
            // Native fallback for Excel if Export class isn't created
            $this->generateNativeCsv($flatData, $path);
        } else {
            // Fallback for all to native CSV
            if ($format === 'xlsx' || $format === 'pdf') {
                $filename = str_replace(['.xlsx', '.pdf'], '.csv', $filename);
                $path = "exports/{$filename}";
            }
            $this->generateNativeCsv($flatData, $path);
        }

        // 6. Return file info with download URL
        return [
            'file_path' => $path,
            'filename' => $filename,
            'size_bytes' => \Illuminate\Support\Facades\Storage::disk('local')->exists($path) ? \Illuminate\Support\Facades\Storage::disk('local')->size($path) : 0,
            'download_url' => url("storage/{$path}")
        ];
    }

    /**
     * Helper to flatten nested array for CSV
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix . (empty($prefix) ? '' : '_') . $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
            }
        }
        return $result;
    }

    /**
     * Helper to generate CSV natively
     */
    private function generateNativeCsv(array $data, string $path): void
    {
        $output = fopen('php://temp', 'r+');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, ['No data available for this report']);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $csvContent);
    }

    /**
     * تقرير ملخص التسليمات (AN-04 / fn41)
     * @param string $periodStart
     * @param string $periodEnd
     * @param int|null $driverId
     * @return array  delivery summary data
     */
    public function getDeliverySummary(string $periodStart, string $periodEnd, ?int $driverId = null): array
    {
        // 1. Query orders in period from SQL Server
        $query = \Illuminate\Support\Facades\DB::table('order')
            ->whereBetween('Created_at', [$periodStart, $periodEnd]);
            
        // 4. If driverId provided → filter by driver
        if ($driverId) {
            $query->where('DriverID(FK)', $driverId);
        }
        
        $orders = $query->get();
        
        // 2. Group by status: delivered, returned, failed, in_transit
        // 3. Calculate totals and percentages
        $total = $orders->count();
        
        $summary = [
            'total_orders' => $total,
            'delivered' => 0,
            'returned' => 0,
            'failed' => 0,
            'in_transit' => 0,
            'other' => 0
        ];
        
        foreach ($orders as $order) {
            $status = strtolower($order->Status ?? '');
            if ($status === 'delivered') {
                $summary['delivered']++;
            } elseif ($status === 'returned') {
                $summary['returned']++;
            } elseif ($status === 'failed') {
                $summary['failed']++;
            } elseif (in_array($status, ['intransit', 'in_transit', 'out for delivery'])) {
                $summary['in_transit']++;
            } else {
                $summary['other']++;
            }
        }
        
        $summary['delivered_pct'] = $total > 0 ? round(($summary['delivered'] / $total) * 100, 2) : 0;
        $summary['returned_pct'] = $total > 0 ? round(($summary['returned'] / $total) * 100, 2) : 0;
        $summary['failed_pct'] = $total > 0 ? round(($summary['failed'] / $total) * 100, 2) : 0;
        $summary['in_transit_pct'] = $total > 0 ? round(($summary['in_transit'] / $total) * 100, 2) : 0;
        
        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'driver_id' => $driverId,
            'summary' => $summary
        ];
    }

    /**
     * تقرير تكاليف الصيانة لأسطول المركبات (AN-01)
     * @param string $periodStart  YYYY-MM-DD
     * @param string $periodEnd    YYYY-MM-DD
     * @return array  per-vehicle maintenance costs, sorted by total_cost descending
     */
    public function getMaintenanceCostReport(string $periodStart, string $periodEnd): array
    {
        // 1. Query work_orders grouped by vehicle AND month
        $rows = WorkOrder::query()
            ->select(
                'vehicle_id',
                'type',
                DB::raw("FORMAT(opened_at, 'yyyy-MM') as month"),
                DB::raw('SUM(repair_cost) as total_cost'),
                DB::raw('COUNT(*) as work_order_count')
            )
            ->whereBetween('opened_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59'])
            ->whereNotNull('repair_cost')
            ->groupBy('vehicle_id', 'type', DB::raw("FORMAT(opened_at, 'yyyy-MM')"))
            ->orderBy('month', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'summary' => [
                    'preventive' => ['value' => 0, 'percentage' => 0],
                    'reactive'   => ['value' => 0, 'percentage' => 0],
                ],
                'data' => []
            ];
        }

        $totalPreventive = 0;
        $totalReactive   = 0;
        $totalOverall    = 0;

        $vehicleIds = $rows->pluck('vehicle_id')->unique()->all();
        $vehicles   = Vehicle::whereIn('vehicle_id', $vehicleIds)
            ->get(['vehicle_id', 'VehicleModel', 'VehicleLicense', 'MarketValue'])
            ->keyBy('vehicle_id');

        $report = [];
        foreach ($rows as $row) {
            $vehicle     = $vehicles->get($row->vehicle_id);
            $marketValue = $vehicle ? (float) $vehicle->MarketValue : 0.0;
            $totalCost   = (float) $row->total_cost;
            $totalOverall += $totalCost;

            if ($row->type === 'routine') {
                $totalPreventive += $totalCost;
            } else {
                $totalReactive += $totalCost;
            }

            $ratio = ($marketValue > 0) ? round($totalCost / $marketValue, 4) : null;

            $report[] = [
                'date'                  => $row->month,
                'type'                  => $row->type,
                'vehicle_id'            => $row->vehicle_id,
                'vehicle_model'         => $vehicle?->VehicleModel,
                'vehicle_license'       => $vehicle?->VehicleLicense,
                'market_value'          => $marketValue,
                'total_cost'            => $totalCost,
                'work_order_count'      => (int) $row->work_order_count,
                'cost_to_value_ratio'   => $ratio,
                'recommend_replacement' => ($ratio !== null) ? $ratio > 0.40 : false,
            ];
        }

        return [
            'summary' => [
                'preventive' => [
                    'value'      => round($totalPreventive, 2),
                    'percentage' => $totalOverall > 0 ? round(($totalPreventive / $totalOverall) * 100, 1) : 0
                ],
                'reactive'   => [
                    'value'      => round($totalReactive, 2),
                    'percentage' => $totalOverall > 0 ? round(($totalReactive / $totalOverall) * 100, 1) : 0
                ],
            ],
            'data' => $report
        ];
    }


    /**
     * مخطط الإيرادات الشهرية (analytics-revenue-chart)
     * Returns monthly revenue totals for the last N months from cash_ledger.
     *
     * @param int $months  Number of past months to include (1–24)
     * @return array  ['months' => int, 'labels' => string[], 'data' => float[], 'currency' => 'EGP']
     */
    public function getRevenueChart(int $months = 3): array
    {
        $labels  = [];
        $revenue = [];
        $loss    = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month      = Carbon::now()->subMonths($i)->startOfMonth();
            $monthStart = $month->copy()->startOfMonth()->startOfDay();
            $monthEnd   = $month->copy()->endOfMonth()->endOfDay();

            $totalRevenue = DB::table('cash_ledger')
                ->where('payment_status', 'collected')
                ->whereBetween('transaction_ts', [$monthStart, $monthEnd])
                ->sum('amount_collected');

            $totalLoss = DB::table('cash_ledger')
                ->whereIn('payment_status', ['failed', 'refunded'])
                ->whereBetween('transaction_ts', [$monthStart, $monthEnd])
                ->sum('amount_collected');

            $labels[]  = $month->format('M Y');
            $revenue[] = round((float) $totalRevenue, 2);
            $loss[]    = round((float) $totalLoss, 2);
        }

        return [
            'months'   => $months,
            'labels'   => $labels,
            'revenue'  => $revenue,
            'loss'     => $loss,
            'currency' => 'EGP',
        ];
    }

    public function getActiveFleetData(string $date): array
    {
        $routes = Route::with(['driver.user', 'stops.order'])
            ->where('status', 'Active')
            ->whereDate('scheduled_start_time', $date)
            ->get()
            ->unique('driver_id');

        return $routes->map(function ($route) {
            $totalStops = $route->total_stops ?: $route->stops->count();
            $completedStops = $route->stops->whereNotNull('actual_arrival_time')->count();
            
            $progress = $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0;
            
            // Determine Location
            $nextStop = $route->stops->whereNull('actual_arrival_time')->first();
            $location = 'En Route';
            if ($nextStop && $nextStop->order && $nextStop->order->Area) {
                $location = $nextStop->order->Area;
            } elseif ($route->route_name) {
                $location = $route->route_name;
            }

            // ETA formatting
            $eta = $route->scheduled_end_time 
                ? $route->scheduled_end_time->format('h:i A') 
                : '--:--';

            return [
                'route_id' => 'R-' . str_pad($route->route_id, 3, '0', STR_PAD_LEFT),
                'location' => $location,
                'driver'   => $route->driver && $route->driver->user ? $route->driver->user->name : 'Unassigned',
                'progress' => $progress,
                'eta'      => $eta,
            ];
        })->toArray();
    }

    /**
     * تقرير لوحة قيادة العمليات اليومية
     * Aggregates data from Routes, Orders, Alerts, and Fuel logs
     * to provide the frontend dashboard with summary metrics.
     *
     * @param string $date  YYYY-MM-DD
     * @return array  dashboard summary metrics
     */
    public function getDailyDashboard(string $date): array
    {

        try {
            
            $today     = Carbon::parse($date)->startOfDay();
            $yesterday = Carbon::parse($date)->copy()->subDay();

            // ── 1. Active Routes ─────────────────────────────────────────────────
            $activeRoutes    = Route::query()
                ->where('status', 'Active')
                ->whereDate('scheduled_start_time', $date)
                ->count();

            $yesterdayRoutes = Route::query()
                ->where('status', 'Active')
                ->whereDate('scheduled_start_time', $yesterday->toDateString())
                ->count();

            // ── 2. Orders Today ──────────────────────────────────────────────────
            $ordersToday     = Order::query()
                ->whereDate('Created_at', $date)
                ->count();

            $ordersYesterday = Order::query()
                ->whereDate('Created_at', $yesterday->toDateString())
                ->count();

            // ── 3. Open Alerts (unresolved incidents) ────────────────────────────
            $openAlerts = IncidentReport::query()
                ->open()
                ->whereDate('incident_ts', '<=', $date)
                ->count();

            // ── 4. Fuel Efficiency (km/L) ────────────────────────────────────────
            $fuelToday     = $this->calculateFuelEfficiency($date);
            $fuelYesterday = $this->calculateFuelEfficiency($yesterday->toDateString());

            // ── 5. Delivery Rate (%) ─────────────────────────────────────────────
            $deliveredToday    = Order::query()
                ->where('Status', 'Delivered')
                ->whereDate('DeliveredAt', $date)
                ->count();

            $deliveredYesterday = Order::query()
                ->where('Status', 'Delivered')
                ->whereDate('DeliveredAt', $yesterday->toDateString())
                ->count();

            $deliveryRate          = $ordersToday > 0 ? round(($deliveredToday / $ordersToday) * 100, 1) : 0.0;
            $deliveryRateYesterday = $ordersYesterday > 0 ? round(($deliveredYesterday / $ordersYesterday) * 100, 1) : 0.0;
            
            // ── Build Response ───────────────────────────────────────────────────
            return [
                'active_routes'   => [
                    'count'    => (string) $activeRoutes,
                    'change'   => $this->calculateChange((float) $activeRoutes, (float) $yesterdayRoutes),
                    'positive' => $this->isPositive((float) $activeRoutes, (float) $yesterdayRoutes),
                ],
                'orders_today'    => [
                    'count'    => (string) $ordersToday,
                    'change'   => $this->calculateChange((float) $ordersToday, (float) $ordersYesterday),
                    'positive' => $this->isPositive((float) $ordersToday, (float) $ordersYesterday),
                ],
                'open_alerts'     => [
                    'count'    => (string) $openAlerts,
                    'change'   => 'N/A',
                    'positive' => null,
                ],
                'fuel_efficiency' => [
                    'count'    => $fuelToday !== null ? $fuelToday . ' km/L' : 'N/A',
                    'change'   => ($fuelToday !== null && $fuelYesterday !== null)
                        ? $this->calculateChange($fuelToday, $fuelYesterday)
                        : 'N/A',
                    'positive' => ($fuelToday !== null && $fuelYesterday !== null)
                        ? $this->isPositive($fuelToday, $fuelYesterday)
                        : null,
                ],
                'delivery_rate'   => [
                    'count'    => $deliveryRate . '%',
                    'change'   => $this->calculateChange($deliveryRate, $deliveryRateYesterday),
                    'positive' => $this->isPositive($deliveryRate, $deliveryRateYesterday),
                ],
                'active_fleet_data' => $this->getActiveFleetData($date),
            ];

        } catch (\Exception $e) {
            Log::error($e);
            return array('message' => 'Server error');
        }
        
        }

    // ═══════════════════════════════════════════════════════════════════════════
    // Private Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Calculate percentage change between today and yesterday values.
     */
    private function calculateChange(float $current, float $previous): string
    {
        if ($previous == 0.0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = round((($current - $previous) / $previous) * 100, 1);
        $sign   = $change >= 0 ? '+' : '';

        return $sign . $change . '%';
    }

    /**
     * Determine if the change is positive (current >= previous).
     */
    private function isPositive(float $current, float $previous): ?bool
    {
        if ($current == $previous) {
            return null;
        }

        return $current > $previous;
    }

    /**
     * Calculate fuel efficiency (km/L) for a given date.
     * Uses fuel_audit_logs: total distance driven / total fuel consumed.
     */
    private function calculateFuelEfficiency(string $date): ?float
    {
        $logs = FuelAuditLog::whereDate('log_ts', $date)->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $totalFuel = $logs->sum('fuel_quantity');

        if ($totalFuel == 0) {
            return null;
        }

        // Calculate distance from routes that have actual distance recorded on this date
        $totalDistance = Route::whereIn('status', ['Completed', 'Active'])
            ->whereDate('scheduled_start_time', $date)
            ->whereNotNull('total_distance')
            ->sum('total_distance');

        if ($totalDistance == 0) {
            return null;
        }

        return round($totalDistance / $totalFuel, 1);
    }
}
