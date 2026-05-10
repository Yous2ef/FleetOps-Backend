<?php

/**
 * @file UserRepository.php
 * @description مستودع بيانات المستخدمين — كل عمليات users table
 * @module AuthIdentity
 * @author Team Leader (Khalid)
 */

namespace App\Modules\AuthIdentity\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\AuthIdentity\Models\User;
use App\Modules\AuthIdentity\Models\Driver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * البحث عن مستخدم بالإيميل
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * جلب المستخدمين النشطين
     */
    public function getActiveUsers(): EloquentCollection
    {
        return $this->model->active()->get();
    }

    /**
     * Build the standard frontend driver shape from a Driver + eager-loaded User.
     * Centralizes mapping so both getDrivers() and getDriversByStatus() stay consistent.
     */
    private function mapDriver($driver): array
    {
        // Derive initials from the linked user's name (e.g. "Ahmed Sayed" → "AS")
        $name     = $driver->user->name ?? '';
        $initials = '';
        if ($name) {
            $initials = implode('', array_map(
                fn($word) => strtoupper(mb_substr($word, 0, 1)),
                array_filter(explode(' ', $name))
            ));
        }

        return [
            'driver_id'       => (string) $driver->driver_id,
            'name'            => $name,
            'initials'        => $initials,
            'status'          => $driver->status ?? '',
            'score'           => (int) ($driver->score ?? 0),
            'shift'           => $driver->status ?? '',   // mirrors status until a dedicated shift col exists
            'license_type'    => $driver->license_type ?? '',
            'license_no'      => $driver->license_no ?? '',
            'stats'           => [
                'deliveries'   => 0,
                'success_rate' => 0,
                'on_time_rate' => 0,
                'avg_time'     => 0,
            ],
            'current_vehicle' => $driver->vehicle_id ? (string) $driver->vehicle_id : null,
            'current_route'   => null,
        ];
    }

    /**
     * جلب السائقين النشطين
     */
    public function getDrivers(): Collection
    {
        $drivers = Driver::query()
            ->with('user:user_id,name,email,phone_no')
            ->get();

        // ->map() returns Support\Collection (not Eloquent\Collection)
        return $drivers->map(fn($d) => $this->mapDriver($d));
    }

    /**
     * جلب السائقين حسب الحالة (Available / OnShift / OffShift)
     */
    public function getDriversByStatus(string $status): Collection
    {
        $drivers = Driver::query()
            ->where('status', $status)
            ->with('user:user_id,name,email,phone_no')
            ->get();

        // ->map() returns Support\Collection (not Eloquent\Collection)
        return $drivers->map(fn($d) => $this->mapDriver($d));
    }   

    /**
     * جلب الموزعين
     */
    public function getDispatchers(): EloquentCollection
    {
        return $this->model->active()->byRole('Dispatcher')->get();
    }

    /**
     * جلب مديري الأسطول
     */
    public function getFleetManagers(): EloquentCollection
    {
        return $this->model->active()->byRole('FleetManager')->get();
    }

    /**
     * جلب الميكانيكيين
     */
    public function getMechanics(): EloquentCollection
    {
        return $this->model->active()->byRole('Mechanic')->get();
    }

    /**
     * تحديث عدد محاولات الدخول الفاشلة
     */
    public function updateFailedAttempts(int $userId, int $attempts): bool
    {
        return $this->update($userId, ['failed_login_attempts' => $attempts]);
    }

    /**
     * قفل حساب المستخدم مؤقتاً
     */
    public function lockUser(int $userId, \DateTime $until): bool
    {
        return $this->update($userId, ['locked_until' => $until, 'is_active' => false]);
    }

    /**
     * تحديث آخر تسجيل دخول وإعادة تعيين محاولات الفشل
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, [
            'last_login_at'          => now(),
            'failed_login_attempts'  => 0,
        ]);
    }

    /**
     * تغيير حالة المستخدم
     */
    public function setActive(int $userId, bool $isActive): bool
    {
        return $this->update($userId, ['is_active' => $isActive]);
    }
}
