<?php

/**
 * @file: KpiController.php
 * @description: متحكم مؤشرات الأداء والتحليلات - Reporting & Analytics Service
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ReportingAnalytics\Services\KpiService;
use App\Modules\ReportingAnalytics\Requests\KpiFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * جلب KPIs الأسطول للفترة المحددة
     * GET /api/v1/analytics/kpis
     */
    public function index(KpiFilterRequest $request): JsonResponse
    {
        try {
            $data = $this->kpiService->getAnalyticsKpis($request->input('range', '30d'));
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * نسبة التسليم في الموعد (AN-04 / fn41)
     * GET /api/v1/analytics/kpis/on-time-rate
     */
    public function onTimeRate(KpiFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->kpiService->calculateOnTimeRate(
                $request->period_start, 
                $request->period_end, 
                $request->driver_id
            );
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * نقاط أداء السائق (AN-02 / fn22)
     * GET /api/v1/analytics/kpis/driver-score/{driverId}
     */
    public function driverScore(int $driverId, KpiFilterRequest $request): JsonResponse
    {
        try {
            $score = $this->kpiService->calculateDriverPerformanceScore(
                $driverId, 
                $request->period_start, 
                $request->period_end
            );
            return response()->json(['success' => true, 'data' => $score]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تقرير CO2/الاستدامة (AN-03 / fn40)
     * GET /api/v1/analytics/kpis/co2-report?period=monthly
     */
    public function co2Report(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:monthly',
        ], [
            'period.in' => 'الفترة غير صالحة',
        ]);

        try {
            $period = $request->input('period', 'monthly');
            $report = $this->kpiService->generateCO2Report($period);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح',
                'data'    => $report,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * مؤشرات الأداء الرئيسية لصفحة التحليلات (analytics-kpis)
     * GET /api/v1/analytics/analytics-kpis?range=today|7d|30d
     */
    public function analyticsKpis(Request $request): JsonResponse
    {
        $request->validate([
            'range' => 'sometimes|string|in:today,7d,30d',
        ]);

        try {
            $range = $request->input('range', '30d');
            $data  = $this->kpiService->getAnalyticsKpis($range);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * توزيع حالة الأسطول (analytics-fleet-distribution)
     * GET /api/v1/analytics/analytics-fleet-distribution
     */
    public function fleetDistribution(): JsonResponse
    {
        try {
            $data = $this->kpiService->getFleetDistribution();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * مراجعة الوقود (analytics-fuel-audit / fn24)
     * GET /api/v1/analytics/analytics-fuel-audit?range=today|7d|30d (default: 30d)
     */
    public function fuelAudit(Request $request): JsonResponse
    {
        $request->validate([
            'range' => 'sometimes|string|in:today,7d,30d',
        ]);

        try {
            $range = $request->input('range', '30d');
            $data  = $this->kpiService->getFuelAudit($range);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * كشف الشذوذات (AN-07)
     * GET /api/v1/analytics/kpis/anomalies
     */
    public function anomalies(Request $request): JsonResponse
    {
        try {
            $date = $request->input('date', now()->toDateString());
            // Mock logic for now as detectAnomalies is not in service
            $anomalies = []; 
            return response()->json(['success' => true, 'data' => $anomalies]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
