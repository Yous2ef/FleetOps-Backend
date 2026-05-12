<?php

/**
 * @file: ProofOfDeliveryService.php
 * @description: خدمة إثبات التسليم (POD) - صورة وتوقيع (fn13/14 / OM-05)
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Services;

use App\Modules\OrderManagement\Repositories\OrderRepository;
use App\Modules\OrderManagement\Repositories\ProofOfDeliveryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class ProofOfDeliveryService
{
    protected OrderRepository $orderRepository;
    protected ProofOfDeliveryRepository $podRepository;

    public function __construct(OrderRepository $orderRepository, ProofOfDeliveryRepository $podRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->podRepository = $podRepository;
    }

    /**
     * حفظ إثبات التسليم (fn13/14)
     * @param int $orderId
     * @param array $data (digital_signature, customer_name, geolocation, timestamp)
     * @return mixed ProofOfDelivery record
     * @throws Exception
     */
    public function storePOD(int $orderId, array $data)
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = $this->orderRepository->findById($orderId);

            if (!$order) {
                throw new Exception("Order not found.");
            }

            if ($order->Status === 'Delivered') {
                throw new Exception("Order is already delivered.");
            }

            // Extract geolocation data
            $lat = $data['geolocation']['lat'] ?? null;
            $lng = $data['geolocation']['lng'] ?? null;

            // POD data is stored on the order row itself
            $updatePayload = [
                'digital_signature' => $data['digital_signature'] ?? null,
                'Latitude'          => $lat,
                'Longitude'         => $lng,
                'DeliveredAt'       => $data['timestamp'] ?? now(),
                'Status'            => 'Delivered',
            ];

            // Note: customer_name is not currently in the orders table schema.
            // If it's required to be saved, a migration to add it would be needed.
            // For now, we update the existing supported fields.
            
            $this->orderRepository->update($orderId, $updatePayload);

            return $this->orderRepository->findById($orderId);
        });
    }
}

