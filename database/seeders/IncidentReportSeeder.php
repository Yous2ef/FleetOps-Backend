<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * IncidentReportSeeder — real incident reports linked to actual drivers & vehicles.
 *
 * Idempotent: uses updateOrInsert keyed on (driver_id, incident_ts).
 */
class IncidentReportSeeder extends Seeder
{
    public function run(): void
    {
        $drivers  = DB::table('drivers')->pluck('driver_id')->toArray();
        $vehicles = DB::table('vehicles')->pluck('vehicle_id')->toArray();

        if (empty($drivers) || empty($vehicles)) {
            $this->command->warn('⚠️  IncidentReportSeeder: No drivers or vehicles found.');
            return;
        }

        $now = now();

        $reports = [
            [
                'driver_id'   => $drivers[1] ?? $drivers[0],
                'vehicle_id'  => $vehicles[1] ?? $vehicles[0],
                'type'        => 'breakdown',
                'severity'    => 'high',
                'description' => 'Vehicle broke down on the Cairo–Alex desert road at km 87. Engine failure suspected. Tow truck requested.',
                'latitude'    => 30.0561,
                'longitude'   => 31.2394,
                'photo_urls'  => json_encode(['https://storage.fleetops.com/incidents/inc-001-a.jpg']),
                'incident_ts' => $now->copy()->subDays(15)->setHour(11)->setMinute(45)->setSecond(0)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[0],
                'vehicle_id'  => $vehicles[0],
                'type'        => 'traffic_violation',
                'severity'    => 'low',
                'description' => 'Driver received a fine for illegal parking in a loading zone near Nasr City warehouse.',
                'latitude'    => 30.0626,
                'longitude'   => 31.3417,
                'photo_urls'  => null,
                'incident_ts' => $now->copy()->subDays(16)->setHour(14)->setMinute(20)->setSecond(0)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[2] ?? $drivers[0],
                'vehicle_id'  => $vehicles[2] ?? $vehicles[0],
                'type'        => 'cargo_damage',
                'severity'    => 'medium',
                'description' => 'Two fragile packages damaged due to sudden braking on Ring Road. Customer notified.',
                'latitude'    => 30.0131,
                'longitude'   => 31.2089,
                'photo_urls'  => json_encode([
                    'https://storage.fleetops.com/incidents/inc-003-a.jpg',
                    'https://storage.fleetops.com/incidents/inc-003-b.jpg',
                ]),
                'incident_ts' => $now->copy()->subDays(15)->setHour(9)->setMinute(10)->setSecond(0)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[3] ?? $drivers[0],
                'vehicle_id'  => $vehicles[3] ?? $vehicles[0],
                'type'        => 'accident',
                'severity'    => 'medium',
                'description' => 'Minor rear-end collision at Giza toll gate. No injuries. Front bumper cracked.',
                'latitude'    => 30.0222,
                'longitude'   => 31.1950,
                'photo_urls'  => json_encode(['https://storage.fleetops.com/incidents/inc-004-a.jpg']),
                'incident_ts' => $now->copy()->subDays(7)->setHour(16)->setMinute(30)->setSecond(0)->toDateTimeString(),
            ],
            [
                'driver_id'   => $drivers[4] ?? $drivers[0],
                'vehicle_id'  => $vehicles[4] ?? $vehicles[0],
                'type'        => 'breakdown',
                'severity'    => 'critical',
                'description' => 'Refrigerated unit temperature alarm — compressor failure. Cargo spoilage risk. Emergency retrieval dispatched.',
                'latitude'    => 29.8741,
                'longitude'   => 30.9958,
                'photo_urls'  => json_encode(['https://storage.fleetops.com/incidents/inc-005-a.jpg']),
                'incident_ts' => $now->copy()->subDays(3)->setHour(8)->setMinute(0)->setSecond(0)->toDateTimeString(),
            ],
        ];

        $inserted = 0;
        foreach ($reports as $r) {
            $exists = DB::table('incident_reports')
                ->where('driver_id', $r['driver_id'])
                ->where('incident_ts', $r['incident_ts'])
                ->exists();

            if (! $exists) {
                DB::table('incident_reports')->insert($r);
                $inserted++;
            }
        }

        $this->command->info("✅ IncidentReportSeeder: {$inserted} new incident reports added (total: " . count($reports) . ').');
    }
}
