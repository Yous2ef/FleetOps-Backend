<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RouteSeeder — delivery routes with dynamic dates relative to now().
 *
 * Route statuses:
 *  - Completed  : finished ~2 weeks ago
 *  - InProgress : started today, ongoing
 *  - Planned    : scheduled for the next few days
 */
class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $drivers     = DB::table('drivers')->pluck('driver_id')->toArray();
        $dispatchers = DB::table('dispatchers')->pluck('dispatcher_id')->toArray();
        $vehicles    = DB::table('vehicles')->where('Status', 'Active')->pluck('vehicle_id')->toArray();

        if (empty($drivers) || empty($dispatchers) || empty($vehicles)) {
            $this->command->warn('⚠️  RouteSeeder: Missing drivers/dispatchers/vehicles — run ProfileSeeder first.');
            return;
        }

        $now = Carbon::now();

        $routes = [
            // ── Route 1: Completed ~2 weeks ago ───────────────────────────────
            [
                'route_name'           => 'Cairo Ring Road — Morning Run',
                'driver_id'            => $drivers[0] ?? null,
                'dispatcher_id'        => $dispatchers[0] ?? null,
                'vehicle_id'           => $vehicles[0] ?? null,
                'scheduled_start_time' => $now->copy()->subDays(14)->setHour(7)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'actual_start_time'    => $now->copy()->subDays(14)->setHour(7)->setMinute(10)->setSecond(0)->toDateTimeString(),
                'scheduled_end_time'   => $now->copy()->subDays(14)->setHour(13)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'status'               => 'Completed',
                'total_distance'       => 187.50,
                'total_stops'          => 6,
                'fuel_consumption_est' => 28.20,
            ],
            // ── Route 2: In-progress today ────────────────────────────────────
            [
                'route_name'           => 'Alexandria — Giza Express',
                'driver_id'            => $drivers[1] ?? null,
                'dispatcher_id'        => $dispatchers[0] ?? null,
                'vehicle_id'           => $vehicles[1] ?? null,
                'scheduled_start_time' => $now->copy()->setHour(6)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'actual_start_time'    => $now->copy()->setHour(6)->setMinute(5)->setSecond(0)->toDateTimeString(),
                'scheduled_end_time'   => $now->copy()->setHour(14)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'status'               => 'InProgress',
                'total_distance'       => 220.00,
                'total_stops'          => 4,
                'fuel_consumption_est' => 42.50,
            ],
            // ── Route 3: Planned — 3 days out ─────────────────────────────────
            [
                'route_name'           => 'Nasr City — 10th Ramadan',
                'driver_id'            => $drivers[2] ?? null,
                'dispatcher_id'        => $dispatchers[1] ?? null,
                'vehicle_id'           => $vehicles[2] ?? null,
                'scheduled_start_time' => $now->copy()->addDays(3)->setHour(8)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'actual_start_time'    => null,
                'scheduled_end_time'   => $now->copy()->addDays(3)->setHour(15)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'status'               => 'Planned',
                'total_distance'       => 95.00,
                'total_stops'          => 5,
                'fuel_consumption_est' => 18.00,
            ],
            // ── Route 4: Planned — 5 days out ─────────────────────────────────
            [
                'route_name'           => 'Giza Distribution Loop',
                'driver_id'            => $drivers[0] ?? null,
                'dispatcher_id'        => $dispatchers[1] ?? null,
                'vehicle_id'           => $vehicles[4] ?? $vehicles[0] ?? null,
                'scheduled_start_time' => $now->copy()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'actual_start_time'    => null,
                'scheduled_end_time'   => $now->copy()->addDays(5)->setHour(17)->setMinute(0)->setSecond(0)->toDateTimeString(),
                'status'               => 'Planned',
                'total_distance'       => 145.00,
                'total_stops'          => 8,
                'fuel_consumption_est' => 25.50,
            ],
        ];

        foreach ($routes as $r) {
            $exists = DB::table('routes')
                ->where('route_name', $r['route_name'])
                ->exists();

            if (! $exists) {
                DB::table('routes')->insert(array_merge($r, ['created_at' => $now]));
            }
        }

        $this->command->info('✅ RouteSeeder: ' . count($routes) . ' routes ready.');
    }
}
