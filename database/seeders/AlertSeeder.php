<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = DB::table('drivers')->pluck('driver_id')->toArray();
        $vehicles = DB::table('vehicles')->pluck('vehicle_id')->toArray();

        if (empty($drivers) || empty($vehicles)) {
            return;
        }

        $now = Carbon::now();

        $alerts = [
            [
                'driver_id'   => $drivers[0],
                'vehicle_id'  => $vehicles[0],
                'type'        => 'breakdown',
                'severity'    => 'critical',
                'status'      => 'Open',
                'description' => 'Engine overheating in Maadi area. Vehicle immobilized.',
                'latitude'    => 29.9602,
                'longitude'   => 31.2569,
                'incident_ts' => $now->toDateTimeString(),
                'created_at'  => $now->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[1] ?? $drivers[0],
                'vehicle_id'  => $vehicles[1] ?? $vehicles[0],
                'type'        => 'accident',
                'severity'    => 'high',
                'status'      => 'Open',
                'description' => 'Minor collision at Giza Square. No injuries, but front bumper damaged.',
                'latitude'    => 30.0131,
                'longitude'   => 31.2089,
                'incident_ts' => $now->copy()->subHours(2)->toDateTimeString(),
                'created_at'  => $now->copy()->subHours(2)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[2] ?? $drivers[0],
                'vehicle_id'  => $vehicles[2] ?? $vehicles[0],
                'type'        => 'traffic_violation',
                'severity'    => 'low',
                'status'      => 'Open',
                'description' => 'Speeding alert triggered on Ring Road.',
                'latitude'    => 30.0626,
                'longitude'   => 31.3417,
                'incident_ts' => $now->copy()->subHours(5)->toDateTimeString(),
                'created_at'  => $now->copy()->subHours(5)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[0],
                'vehicle_id'  => $vehicles[0],
                'type'        => 'cargo_damage',
                'severity'    => 'medium',
                'status'      => 'Open',
                'description' => 'Sensor alert: Temperature in refrigerated trailer exceeded 5°C.',
                'latitude'    => 30.1234,
                'longitude'   => 31.4567,
                'incident_ts' => $now->copy()->subDay()->toDateTimeString(),
                'created_at'  => $now->copy()->subDay()->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[1] ?? $drivers[0],
                'vehicle_id'  => $vehicles[1] ?? $vehicles[0],
                'type'        => 'other',
                'severity'    => 'medium',
                'status'      => 'In Progress',
                'description' => 'Driver reported a suspicious noise from the suspension.',
                'latitude'    => 29.8765,
                'longitude'   => 31.1234,
                'incident_ts' => $now->copy()->subDay()->subHours(4)->toDateTimeString(),
                'created_at'  => $now->copy()->subDay()->subHours(4)->toDateTimeString(),
            ],
        ];

        DB::table('incident_reports')->insert($alerts);
    }
}
