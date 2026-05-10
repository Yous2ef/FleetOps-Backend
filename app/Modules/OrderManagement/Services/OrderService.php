<?php

/**
 * @file: OrderService.php
 * @description: خدمة إدارة دورة حياة الطلبات (State Machine) - Order Management Service
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Services;

use App\Modules\AuthIdentity\Models\Customer;
use App\Modules\AuthIdentity\Models\User;
use App\Modules\OrderManagement\Models\Order;
use App\Modules\OrderManagement\Repositories\OrderRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrderService
{
    protected OrderRepository $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getAllOrders(int $perPage = 15)
    {
        return $this->orderRepository->with(['customer.user', 'vehicle', 'driver.user'])->paginate($perPage);
    }

    public function getOrderById(int $id)
    {
        return $this->orderRepository->with(['customer.user', 'vehicle', 'driver.user'])->findOrFail($id);
    }


    /**
     * Get all orders filtered by status, with customer details eagerly loaded
     * @param string $status (e.g., 'Pending', 'InTransit', 'Delivered')
     * @return Collection Orders with customer names and details
     */
    public function getOrdersByStatus(string $status)
    { 
        return $this->orderRepository->findByStatus($status);
    }
    

    public function createOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            $email = $data['customer_email']
                ?: 'customer-' . Str::uuid() . '@fleetops.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'password' => Hash::make(Str::password(16)),
                    'name' => $data['customer_name'],
                    'phone_no' => $data['customer_phone'],
                    'role' => 'Customer',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $user->fill([
                'name' => $data['customer_name'],
                'phone_no' => $data['customer_phone'],
                'updated_at' => now(),
            ])->save();

            Customer::updateOrCreate(
                ['customer_id' => $user->user_id],
                [
                    'address' => $data['delivery_address'],
                    'delivery_preference' => $data['delivery_preference'] ?? null,
                    'created_at' => now(),
                ]
            );

            $priority = ($data['priority'] ?? 'normal') === 'express' ? 80 : 40;
            $type = ($data['priority'] ?? 'normal') === 'express' ? 'Express' : 'Normal';
            $price = ($data['payment_type'] ?? 'prepaid') === 'COD'
                ? (int) ($data['cod_amount'] ?? 0)
                : 0;

            $order = Order::create([
                'CustomerID(FK)' => $user->user_id,
                'Status' => 'Pending',
                'Priority' => $priority,
                'Type' => $type,
                'Price' => $price,
                'digital_signature' => strtoupper(Str::random(10)),
                'Delivery_preference' => $data['delivery_preference'] ?? null,
                'Payment_method' => $data['payment_type'] === 'COD' ? 'COD' : 'Prepaid',
                'Created_at' => now(),
                'UpdatedAt' => now(),
                'Perishable' => false,
                'Weight' => (int) $data['weight_kg'],
                'Volume' => isset($data['volume_m3']) ? (int) $data['volume_m3'] : null,
                'DeliveryTimeWindow' => null,
                'Longitude' => $data['lng'],
                'Latitude' => $data['lat'],
                'Area' => $data['delivery_address'],
            ]);

            $order->LiveTrackingLink = 'http://fleetops.com/track/' . $order->OrderID;
            $order->save();

            return $order->load(['customer.user', 'vehicle', 'driver.user']);
        });
    }

    public function updateOrder(int $id, array $data)
    {
        // TODO: Update order (only if pending status)
    }

    public function deleteOrder(int $id): bool
    {
        // TODO: Soft delete (only if pending)
    }

    /**
     * تحديث حالة الطلب (State Machine - OM-08)
     * @param int $orderId
     * @param string $newStatus  (in_transit | delivered | returned | failed)
     * @param array $extraData   (failure_reason, failure_reason_code, etc.)
     * @return mixed
     * @throws Exception
     */
    public function updateOrderStatus(int $orderId, string $newStatus, array $extraData = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $newStatus, $extraData) {
            return $this->orderRepository->updateStatus($orderId, $newStatus, $extraData);
        });
    }

    /**
     * التحقق من QR Code عند التسليم (fn17 / OM-04)
     * @param int $orderId
     * @param string $scannedQr
     * @return bool
     * @throws Exception
     */
    public function verifyQrCode(int $orderId, string $scannedQr): bool
    {
        $order = $this->orderRepository->findByIdOrFail($orderId);

        // Only allow QR verification for orders that are out for active delivery
        $deliverableStatuses = ['InTransit', 'Out for Delivery'];
        if (!in_array($order->Status, $deliverableStatuses)) {
            throw new Exception(
                "لا يمكن التحقق من QR Code للطلب بحالة: [{$order->Status}]. " .
                "الحالات المسموحة: " . implode(', ', $deliverableStatuses)
            );
        }

        // QR codes encode the OrderID (matches LiveTrackingLink pattern: /track/{OrderID})
        if ((string) $order->OrderID !== trim($scannedQr)) {
            throw new Exception("رمز QR غير صحيح. يرجى إعادة المسح.");
        }

        return true;
    }

    /**
     * تفعيل سير عمل الإرجاع (RTB - fn21 / OM-07)
     * @param int $orderId
     * @param string $failureReason
     * @return mixed
     */
    public function initiateReturn(int $orderId, string $failureReason)
    {
        // TODO: RTB Workflow
        // 1. Update order status to 'returned'
        // 2. Set failure_reason
        // 3. Increment retry_count
        // 4. Schedule retry if retry_count < 3
        // 5. Fire event: OrderReturned
        // 6. Return updated order
    }

    public function getRouteOrders(int $routeId)
    {
        return $this->orderRepository->getForRoute($routeId);
    }

    public function getDriverOrders(int $driverId)
    {
        return $this->orderRepository->getForDriver($driverId);
    }

    /**
     * Get cash orders for a specific driver
     * @param int $driverId
     * @return Collection
     */
    public function getCashOrdersForDriver(int $driverId)
    {
        return $this->orderRepository->cashOrders($driverId);
    }
}
