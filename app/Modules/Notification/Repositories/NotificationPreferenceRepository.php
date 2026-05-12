<?php

/**
 * @file: NotificationPreferenceRepository.php
 * @description: مستودع تفضيلات الإشعارات - Notification Service (NF-02)
 * @module: Notification
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\Notification\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Notification\Models\NotificationPreference;
use Carbon\Carbon;

class NotificationPreferenceRepository extends BaseRepository
{
    public function __construct(NotificationPreference $model)
    {
        parent::__construct($model);
    }

    /**
     * Get notification preferences for a specific user.
     *
     * @param int $userId
     * @return NotificationPreference|null
     */
    public function getForUser(int $userId): ?NotificationPreference
    {
        return $this->model->where('user_id', $userId)->first();
    }

    /**
     * Create or update notification preferences for a user (Upsert).
     *
     * @param int   $userId
     * @param array $data
     * @return NotificationPreference
     */
    public function upsertForUser(int $userId, array $data): NotificationPreference
    {
        return $this->model->updateOrCreate(
            ['user_id' => $userId],
            $data
        );
    }

    /**
     * Check whether the current time falls within the user's configured quiet hours.
     * Handles midnight-crossing windows (e.g., 23:00–06:00).
     *
     * @param int $userId
     * @return bool  true = currently in quiet hours (should NOT send)
     */
    public function isInQuietHours(int $userId): bool
    {
        $pref = $this->getForUser($userId);

        // No preferences or no quiet hours configured → never block
        if (!$pref || !$pref->quiet_hours_start || !$pref->quiet_hours_end) {
            return false;
        }

        $now   = Carbon::now();
        $start = Carbon::createFromFormat('H:i', $pref->quiet_hours_start);
        $end   = Carbon::createFromFormat('H:i', $pref->quiet_hours_end);

        // Normalise to today so we can do reliable comparisons
        $start->setDateFrom($now);
        $end->setDateFrom($now);

        // Handle midnight-crossing window (e.g. 23:00 → 06:00)
        // In this case end is "earlier" than start within the same day
        if ($end->lt($start)) {
            // Current time is after start (e.g. 23:30) OR before end (e.g. 05:00)
            return $now->gte($start) || $now->lte($end);
        }

        // Normal same-day window (e.g. 14:00 → 17:00)
        return $now->between($start, $end);
    }
}
