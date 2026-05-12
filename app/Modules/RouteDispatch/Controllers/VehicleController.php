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
use App\Modules\RouteDispatch\Requests\VehicleRequest;
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
            $perPage = (int) request()->query('per_page', 0);

            if ($perPage > 0) {
                $paginated = $this->vehicleService->getAllVehicles($perPage);
                $mapped = collect($paginated->items())->map(fn($v) => $this->mapVehicle($v));

                return response()->json([
                    'success'      => true,
                    'data'         => $mapped,
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ], 200);
            }

            $vehicles = \App\Modules\RouteDispatch\Models\Vehicle::all();
            $mapped = $vehicles->map(fn($v) => $this->mapVehicle($v))->values();

            return response()->json([
                'success' => true,
                'data'    => $mapped,
                'total'   => $mapped->count(),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Maps a Vehicle Eloquent model to the standard API response shape.
     */
    private function mapVehicle(\App\Modules\RouteDispatch\Models\Vehicle $vehicle): array
    {
        return [
            'vehicle_id'   => (int)    $vehicle->vehicle_id,
            'model'        => (string) ($vehicle->VehicleModel   ?? ''),
            'type'         => (string) ($vehicle->VehicleType    ?? ''),
            'plate_number' => (string) ($vehicle->VehicleLicense ?? ''),
            'status'       => (string) ($vehicle->Status         ?? ''),
            'odometer'     => (string) ($vehicle->Current_odometer ?? '0'),
            'max_weight'   => (string) ($vehicle->MaxWeightCapacity ?? '0'),
            'max_volume'   => (string) ($vehicle->MaxVolume      ?? '0'),
            'market_value' => (int)    ($vehicle->MarketValue    ?? 0),
        ];
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

    /** POST /api/v1/dispatch/vehicles */
    public function store(VehicleRequest $request): JsonResponse
    {
        // TODO: Create vehicle → 201
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