<?php

namespace Tests\Feature\AuthIdentity;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Modules\AuthIdentity\Models\User;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user to act as for protected routes
        $this->adminUser = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin@fleet.test',
            'password' => Hash::make('password123'),
            'phone_no' => '1234567890',
            'role' => 'FleetManager',
            'is_active' => true,
        ]);
    }

    /**
     * Test getting a list of users.
     */
    public function test_authenticated_user_can_get_all_users(): void
    {
        // Create an extra user to ensure the list is populated
        User::create([
            'name' => 'Test Driver',
            'email' => 'driver@fleet.test',
            'password' => Hash::make('password123'),
            'phone_no' => '0987654321',
            'role' => 'Driver',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => ['user_id', 'name', 'email', 'role', 'is_active']
                    ]
                ]
            ]);
    }

    /**
     * Test getting a single user by ID.
     */
    public function test_authenticated_user_can_get_a_single_user(): void
    {
        $targetUser = User::create([
            'name' => 'Youssef Mahmoud',
            'email' => 'youssef.mah@fleet.test',
            'password' => Hash::make('password123'),
            'phone_no' => '1112223333',
            'role' => 'Dispatcher',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/users/' . $targetUser->user_id);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'youssef.mah@fleet.test')
            ->assertJsonPath('data.name', 'Youssef Mahmoud');
    }

    /**
     * Test creating a new user.
     */
    public function test_authenticated_user_can_create_a_user(): void
    {
        $payload = [
            'name' => 'New Mechanic',
            'email' => 'mechanic.new@fleet.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'phone_no' => '5556667777',
            'role' => 'Mechanic',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'mechanic.new@fleet.test');

        $this->assertDatabaseHas('users', [
            'email' => 'mechanic.new@fleet.test',
            'role' => 'Mechanic',
        ]);
    }

    /**
     * Test updating an existing user.
     */
    public function test_authenticated_user_can_update_a_user(): void
    {
        $targetUser = User::create([
            'name' => 'Old Name',
            'email' => 'update.me@fleet.test',
            'password' => Hash::make('password123'),
            'phone_no' => '9998887777',
            'role' => 'Driver',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Updated Name',
            'email' => 'update.me@fleet.test',
            'phone_no' => '0000000000',
            'role' => 'Driver',
            'status' => 'inactive',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson('/api/v1/users/' . $targetUser->user_id, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'user_id' => $targetUser->user_id,
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
    }
}
