<?php

/**
 * @file: ProofOfDeliveryRepository.php
 * @description: مستودع بيانات إثبات التسليم (POD) - Order Management Service
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\OrderManagement\Models\ProofOfDelivery;

class ProofOfDeliveryRepository extends BaseRepository
{
    public function __construct(ProofOfDelivery $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب إثبات تسليم طلب معين
     * @param int $orderId
     * @return ProofOfDelivery|null
     */
    public function findByOrderId(int $orderId): ?ProofOfDelivery
    {
        return $this->model->where('order_id', $orderId)->first();
    }

    /**
     * جلب كل إثباتات التسليم لسائق معين في يوم محدد
     * @param int $driverId
     * @param string $date  YYYY-MM-DD
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForDriverOnDate(int $driverId, string $date)
    {
        return $this->model
            ->where('driver_id', $driverId)
            ->whereDate('delivered_at', $date)
            ->get();
    }

    public function storePOD(array $data)
    {
        return $this->model->create($data);
    }   
}

