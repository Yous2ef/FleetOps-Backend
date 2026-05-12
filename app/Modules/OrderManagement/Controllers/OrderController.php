<?php

/**
 * @file: OrderController.php
 * @description: متحكم الطلبات - CRUD والاستيراد وتحديث الحالة
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OrderManagement\Services\OrderService;
use App\Modules\OrderManagement\Services\OrderImportService;
use App\Modules\OrderManagement\Requests\OrderRequest;
use App\Modules\OrderManagement\Requests\BulkImportRequest;
use App\Modules\OrderManagement\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected OrderImportService $importService;

    public function __construct(OrderService $orderService, OrderImportService $importService)
    {
        $this->orderService  = $orderService;
        $this->importService = $importService;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $paginator = $this->orderService->getAllOrders((int) $perPage);

        return response()->json([
            'success' => true,
            'data'    => OrderResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id);
            return response()->json([
                'success' => true,
                'data'    => new OrderResource($order),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
    }

    public function store(OrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());
            return response()->json([
                'success' => true,
                'data'    => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /** PUT /api/v1/orders/{id} */
    public function update(int $id, OrderRequest $request): JsonResponse
    {
        // TODO: Update order (only if pending)
    }

    /** DELETE /api/v1/orders/{id} */
    public function destroy(int $id): JsonResponse
    {
        // TODO: Soft delete order (only if pending)
    }

    /** GET /api/v1/orders/{status} */
    public function getByStatus(string $status): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->orderService->getOrdersByStatus($status)]);
    }


    /**
     * تحديث حالة الطلب (State Machine)
     * PATCH /api/v1/orders/{id}/status
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'status'         => 'required|in:pending,assigned,in_transit,out_for_delivery,delivered,returned,failed',
            'failure_reason' => 'required_if:status,failed|string|max:500',
        ]);

        try {
            $order = $this->orderService->updateOrderStatus($id, $request->status, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الطلب بنجاح',
                'data'    => $order,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * التحقق من QR Code (fn17)
     * POST /api/v1/orders/{id}/verify-qr
     */
    public function verifyQr(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        try {
            $this->orderService->verifyQrCode($id, $request->qr_code);

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من رمز QR بنجاح',
                'data'    => ['order_id' => $id, 'verified' => true],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => ['order_id' => $id, 'verified' => false],
            ], 422);
        }
    }

    /**
     * بدء سير عمل الإرجاع (fn21 - RTB)
     * POST /api/v1/orders/{id}/return
     */
    public function initiateReturn(int $id, Request $request): JsonResponse
    {
        // TODO: Validate failure_reason field
        // $order = $this->orderService->initiateReturn($id, $request->failure_reason)
        // return response with returned order
    }

    /**
     * استيراد طلبات جماعية من CSV/XML (fn39)
     * POST /api/v1/orders/import
     */
    public function bulkImport(BulkImportRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->importOrders($request->file('file'), $request->format);
            return response()->json(['success' => true, 'data' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /** GET /api/v1/orders/route/{routeId} */
    public function routeOrders(int $routeId): JsonResponse
    {
        try {
            $orders = $this->orderService->getRouteOrders($routeId);

            return response()->json([
                'success' => true,
                'message' => 'Orders for route retrieved successfully.',
                'data'    => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving orders for route: ' . $e->getMessage(),
            ], 400);
        }
    }

    /** GET /api/v1/orders/driver/{driverId} */
    public function driverOrders(int $driverId): JsonResponse
    {
        try {
            $orders = $this->orderService->getDriverOrders($driverId);

            return response()->json([
                'success' => true,
                'message' => 'Orders for driver retrieved successfully.',
                'data'    => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving orders for driver: ' . $e->getMessage(),
            ], 400);
        }
    }

    /** GET /api/v1/orders/cash */
        /** GET /api/v1/orders/cash/{driverId} */
    public function driverCashOrders(int $driverId): JsonResponse
    {
        // 1. Validate that the driver exists using the model (since the ID is in the URL)
        $driver = \App\Modules\AuthIdentity\Models\Driver::find($driverId);
        
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found'
            ], 404);
        }

        try {
            // 2. Fetch the orders
            $orders = $this->orderService->getCashOrdersForDriver($driverId);

            return response()->json([
                'success' => true,
                'message' => 'Cash orders retrieved successfully.',
                'data'    => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

}
