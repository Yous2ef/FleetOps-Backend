<?php

namespace App\Modules\RouteDispatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuthIdentity\Models\Driver;
use App\Modules\RouteDispatch\Requests\StoreDriverRequest;
use App\Modules\RouteDispatch\Resources\DriverResource;
use Illuminate\Http\JsonResponse;
use Exception;

class DriverController extends Controller
{
    /**
     * جلب قائمة السائقين الكاملة لشاشة Drivers Management
     * GET /api/v1/dispatch/fleet/drivers
     */
    public function index(): JsonResponse
    {
        try {
            // Eager load 'user' and 'vehicle' relationships
            $drivers = Driver::with(['user', 'vehicle'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Fleet drivers retrieved successfully.',
                'data'    => DriverResource::collection($drivers),
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

    /**
     * إضافة سائق جديد
     * POST /api/v1/dispatch/fleet/drivers
     */
    public function store(StoreDriverRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Create the driver
            $driver = Driver::create([
                'driver_id'    => $data['user_id'], // PK is user_id
                'license_no'   => $data['license_no'],
                'license_type' => $data['license_type'],
                'vehicle_id'   => $data['vehicle_id'] ?? null,
                'status'       => 'Available',
                'score'        => 100,
            ]);

            // Eager load relationships for the resource
            $driver->load(['user', 'vehicle']);

            return response()->json([
                'success' => true,
                'message' => 'Driver added successfully.',
                'data'    => new DriverResource($driver),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
                'errors'  => $e->getTrace(),
            ], 500);
        }
    }

    /**
     * جلب تفاصيل سائق معين
     * GET /api/v1/dispatch/fleet/drivers/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $driver = Driver::with(['user', 'vehicle'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Driver retrieved successfully.',
                'data'    => new DriverResource($driver),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found.',
                'data'    => [],
            ], 404);
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
