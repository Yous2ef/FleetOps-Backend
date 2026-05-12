<?php

/**
 * @file: DriverPerformanceRepository.php
 * @description: مستودع بيانات نقاط أداء السائقين - Reporting & Analytics Service (AN-02)
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\ReportingAnalytics\Models\DriverPerformanceScore;

class DriverPerformanceRepository extends BaseRepository
{
    public function __construct(DriverPerformanceScore $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب تاريخ أداء سائق معين
     * @param int $driverId
     * @param int $perPage
     */
    public function getForDriver(int $driverId, int $perPage = 12)
    {
        return $this->model->where('driver_id', $driverId)
            ->orderByDesc('period_start')
            ->paginate($perPage);
    }

    /**
     * تصنيف السائقين بناءً على النقاط (Leaderboard - AN-05)
     * @param string $periodStart
     * @param string $periodEnd
     * @return array
     */
    public function getLeaderboard(string $periodStart, string $periodEnd): array
    {
        $performances = \Illuminate\Support\Facades\DB::table('driver_performance')
            ->join('drivers', 'driver_performance.driver_id', '=', 'drivers.driver_id')
            ->join('users', 'drivers.driver_id', '=', 'users.user_id')
            ->where('driver_performance.period_start', '>=', $periodStart)
            ->where('driver_performance.period_end', '<=', $periodEnd)
            ->select(
                'driver_performance.driver_id',
                'users.name as Driver',
                \Illuminate\Support\Facades\DB::raw('AVG(driver_performance.on_time_delivery_pct) as SpeedPct'),
                \Illuminate\Support\Facades\DB::raw('AVG(driver_performance.fuel_per_100km) as FuelConsumption'),
                \Illuminate\Support\Facades\DB::raw('AVG(driver_performance.avg_customer_rating) as Rating'),
                'drivers.score as Score'
            )
            ->groupBy('driver_performance.driver_id', 'users.name', 'drivers.score')
            ->orderByDesc('drivers.score')
            ->get();

        $leaderboard = [];
        $rank = 1;

        foreach ($performances as $perf) {
            // Calculate a normalized Fuel% score based on fuel consumption (assuming 8L/100km is 100%, 20L/100km is 0%)
            $fuelScore = max(0, min(100, 100 - (($perf->FuelConsumption - 8) * (100 / 12))));

            $leaderboard[] = [
                'Rank'   => $rank++,
                'Driver' => $perf->Driver,
                'Speed%' => round((float) $perf->SpeedPct, 1) . '%',
                'Fuel%'  => round($fuelScore, 1) . '%',
                'Rating' => round((float) $perf->Rating, 1),
                'Score'  => (int) $perf->Score,
            ];
        }

        return $leaderboard;
    }

    /**
     * حفظ أو تحديث نقاط السائق للفترة
     * @param int $driverId
     * @param string $periodStart
     * @param string $periodEnd
     * @param array $scoreData
     * @return DriverPerformanceScore
     */
    public function upsertScore(int $driverId, string $periodStart, string $periodEnd, array $scoreData): DriverPerformanceScore
    {
        return $this->model->updateOrCreate(
            [
                'driver_id' => $driverId, 
                'period_start' => $periodStart, 
                'period_end' => $periodEnd
            ],
            $scoreData
        );
    }
}
