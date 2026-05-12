<?php

/**
 * @file: DispatchController.php
 * @description: متحكم التعيين - ربط السائقين بالمسارات ومعالجة الطوارئ (RD-01 / fn01)
 * @module: RouteDispatch
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\RouteDispatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RouteDispatch\Services\DispatchService;
use App\Modules\RouteDispatch\Services\RouteOptimizationService;
use App\Modules\RouteDispatch\Requests\DispatchRequest;
use App\Modules\RouteDispatch\Requests\CapacityCheckRequest;
use App\Modules\RouteDispatch\Requests\ClusterOrdersRequest;
use App\Modules\RouteDispatch\Requests\PriorityScoreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\RouteDispatch\Models\Route;
use App\Modules\RealtimeTracking\Models\GpsPing;

class DispatchController extends Controller
{
    protected DispatchService $dispatchService;
    protected RouteOptimizationService $optimizationService;

    public function __construct(
        DispatchService $dispatchService,
        RouteOptimizationService $optimizationService
    ) {
        $this->dispatchService     = $dispatchService;
        $this->optimizationService = $optimizationService;
    }

    /**
     * Unified endpoint for Live Monitoring (Routes + Locations)
     * GET /api/v1/dispatch/live-snapshot
     */
    public function liveSnapshot(): JsonResponse
    {
        // 1. Get routes that are actually in motion.
        // Planned routes/orders are still being prepared and should not appear on the live map.
        $routes = Route::with(['driver.user', 'vehicle', 'stops'])
            ->whereIn('status', ['Active', 'InProgress', 'In_progress'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Extract driver IDs to fetch their last known locations in one query
        $driverIds = $routes->pluck('driver_id')->filter()->unique()->toArray();

        // 3. Fetch latest GPS pings for these drivers
        $latestPings = [];
        if (!empty($driverIds)) {
            // Fetch all recent pings for the drivers ordered by time, then take the first per driver
            $pings = GpsPing::whereIn('driver_id', $driverIds)
                ->orderBy('recorded_at', 'desc')
                ->get();
                
            $latestPings = $pings->unique('driver_id')->keyBy('driver_id');
        }

        // 4. Merge location data into routes
        $routesData = $routes->map(function ($route) use ($latestPings) {
            $routeArray = $route->toArray();
            $ping = $latestPings->get($route->driver_id);
            $routeArray['location'] = $ping ? $ping->toArray() : null;
            return $routeArray;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'routes' => $routesData
            ]
        ]);
    }

    /**
     * تعيين سائق ومركبة لمسار (RD-01 / fn01)
     * POST /api/v1/dispatch/assign
     */
    public function assign(DispatchRequest $request): JsonResponse
    {
        // TODO: Assign driver + vehicle to route
        // $route = $this->dispatchService->assignDriverAndVehicle(
        //     $request->route_id, $request->driver_id, $request->vehicle_id
        // )
        // return response()->json(['success' => true, 'message' => 'تم التعيين بنجاح', 'data' => $route], 200)
        // Catch Exception (license mismatch, vehicle unavailable, driver busy)
    }

    /**
     * التحقق من إتاحة سائق
     * GET /api/v1/dispatch/drivers/{driverId}/availability
     */
    public function driverAvailability(int $driverId): JsonResponse
    {
        // TODO: Check driver availability
        // $isAvailable = $this->dispatchService->isDriverAvailable($driverId)
        // return response()->json(['success' => true, 'data' => ['driver_id' => $driverId, 'is_available' => $isAvailable]])
    }

    /**
     * التحقق من تطابق الرخصة مع المركبة
     * GET /api/v1/dispatch/license-check?vehicle_type=heavy&license_type=light
     */
    public function licenseCheck(Request $request): JsonResponse
    {
        // TODO: Check license compatibility
        // 1. Validate: vehicle_type (required), license_type (required)
        // $compatible = $this->dispatchService->isLicenseCompatible($request->vehicle_type, $request->license_type)
        // return response()->json(['success' => true, 'data' => ['compatible' => $compatible]])
    }

    /**
     * إعادة توزيع الطلبات عند تعطل مركبة (RD-07 / fn04)
     * POST /api/v1/dispatch/redistribute
     */
    public function redistribute(Request $request): JsonResponse
    {
        // TODO: Redistribute broken route
        // 1. Validate: broken_route_id (required), available_route_ids (required|array)
        // $result = $this->dispatchService->redistributeOnBreakdown(
        //     $request->broken_route_id, $request->available_route_ids
        // )
        // return response()->json(['success' => true, 'message' => 'تم إعادة التوزيع', 'data' => $result])
    }

    /**
     * التحقق من سعة التحميل (RD-03 / fn03)
     * POST /api/v1/dispatch/capacity-check
     */
    public function capacityCheck(CapacityCheckRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $result = $this->optimizationService->checkLoadCapacity($validatedData['data']);
        $validClusters = array_values(array_filter($result, static fn (array $cluster): bool => (bool) ($cluster['valid'] ?? false)));
        $invalidClusters = array_values(array_filter($result, static fn (array $cluster): bool => !((bool) ($cluster['valid'] ?? false))));
        $allValid = count($invalidClusters) === 0;

        return response()->json([
            'success' => true,
            'message' => $allValid
                ? 'All clusters fit within the assigned vehicle capacities.'
                : 'Some clusters exceed vehicle capacity or use an invalid vehicle.',
            'summary' => [
                'all_valid' => $allValid,
                'total_clusters' => count($result),
                'valid_clusters' => count($validClusters),
                'invalid_clusters' => count($invalidClusters),
            ],
            'data' => $result,
            'invalid_clusters' => $invalidClusters,
        ]);
    }

    /**
     * التجميع الجغرافي للطلبات (RD-02 / fn02)
     * POST /api/v1/dispatch/cluster-orders
     */
    public function clusterOrders(ClusterOrdersRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $clusters = $this->optimizationService->clusterOrders($validatedData['order_ids']);

        return response()->json(['success' => true, 'data' => $clusters]);
    }

    /**
     * حساب درجة أولوية الطلبات (POST /api/v1/dispatch/priority-score)
     *  score من 0 إلى 100 لكل طلب.
     */
    public function priorityScore(PriorityScoreRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $orderIds = $validatedData['order_ids'] ?? null;

        if (!is_array($orderIds) || count($orderIds) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'The order_ids field is required and must be a non-empty array.',
                'errors' => ['order_ids' => ['The order_ids field is required and must be a non-empty array.']],
            ], 422);
        }

        $scores = $this->dispatchService->calculatePriorityScores($orderIds);

        return response()->json(['success' => true, 'data' => $scores]);
    }
}
