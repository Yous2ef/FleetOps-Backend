<?php

/**
 * @file: NotificationRepository.php
 * @description: مستودع بيانات الإشعارات - Notification Service
 * @module: Notification
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Notification\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Notification\Models\Notification;

class NotificationRepository extends BaseRepository
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    public function getForUser(int $userId, int $perPage = 15)
    {
        return $this->model->forUser($userId)->latest()->paginate($perPage);
    }

    public function findByDedupKey(string $dedupKey): ?Notification
    {
        // TODO: return $this->model->where('dedup_key', $dedupKey)->where('created_at', '>=', now()->subMinutes(5))->first();
    }

    public function markAsSent(int $notificationId): bool
    {
        // TODO: return $this->update($notificationId, ['status' => 'sent', 'sent_at' => now()]);
    }

    public function markAsDelivered(int $notificationId): bool
    {
        // TODO: return $this->update($notificationId, ['status' => 'delivered', 'delivered_at' => now()]);
    }

    public function markAsFailed(int $notificationId, string $reason, int $retryCount): bool
    {
        // TODO: return $this->update($notificationId, ['status' => 'failed', 'failed_reason' => $reason, 'retry_count' => $retryCount]);
    }

    public function getFailedForRetry(): \Illuminate\Database\Eloquent\Collection
    {
        // TODO: return $this->model->failed()->where('retry_count', '<', 3)->get();
    }
}
