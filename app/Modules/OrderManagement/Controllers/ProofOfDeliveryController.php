<?php

/**
 * @file: ProofOfDeliveryController.php
 * @description: متحكم إثبات التسليم (POD) - صورة وتوقيع (fn13/14)
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OrderManagement\Requests\ProofOfDeliveryRequest;
use App\Modules\OrderManagement\Services\ProofOfDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProofOfDeliveryController extends Controller
{
    protected ProofOfDeliveryService $podService;

    public function __construct(ProofOfDeliveryService $podService)
    {
        $this->podService = $podService;
    }

    /**
     * حفظ إثبات التسليم
     * POST /api/v1/orders/{orderId}/pod
     */
    public function store(int $orderId, ProofOfDeliveryRequest $request): JsonResponse
    {
        try {
            $this->podService->storePOD($orderId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Delivery proof saved successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error("POD Store Failure for Order {$orderId}: " . $e->getMessage(), [
                'exception' => $e,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving delivery proof.'
            ], 500);
        }
    }

    /**
     * عرض إثبات التسليم لطلب معين
     * GET /api/v1/orders/{orderId}/pod
     */
    public function show(int $orderId): JsonResponse
    {
        // TODO: Get POD for order
        // return POD record with URLs
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
