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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository extends BaseRepository
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    /**
     * Get paginated notifications for a specific user, latest first.
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->forUser($userId)->latest()->paginate($perPage);
    }

    /**
     * Find a notification by its deduplication key within the last 5 minutes.
     * Returns null if no recent duplicate exists.
     *
     * @param string $dedupKey
     * @return Notification|null
     */
    public function findByDedupKey(string $dedupKey): ?Notification
    {
        return $this->model
            ->where('dedup_key', $dedupKey)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();
    }

    /**
     * Mark a notification as sent by setting status and sent_at timestamp.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markAsSent(int $notificationId): bool
    {
        return $this->update($notificationId, [
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark a notification as delivered by setting status and delivered_at timestamp.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markAsDelivered(int $notificationId): bool
    {
        return $this->update($notificationId, [
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark a notification as failed with the reason and incremented retry count.
     *
     * @param int    $notificationId
     * @param string $reason
     * @param int    $retryCount
     * @return bool
     */
    public function markAsFailed(int $notificationId, string $reason, int $retryCount): bool
    {
        return $this->update($notificationId, [
            'status'        => 'failed',
            'failed_reason' => $reason,
            'retry_count'   => $retryCount,
        ]);
    }

    /**
     * Get all failed notifications that still have retries remaining (retry_count < 3).
     *
     * @return Collection
     */
    public function getFailedForRetry(): Collection
    {
        return $this->model
            ->failed()
            ->where('retry_count', '<', 3)
            ->get();
    }
}
