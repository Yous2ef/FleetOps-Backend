<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\AuthIdentity\Models\User;

class NotificationPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::limit(50)->get();

        foreach ($users as $user) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'push_enabled' => true,
                    'sms_enabled' => false,
                    'email_enabled' => true,
                    'quiet_hours_start' => null,
                    'quiet_hours_end' => null,
                    'preferred_language' => $user->locale ?? 'en',
                    'fcm_token' => null,
                ]
            );
        }
    }
}
