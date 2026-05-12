<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * NotificationPreferenceSeeder
 *
 * Seeds realistic per-role notification preferences for all seeded users.
 * Idempotent: uses updateOrInsert keyed on user_id.
 *
 * Role-based defaults:
 *  - FleetManager  → push + email, quiet hours 23:00–06:00 (business hours focused)
 *  - Dispatcher    → push + sms + email, no quiet hours (always on-call)
 *  - Driver        → push only, quiet hours 22:00–07:00 (rest after long shifts)
 *  - Mechanic      → push + email, no quiet hours
 *  - Customer      → email + sms, quiet hours 21:00–08:00
 */
class NotificationPreferenceSeeder extends Seeder
{
    /**
     * Preference templates by user role.
     * Keys match the 'role' column in the users table.
     */
    private const ROLE_DEFAULTS = [
        'FleetManager' => [
            'push_enabled'       => true,
            'sms_enabled'        => false,
            'email_enabled'      => true,
            'quiet_hours_start'  => '23:00',
            'quiet_hours_end'    => '06:00',
            'preferred_language' => 'en',
        ],
        'Dispatcher' => [
            'push_enabled'       => true,
            'sms_enabled'        => true,
            'email_enabled'      => true,
            'quiet_hours_start'  => null,
            'quiet_hours_end'    => null,
            'preferred_language' => 'en',
        ],
        'Driver' => [
            'push_enabled'       => true,
            'sms_enabled'        => false,
            'email_enabled'      => false,
            'quiet_hours_start'  => '22:00',
            'quiet_hours_end'    => '07:00',
            'preferred_language' => 'ar',
        ],
        'Mechanic' => [
            'push_enabled'       => true,
            'sms_enabled'        => false,
            'email_enabled'      => true,
            'quiet_hours_start'  => null,
            'quiet_hours_end'    => null,
            'preferred_language' => 'ar',
        ],
        'Customer' => [
            'push_enabled'       => false,
            'sms_enabled'        => true,
            'email_enabled'      => true,
            'quiet_hours_start'  => '21:00',
            'quiet_hours_end'    => '08:00',
            'preferred_language' => 'en',
        ],
    ];

    public function run(): void
    {
        $users = DB::table('users')->select('user_id', 'role')->get();

        if ($users->isEmpty()) {
            $this->command->warn('NotificationPreferenceSeeder: No users found. Run UserSeeder first.');
            return;
        }

        $now   = now();
        $count = 0;

        foreach ($users as $user) {
            $defaults = self::ROLE_DEFAULTS[$user->role] ?? self::ROLE_DEFAULTS['Customer'];

            DB::table('notification_preferences')->updateOrInsert(
                ['user_id' => $user->user_id],
                array_merge($defaults, [
                    'fcm_token'  => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

            $count++;
        }

        $this->command->info("✅ NotificationPreferenceSeeder: {$count} preferences ready.");
    }
}
