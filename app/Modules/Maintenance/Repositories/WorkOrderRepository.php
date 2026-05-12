<?php

/**
 * @file: WorkOrderRepository.php
 * @description: مستودع بيانات أوامر العمل - Maintenance Service
 * @module: Maintenance
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Maintenance\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Maintenance\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;

class WorkOrderRepository extends BaseRepository
{
    public function __construct(WorkOrder $model)
    {
        parent::__construct($model);
    }

    public function getForVehicle(int $vehicleId): Collection
    {
        return $this->model->with('vehicle')->forVehicle($vehicleId)->orderBy('opened_at', 'desc')->get();
    }

    public function getForMechanic(int $mechanicId): Collection
    {
        return $this->model->with('vehicle')->forMechanic($mechanicId)->orderBy('opened_at', 'desc')->get();
    }

    public function updateStatus(int $workOrderId, string $newStatus, array $timestamps = []): bool
    {
        // Map status to its corresponding timestamp field
        $statusTimestampMap = [
            'assigned'    => 'assigned_at',
            'in_progress' => 'started_at',
            'resolved'    => 'resolved_at',
            'closed'      => 'closed_at',
        ];

        if (isset($statusTimestampMap[$newStatus]) && !isset($timestamps[$statusTimestampMap[$newStatus]])) {
            $timestamps[$statusTimestampMap[$newStatus]] = now();
        }

        return $this->update($workOrderId, array_merge(['status' => $newStatus], $timestamps));
    }

    public function getOpenWorkOrders(): Collection
    {
        return $this->model->open()->with('vehicle')->get();
    }
}
