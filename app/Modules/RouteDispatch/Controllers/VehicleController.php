<?php

/**
 * @file: VehicleController.php
 * @description: متحكم المركبات - CRUD وإدارة الحالة والإتاحة
 * @module: RouteDispatch
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\RouteDispatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RouteDispatch\Services\VehicleService;
use App\Modules\RouteDispatch\Models\Vehicle;
use App\Modules\RouteDispatch\Resources\VehicleResource;
use App\Modules\RouteDispatch\Requests\VehicleRequest;
use App\Modules\RouteDispatch\Requests\StoreVehicleRequest;
use App\Modules\RouteDispatch\Requests\UpdateVehicleRequest;
use App\Modules\RouteDispatch\Resources\VehicleDetailResource;
use Exception;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    protected VehicleService $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /** GET /api/v1/dispatch/vehicles */
    public function index(): JsonResponse
    {
        try {
            $vehicles = Vehicle::all();
            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vehicles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** GET /api/v1/dispatch/vehicles/{id} */
    public function show(int $id): JsonResponse
    {
        try {
            $vehicle = $this->vehicleService->getVehicleById($id);

            return response()->json([
                'success' => true,
                'data'    => $vehicle,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /** POST /api/v1/dispatch/vehicles  (generic CRUD — reserved for future use) */
    public function store(VehicleRequest $request): JsonResponse
    {
        // Delegated to storeFleetVehicle for the fleet screen.
        // This stub remains to satisfy the existing route registration.
        return $this->storeFleetVehicle($request);
    }

    /** PUT /api/v1/dispatch/vehicles/{id} */
    public function update(int $id, VehicleRequest $request): JsonResponse
    {
        // TODO: Update vehicle
    }

    /** DELETE /api/v1/dispatch/vehicles/{id} */
    public function destroy(int $id): JsonResponse
    {
        // TODO: Soft delete vehicle (check no active routes)
    }

    /**
     * جلب المركبات المتاحة للتوزيع
     * GET /api/v1/dispatch/vehicles/available
     */
    public function available(): JsonResponse
    {
        try {
            $vehicles = $this->vehicleService->getAvailableVehicles();

            return response()->json([
                'success' => true,
                'data' => $vehicles,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->getTrace(),
            ], 500);
        }
    }

    /**
     * قفل مركبة من التوزيع (fn25 / MT-04)
     * POST /api/v1/dispatch/vehicles/{id}/lock
     */
    public function lock(int $id): JsonResponse
    {
        // TODO: $this->vehicleService->lockVehicle($id)
        // return success response
    }

    /**
     * تحرير مركبة بعد الصيانة
     * POST /api/v1/dispatch/vehicles/{id}/unlock
     */
    public function unlock(int $id): JsonResponse
    {
        // TODO: $this->vehicleService->unlockVehicle($id)
        // return success response
    }

    // =========================================================================
    // Fleet Management Screen — Added for frontend Fleet & Drivers screens
    // =========================================================================

    /**
     * إضافة مركبة جديدة عبر شاشة Fleet Management
     * POST /api/v1/dispatch/fleet/vehicles
     *
     * Accepts validated snake_case input, maps to SQL Server column names,
     * and returns the full VehicleDetailResource so the frontend table row
     * receives the same shape as the detail modal.
     *
     * Body (JSON):
     *   plate        (string, required, unique) — e.g. "TRK-099"
     *   type         (string, required)         — "light"|"heavy"|"refrigerated"
     *   max_weight   (numeric, required)        — kg
     *   max_volume   (numeric, required)        — m³
     *   odometer     (numeric, required)        — km
     *   market_value (numeric, required)        — SAR
     *   make_model   (string, optional)         — free-text "Toyota Hilux"
     */
    public function storeFleetVehicle(StoreVehicleRequest $request): JsonResponse
    {
        try {
            $vehicle  = $this->vehicleService->createFleetVehicle($request->validated());
            $resource = new VehicleDetailResource($vehicle);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle added successfully.',
                'data'    => $resource->toArray($request),
            ], 201);

        } catch (Exception $e) {
            $statusCode = (int) $e->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
                'errors'  => [],
            ], $statusCode);
        }
    }

    /**
     * تحديث مركبة عبر شاشة Fleet Management
     * PUT/PATCH /api/v1/dispatch/fleet/vehicles/{id}
     */
    public function updateFleetVehicle(int $id, UpdateVehicleRequest $request): JsonResponse
    {
        try {
            $vehicle  = $this->vehicleService->updateFleetVehicle($id, $request->validated());
            $resource = new VehicleDetailResource($vehicle);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully.',
                'data'    => $resource->toArray($request),
            ], 200);

        } catch (Exception $e) {
            $statusCode = (int) $e->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
                'errors'  => [],
            ], $statusCode);
        }
    }

    /**
     * جلب قائمة الأسطول الكاملة لشاشة Fleet Management
     * GET /api/v1/dispatch/fleet/vehicles
     *
     * Response shape:
     * {
     *   "success": true,
     *   "message": "...",
     *   "data": [ { id, plate, type, max_weight, max_volume,
     *               odometer, status, mechanic,
     *               market_value, last_service }, ... ]
     * }
     */
    public function fleetVehicles(): JsonResponse
    {
        try {
            $vehicles = $this->vehicleService->getFleetVehicles();

            return response()->json([
                'success' => true,
                'message' => 'Fleet vehicles retrieved successfully.',
                'data'    => $vehicles,   // always an array — never a bare object
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],           // keep data key present for frontend stability
                'errors'  => $e->getTrace(),
            ], 500);
        }
    }

    /**
     * جلب تفاصيل مركبة واحدة مع بيانات الرسوم البيانية (Charts)
     * GET /api/v1/dispatch/fleet/vehicles/{id}
     *
     * Returns the full vehicle detail payload consumed by the Fleet Management
     * detail modal, including:
     *   – All list fields (id, plate, type, status, odometer_display …)
     *   – odometer_history  : int[]   (6 monthly readings, Chart.js line data)
     *   – fuel_efficiency_history : float[]  (6 km/L readings, Chart.js bar data)
     *   – chart_months      : string[] (e.g. ['Dec','Jan','Feb','Mar','Apr','May'])
     *   – insurance_expiry  : string   (e.g. "May 01, 2027")
     *   – inspection_expiry : string   (e.g. "Nov 01, 2026")
     *   – last_service      : string   (Y-m-d, never null)
     */
    public function fleetVehicleDetail(int $id): JsonResponse
    {
        try {
            $vehicle  = $this->vehicleService->getFleetVehicleDetail($id);
            $resource = new VehicleDetailResource($vehicle);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle detail retrieved successfully.',
                'data'    => $resource->toArray(request()),
            ], 200);
        } catch (Exception $e) {
            $statusCode = (int) $e->getCode();
            // getCode() is 0 when not explicitly set — default to 404 for not-found
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
            ], $statusCode);
        }
    }

    /**
     * جلب قائمة السائقين الكاملة لشاشة Drivers Management
     * GET /api/v1/dispatch/fleet/drivers
     *
     * Response shape:
     * {
     *   "success": true,
     *   "message": "...",
     *   "data": [ { driver_id, name, initials, status, score, shift,
     *               license_type, license_no,
     *               stats: { deliveries, success_rate, on_time_rate, avg_time },
     *               current_vehicle, current_route }, ... ]
     * }
     */
    public function fleetDrivers(): JsonResponse
    {
        try {
            $drivers = $this->vehicleService->getFleetDrivers();

            return response()->json([
                'success' => true,
                'message' => 'Fleet drivers retrieved successfully.',
                'data'    => $drivers,    // always an array — never a bare object
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],           // keep data key present for frontend stability
                'errors'  => $e->getTrace(),
            ], 500);
        }
    }

    /**
     * جلب قائمة المركبات لشاشة الصيانة
     * GET /api/v1/maintenance/vehicles
     */
    public function maintenanceVehicles(): JsonResponse
    {
        try {
            $vehicles = $this->vehicleService->getMaintenanceVehicles();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance vehicles retrieved successfully.',
                'data'    => $vehicles,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
                'errors'  => $e->getTrace(),
            ], 500);
        }
    }
}