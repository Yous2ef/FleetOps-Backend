<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Modules\AuthIdentity\Models\User;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $firstUser = User::first();
        if (!$firstUser) return;
        
        $userId = $firstUser->user_id;

        $notifications = [
            [
                'user_id' => $userId,
                'channel' => 'push',
                'event_type' => 'status_update',
                'payload' => json_encode([
                    'title' => 'Route Started', 
                    'body' => 'Your route R-001 has been started.',
                    'description' => 'The system has successfully dispatched vehicle V-101 for route R-001. All stops have been synchronized to the driver app.'
                ]),
                'status' => 'delivered',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'channel' => 'push',
                'event_type' => 'delay_alert',
                'payload' => json_encode([
                    'title' => 'Traffic Delay', 
                    'body' => 'Expect a 15 min delay on Route R-002.',
                    'description' => 'Heavy traffic detected on the Ring Road near the 6th of October exit. Estimated time of arrival for Stop 3 has been adjusted.'
                ]),
                'status' => 'delivered',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ],
            [
                'user_id' => $userId,
                'channel' => 'push',
                'event_type' => 'maintenance_alert',
                'payload' => json_encode([
                    'title' => 'Maintenance Required', 
                    'body' => 'Vehicle V-101 needs oil change.',
                    'description' => 'Predictive maintenance alert: Vehicle V-101 has reached 10,000 km since its last oil change. Please schedule a visit to the workshop.'
                ]),
                'status' => 'pending',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'user_id' => $userId,
                'channel' => 'push',
                'event_type' => 'proximity_alert',
                'payload' => json_encode([
                    'title' => 'Driver Nearby', 
                    'body' => 'Driver is 2km away from warehouse.',
                    'description' => 'Proximity alert: Vehicle V-105 is approaching the central warehouse for pickup. Dock 4 is assigned.'
                ]),
                'status' => 'delivered',
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ],
        ];

        DB::table('notifications')->insert($notifications);
    }
}
