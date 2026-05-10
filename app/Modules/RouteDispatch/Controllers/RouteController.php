<?php

/**
 * @file: RouteController.php
 * @description: متحكم المسارات - CRUD وبدء/إنهاء/تحسين المسارات
 * @module: RouteDispatch
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\RouteDispatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RouteDispatch\Services\RouteService;
use App\Modules\RouteDispatch\Services\RouteOptimizationService;
use App\Modules\RouteDispatch\Requests\RouteRequest;
use App\Modules\RouteDispatch\Requests\OptimizeRouteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    protected RouteService $routeService;
    protected RouteOptimizationService $optimizationService;

    public function __construct(RouteService $routeService, RouteOptimizationService $optimizationService)
    {
        $this->routeService        = $routeService;
        $this->optimizationService = $optimizationService;
    }

    /** GET /api/v1/dispatch/routes */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $routes = $this->routeService->getAllRoutes($perPage);
            return response()->json(['success' => true, 'data' => $routes]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('RouteController::index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /api/v1/dispatch/routes/{id} */
    public function show(int $id): JsonResponse
    {
        try {
            $route = $this->routeService->getRoute($id);
            return response()->json(['success' => true, 'data' => $route]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /** POST /api/v1/dispatch/routes */
    public function store(RouteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Get idempotency key from header to prevent duplicate requests
        $idempotencyKey = $request->header('Idempotency-Key');

        // If client did not provide an Idempotency-Key, compute a deterministic hash
        // from the normalized request payload to serve as an idempotency key.
        if (empty($idempotencyKey)) {
            // Normalize payload deterministically: recursively sort keys
            $normalize = function (&$data) use (&$normalize) {
                if (is_array($data)) {
                    // sort associative arrays by keys
                    $assoc = array_keys($data) !== range(0, count($data) - 1);
                    if ($assoc) {
                        ksort($data);
                    }

                    foreach ($data as &$value) {
                        $normalize($value);
                    }
                }
            };

            $payload = $validated;
            $normalize($payload);
            $normalizedJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $idempotencyKey = hash('sha256', $normalizedJson ?? json_encode($validated));
        }

        try {
            $route = $this->routeService->createRoute($validated, $idempotencyKey);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المسار بنجاح',
                'data' => $route
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** PUT /api/v1/dispatch/routes/{id} */
    public function update(int $id, RouteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $route = $this->routeService->updateRoute($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المسار بنجاح',
                'data' => $route
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** DELETE /api/v1/dispatch/routes/{id} */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->routeService->cancelRoute($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء المسار بنجاح',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بدء تنفيذ مسار
     * POST /api/v1/dispatch/routes/{id}/start
     */
    public function startRoute(int $id): JsonResponse
    {
        try {
            $route = $this->routeService->startRoute($id);

            return response()->json([
                'success' => true,
                'message' => 'تم بدء المسار بنجاح',
                'data' => $route
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنهاء مسار
     * POST /api/v1/dispatch/routes/{id}/complete
     */
    public function completeRoute(int $id): JsonResponse
    {
        try {
            $route = $this->routeService->completeRoute($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنهاء المسار بنجاح',
                'data' => $route
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحسين ترتيب المحطات (TSP - fn06)
     * POST /api/v1/dispatch/routes/optimize
     */
    public function optimizeRoute(OptimizeRouteRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            $clustersOut = $this->optimizationService->optimizeClusters($payload['clusters'], $payload['start_date'] ?? null);

            return response()->json([
                'success' => true,
                'data' => [
                    'clusters' => $clustersOut,
                ],
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json([
                'success' => false,
                'message' => 'Unable to optimize route clusters at this time.',
            ], 500);
        }
    }

    /**
     * إدراج طلب عاجل (fn07)
     * POST /api/v1/dispatch/routes/{id}/insert-urgent
     */
    public function insertUrgentOrder(int $id, Request $request): JsonResponse
    {
        // TODO: Validate order_id in request
        // $stops = $this->optimizationService->insertUrgentOrder($id, $request->order_id)
    }

    /**
     * انتقال المسار لسائق آخر (fn09)
     * POST /api/v1/dispatch/routes/{id}/shift-transition
     */
    public function shiftTransition(int $id, Request $request): JsonResponse
    {
        // TODO: Validate new_driver_id in request
        // $route = $this->routeService->shiftTransition($id, $request->new_driver_id)
    }

    /**
     * جلب مسارات سائق معين
     * GET /api/v1/dispatch/routes/driver/{driverId}
     */
    public function driverRoutes(int $driverId): JsonResponse
    {
        try {
            $routes = $this->routeService->getDriverRoutes($driverId);

            return response()->json([
                'success' => true,
                'message' => 'Driver routes retrieved successfully.',
                'data' => $routes
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
