<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ترتيب الـ FK dependencies مهم جداً
        $this->call([
            UserSeeder::class,               // Tier 0: users
            VehicleSeeder::class,            // Tier 0: vehicles
            InventorySeeder::class,          // Tier 0: inventory
            ProfileSeeder::class,            // Tier 1: customers, drivers, dispatchers, fleet_managers, mechanics
            DriverPerformanceSeeder::class,  // Tier 2: driver_performance (after drivers)
            MaintenanceAssignmentSeeder::class, // Tier 2: maintenance_assignments
            RouteSeeder::class,              // Tier 2: routes
            GpsPingSeeder::class,            // Tier 2: GPS trails for active/planned routes
            OrderSeeder::class,              // Tier 3: orders + parcels
            CashLedgerSeeder::class,         // Tier 4: cash_ledger (after orders & drivers)
            FuelAuditLogSeeder::class,       // Tier 1: fuel_audit_logs
            IncidentReportSeeder::class,     // Tier 2: incident_reports
            NotificationPreferenceSeeder::class, // Notification preferences
            NotificationSeeder::class,           // Sample notifications
        ]);
    }
}
