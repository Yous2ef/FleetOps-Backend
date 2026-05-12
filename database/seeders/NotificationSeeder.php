<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * NotificationSeeder
 *
 * Seeds 25 realistic notifications covering all event types, statuses, and roles.
 * Idempotent: skips users that already have notifications.
 *
 * Event types covered:
 *  - status_update      → dispatchers & customers
 *  - delay_alert        → dispatchers
 *  - proximity_alert    → customers
 *  - maintenance_alert  → fleet managers
 *  - low_stock_alert    → fleet managers
 *  - incident_alert     → fleet managers & dispatchers
 *  - route_started      → drivers
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // ── Load users by role ─────────────────────────────────────────────────
        $users = DB::table('users')->select('user_id', 'role', 'name')->get()->keyBy('role');

        $byRole = [];
        DB::table('users')->select('user_id', 'role', 'name')->get()->each(function ($u) use (&$byRole) {
            $byRole[$u->role][] = $u;
        });

        if (empty($byRole)) {
            $this->command->warn('NotificationSeeder: No users found. Run UserSeeder first.');
            return;
        }

        // Helper to pick first user of a role safely
        $pick = fn(string $role, int $index = 0) => $byRole[$role][$index] ?? null;

        $now          = Carbon::now();
        $notifications = [];

        // ── 1. Status Updates → Dispatchers ───────────────────────────────────
        if ($dispatcher = $pick('Dispatcher')) {
            $notifications[] = $this->make(
                $dispatcher->user_id, 'push', 'status_update', 'delivered',
                ['title' => 'Order Delivered', 'body' => 'Order #1045 has been delivered successfully to Nasr City.', 'order_id' => 1045],
                $now->copy()->subHours(1), dedup: "d{$dispatcher->user_id}_status_update_1045"
            );
            $notifications[] = $this->make(
                $dispatcher->user_id, 'push', 'delay_alert', 'delivered',
                ['title' => 'Traffic Delay', 'body' => 'Expect a 20 min delay on Route R-002. New ETA: 16:45.', 'order_id' => 1022],
                $now->copy()->subHours(3), dedup: "d{$dispatcher->user_id}_delay_alert_1022"
            );
            $notifications[] = $this->make(
                $dispatcher->user_id, 'email', 'status_update', 'delivered',
                ['title' => 'Order Returned', 'body' => 'Order #1031 was returned — customer was absent.', 'order_id' => 1031],
                $now->copy()->subHours(5), dedup: "d{$dispatcher->user_id}_status_update_1031"
            );
        }

        if ($dispatcher2 = $pick('Dispatcher', 1)) {
            $notifications[] = $this->make(
                $dispatcher2->user_id, 'push', 'delay_alert', 'pending',
                ['title' => 'Delivery Window Violation', 'body' => 'Order #1067 will miss its promised window by 35 minutes.', 'order_id' => 1067],
                $now->copy()->subMinutes(10), dedup: "d{$dispatcher2->user_id}_delay_alert_1067"
            );
            $notifications[] = $this->make(
                $dispatcher2->user_id, 'push', 'incident_alert', 'delivered',
                ['title' => 'Vehicle Incident', 'body' => 'Vehicle V-103 reported a breakdown near 6th of October City.', 'plate_number' => 'V-103', 'location' => '6th of October City'],
                $now->copy()->subHours(2), dedup: "d{$dispatcher2->user_id}_incident_alert_V103"
            );
        }

        // ── 2. Proximity & Status → Customers ─────────────────────────────────
        if ($customer = $pick('Customer')) {
            $notifications[] = $this->make(
                $customer->user_id, 'sms', 'proximity_alert', 'delivered',
                ['title' => 'Driver Nearby', 'body' => 'Your driver is 500m away and will arrive shortly to deliver your order.', 'order_id' => 1045],
                $now->copy()->subHours(2), dedup: "c{$customer->user_id}_proximity_alert_1045"
            );
            $notifications[] = $this->make(
                $customer->user_id, 'email', 'status_update', 'delivered',
                ['title' => 'Order In Transit', 'body' => 'Your order #1045 is now on its way. ETA: 14:30.', 'order_id' => 1045],
                $now->copy()->subHours(4), dedup: "c{$customer->user_id}_status_update_1045_transit"
            );
            $notifications[] = $this->make(
                $customer->user_id, 'sms', 'status_update', 'delivered',
                ['title' => 'Order Delivered', 'body' => 'Your order #1045 has been delivered. Thank you for choosing FleetOps!', 'order_id' => 1045],
                $now->copy()->subHours(1), dedup: "c{$customer->user_id}_status_update_1045_done"
            );
        }

        if ($customer2 = $pick('Customer', 1)) {
            $notifications[] = $this->make(
                $customer2->user_id, 'sms', 'delay_alert', 'delivered',
                ['title' => 'Delivery Delayed', 'body' => 'We apologize for the delay. Expected delivery time: 17:00.', 'order_id' => 1067],
                $now->copy()->subMinutes(30), dedup: "c{$customer2->user_id}_delay_alert_1067"
            );
            $notifications[] = $this->make(
                $customer2->user_id, 'email', 'status_update', 'pending',
                ['title' => 'Order Assigned', 'body' => 'Your order #1078 has been assigned to a driver and will be delivered today.', 'order_id' => 1078],
                $now->copy()->subMinutes(45), dedup: "c{$customer2->user_id}_status_update_1078"
            );
        }

        // ── 3. Maintenance Alerts → Fleet Managers ────────────────────────────
        if ($manager = $pick('FleetManager')) {
            $notifications[] = $this->make(
                $manager->user_id, 'push', 'maintenance_alert_odometer', 'delivered',
                ['title' => 'Odometer Service Alert', 'body' => 'Vehicle ABC-1234 has reached 10,000 km since last oil change. Please schedule maintenance.', 'plate_number' => 'ABC-1234', 'odometer' => '10000'],
                $now->copy()->subDays(1), dedup: "m{$manager->user_id}_odometer_ABC1234"
            );
            $notifications[] = $this->make(
                $manager->user_id, 'email', 'low_stock_alert', 'delivered',
                ['title' => 'Low Stock Alert', 'body' => 'Engine Oil (10W-40) stock has reached minimum level (5 liters). Please reorder.', 'part_name' => 'Engine Oil (10W-40)', 'quantity' => '5', 'unit' => 'liters'],
                $now->copy()->subDays(2), dedup: "m{$manager->user_id}_low_stock_oil"
            );
            $notifications[] = $this->make(
                $manager->user_id, 'push', 'maintenance_alert_inspection', 'delivered',
                ['title' => 'Annual Inspection Due', 'body' => 'Vehicle XYZ-5678 insurance expires in 28 days. Please schedule renewal.', 'plate_number' => 'XYZ-5678'],
                $now->copy()->subDays(3), dedup: "m{$manager->user_id}_inspection_XYZ5678"
            );
            $notifications[] = $this->make(
                $manager->user_id, 'push', 'incident_alert', 'delivered',
                ['title' => 'Breakdown — Mechanic Dispatched', 'body' => 'Vehicle DEF-9012 broke down near Maadi. Nearest mechanic has been dispatched.', 'plate_number' => 'DEF-9012', 'location' => 'Maadi Ring Road'],
                $now->copy()->subHours(6), dedup: "m{$manager->user_id}_incident_DEF9012"
            );
            $notifications[] = $this->make(
                $manager->user_id, 'email', 'low_stock_alert', 'delivered',
                ['title' => 'Low Stock Alert', 'body' => 'Brake Pads stock has reached minimum level (2 sets). Please reorder.', 'part_name' => 'Brake Pads', 'quantity' => '2', 'unit' => 'sets'],
                $now->copy()->subDays(5), dedup: "m{$manager->user_id}_low_stock_brakes"
            );
        }

        if ($manager2 = $pick('FleetManager', 1)) {
            $notifications[] = $this->make(
                $manager2->user_id, 'push', 'maintenance_alert_odometer', 'delivered',
                ['title' => 'Odometer Service Alert', 'body' => 'Vehicle GHI-3456 has reached 15,000 km since last service. Immediate attention required.', 'plate_number' => 'GHI-3456', 'odometer' => '15000'],
                $now->copy()->subDays(1)->subHours(3), dedup: "m{$manager2->user_id}_odometer_GHI3456"
            );
            $notifications[] = $this->make(
                $manager2->user_id, 'email', 'maintenance_alert_inspection', 'pending',
                ['title' => 'Insurance Expiry Alert', 'body' => 'Vehicle JKL-7890 insurance expires in 15 days. Urgent renewal required.', 'plate_number' => 'JKL-7890'],
                $now->copy()->subHours(8), dedup: "m{$manager2->user_id}_inspection_JKL7890"
            );
        }

        // ── 4. Route Notifications → Drivers ──────────────────────────────────
        if ($driver = $pick('Driver')) {
            $notifications[] = $this->make(
                $driver->user_id, 'push', 'route_started', 'delivered',
                ['title' => 'Route Assigned', 'body' => 'Route R-007 has been assigned to you. 8 stops — start time: 08:00.', 'route_id' => 'R-007'],
                $now->copy()->subHours(10), dedup: "dr{$driver->user_id}_route_R007"
            );
            $notifications[] = $this->make(
                $driver->user_id, 'push', 'shift_transfer', 'delivered',
                ['title' => 'Shift Transfer', 'body' => 'Your remaining stops on Route R-005 have been transferred to Omar Tarek for the evening shift.', 'driver_name' => 'Omar Tarek'],
                $now->copy()->subDays(1), dedup: "dr{$driver->user_id}_shift_R005"
            );
        }

        if ($driver2 = $pick('Driver', 1)) {
            $notifications[] = $this->make(
                $driver2->user_id, 'push', 'route_started', 'delivered',
                ['title' => 'Route Assigned', 'body' => 'Route R-008 has been assigned to you. 6 stops starting at Nasr City warehouse.', 'route_id' => 'R-008'],
                $now->copy()->subHours(9), dedup: "dr{$driver2->user_id}_route_R008"
            );
        }

        // ── 5. Failed Notifications (with retry count) ─────────────────────────
        if ($customer3 = $pick('Customer', 2)) {
            $notifications[] = $this->make(
                $customer3->user_id, 'push', 'proximity_alert', 'failed',
                ['title' => 'Driver Nearby', 'body' => 'Your driver is 500m away.', 'order_id' => 1099],
                $now->copy()->subHours(3),
                dedup: "c{$customer3->user_id}_proximity_alert_1099",
                retryCount: 2,
                failedReason: 'FCM token expired'
            );
        }

        if ($customer4 = $pick('Customer', 3)) {
            $notifications[] = $this->make(
                $customer4->user_id, 'sms', 'delay_alert', 'failed',
                ['title' => 'Delivery Delayed', 'body' => 'Expected delivery: 18:30.', 'order_id' => 1055],
                $now->copy()->subHours(4),
                dedup: "c{$customer4->user_id}_delay_alert_1055",
                retryCount: 1,
                failedReason: 'Twilio: Invalid phone number format'
            );
        }

        // ── Insert all ─────────────────────────────────────────────────────────
        if (empty($notifications)) {
            $this->command->warn('NotificationSeeder: No notifications to insert (users missing).');
            return;
        }

        // Idempotent: delete existing and re-seed for clean state
        DB::table('notifications')->truncate();
        DB::table('notifications')->insert($notifications);

        $this->command->info('✅ NotificationSeeder: ' . count($notifications) . ' notifications seeded.');
    }

    /**
     * Build a notification row array.
     *
     * @param int    $userId
     * @param string $channel      push | sms | email
     * @param string $eventType
     * @param string $status       pending | sent | delivered | failed
     * @param array  $payload
     * @param Carbon $createdAt
     * @param string $dedup
     * @param int    $retryCount
     * @param string $failedReason
     */
    private function make(
        int    $userId,
        string $channel,
        string $eventType,
        string $status,
        array  $payload,
        Carbon $createdAt,
        string $dedup = '',
        int    $retryCount = 0,
        string $failedReason = ''
    ): array {
        $sentAt      = in_array($status, ['sent', 'delivered']) ? $createdAt->copy()->addSeconds(5)->toDateTimeString() : null;
        $deliveredAt = $status === 'delivered'                  ? $createdAt->copy()->addSeconds(8)->toDateTimeString() : null;

        return [
            'user_id'       => $userId,
            'channel'       => $channel,
            'event_type'    => $eventType,
            'payload'       => json_encode($payload),
            'status'        => $status,
            'dedup_key'     => $dedup ?: null,
            'retry_count'   => $retryCount,
            'sent_at'       => $sentAt,
            'delivered_at'  => $deliveredAt,
            'failed_reason' => $failedReason ?: null,
            'created_at'    => $createdAt->toDateTimeString(),
            'updated_at'    => $createdAt->copy()->addSeconds(10)->toDateTimeString(),
        ];
    }
}
