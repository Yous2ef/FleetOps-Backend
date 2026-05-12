<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * WorkOrderSeeder — sample work orders covering all statuses and types.
 *
 * Fix: role values in users table use PascalCase ('Mechanic', 'FleetManager', 'Dispatcher')
 * not lowercase ('mechanic', 'fleet_manager').
 */
class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles  = DB::table('vehicles')->pluck('vehicle_id')->toArray();

        // ── Fixed: PascalCase role values to match UserSeeder ─────────────────
        $mechanics = DB::table('users')
            ->where('role', 'Mechanic')
            ->pluck('user_id')
            ->toArray();

        $admins = DB::table('users')
            ->whereIn('role', ['FleetManager', 'Dispatcher'])
            ->pluck('user_id')
            ->toArray();

        if (empty($vehicles)) {
            $this->command->warn('⚠️  WorkOrderSeeder: No vehicles found. Run VehicleSeeder first.');
            return;
        }

        $createdBy = $admins[0]  ?? null;
        $mechanic1 = $mechanics[0] ?? null;
        $mechanic2 = $mechanics[1] ?? $mechanic1;

        $now = now();

        $orders = [
            // 1 ── Open — waiting for assignment
            [
                'vehicle_id'          => $vehicles[0],
                'mechanic_id'         => null,
                'created_by'          => $createdBy,
                'type'                => 'breakdown',
                'status'              => 'open',
                'description'         => 'Vehicle won\'t start — suspected dead battery and faulty alternator.',
                'repair_cost'         => null,
                'parts_used'          => null,
                'priority'            => 'critical',
                'odometer_at_service' => 45200.00,
                'opened_at'           => $now->copy()->subDays(2),
                'assigned_at'         => null,
                'started_at'          => null,
                'resolved_at'         => null,
                'closed_at'           => null,
                'notes'               => null,
                'created_at'          => $now->copy()->subDays(2),
                'updated_at'          => $now->copy()->subDays(2),
            ],
            // 2 ── Assigned — mechanic assigned, not started yet
            [
                'vehicle_id'          => $vehicles[1] ?? $vehicles[0],
                'mechanic_id'         => $mechanic1,
                'created_by'          => $createdBy,
                'type'                => 'emergency',
                'status'              => 'assigned',
                'description'         => 'Engine oil leak detected near the timing cover gasket.',
                'repair_cost'         => null,
                'parts_used'          => null,
                'priority'            => 'high',
                'odometer_at_service' => 88750.00,
                'opened_at'           => $now->copy()->subDays(3),
                'assigned_at'         => $now->copy()->subDays(2),
                'started_at'          => null,
                'resolved_at'         => null,
                'closed_at'           => null,
                'notes'               => 'Mechanic assigned — awaiting spare parts delivery.',
                'created_at'          => $now->copy()->subDays(3),
                'updated_at'          => $now->copy()->subDays(2),
            ],
            // 3 ── In Progress — routine service underway
            [
                'vehicle_id'          => $vehicles[2] ?? $vehicles[0],
                'mechanic_id'         => $mechanic2,
                'created_by'          => $createdBy,
                'type'                => 'routine',
                'status'              => 'in_progress',
                'description'         => 'Scheduled 10,000 km service — oil change, air filter, tyre rotation.',
                'repair_cost'         => null,
                'parts_used'          => json_encode([
                    ['name' => 'Engine Oil 5W-30 (5L)', 'quantity' => 1],
                    ['name' => 'Air Filter',            'quantity' => 1],
                ]),
                'priority'            => 'medium',
                'odometer_at_service' => 121200.00,
                'opened_at'           => $now->copy()->subDays(1),
                'assigned_at'         => $now->copy()->subDays(1),
                'started_at'          => $now->copy()->subHours(3),
                'resolved_at'         => null,
                'closed_at'           => null,
                'notes'               => 'Work in progress.',
                'created_at'          => $now->copy()->subDays(1),
                'updated_at'          => $now->copy()->subHours(3),
            ],
            // 4 ── Resolved — awaiting fleet manager sign-off
            [
                'vehicle_id'          => $vehicles[3] ?? $vehicles[0],
                'mechanic_id'         => $mechanic1,
                'created_by'          => $createdBy,
                'type'                => 'breakdown',
                'status'              => 'resolved',
                'description'         => 'Front-left tyre blowout. Replaced with new tyre 225/70R15.',
                'repair_cost'         => 850.00,
                'parts_used'          => json_encode([
                    ['name' => 'Tyre 225/70R15', 'quantity' => 1],
                ]),
                'priority'            => 'high',
                'odometer_at_service' => 67300.00,
                'opened_at'           => $now->copy()->subDays(5),
                'assigned_at'         => $now->copy()->subDays(5),
                'started_at'          => $now->copy()->subDays(4),
                'resolved_at'         => $now->copy()->subDays(1),
                'closed_at'           => null,
                'notes'               => 'Repair complete — pending fleet manager review.',
                'created_at'          => $now->copy()->subDays(5),
                'updated_at'          => $now->copy()->subDays(1),
            ],
            // 5 ── Closed — full lifecycle complete
            [
                'vehicle_id'          => $vehicles[4] ?? $vehicles[1] ?? $vehicles[0],
                'mechanic_id'         => $mechanic2,
                'created_by'          => $createdBy,
                'type'                => 'routine',
                'status'              => 'closed',
                'description'         => 'Full pre-trip inspection: brakes, lights, coolant, belts.',
                'repair_cost'         => 1200.00,
                'parts_used'          => json_encode([
                    ['name' => 'Fuel Filter',    'quantity' => 1],
                    ['name' => 'Spark Plugs',    'quantity' => 4],
                ]),
                'priority'            => 'low',
                'odometer_at_service' => 154900.00,
                'opened_at'           => $now->copy()->subDays(10),
                'assigned_at'         => $now->copy()->subDays(10),
                'started_at'          => $now->copy()->subDays(9),
                'resolved_at'         => $now->copy()->subDays(8),
                'closed_at'           => $now->copy()->subDays(7),
                'notes'               => 'All checks passed — vehicle returned to service.',
                'created_at'          => $now->copy()->subDays(10),
                'updated_at'          => $now->copy()->subDays(7),
            ],
            // 6 ── Open — low priority routine
            [
                'vehicle_id'          => $vehicles[5] ?? $vehicles[2] ?? $vehicles[0],
                'mechanic_id'         => null,
                'created_by'          => $createdBy,
                'type'                => 'routine',
                'status'              => 'open',
                'description'         => 'Cabin AC filter replacement — reduced airflow reported by driver.',
                'repair_cost'         => null,
                'parts_used'          => null,
                'priority'            => 'low',
                'odometer_at_service' => 34050.00,
                'opened_at'           => $now->copy()->subHours(5),
                'assigned_at'         => null,
                'started_at'          => null,
                'resolved_at'         => null,
                'closed_at'           => null,
                'notes'               => null,
                'created_at'          => $now->copy()->subHours(5),
                'updated_at'          => $now->copy()->subHours(5),
            ],
        ];

        // Idempotent: skip if any work orders already exist
        if (DB::table('work_orders')->count() > 0) {
            $this->command->info('WorkOrderSeeder: work_orders table already has data — skipping.');
            return;
        }

        DB::table('work_orders')->insert($orders);

        $this->command->info('✅ WorkOrderSeeder: ' . count($orders) . ' work orders seeded.');
    }
}
