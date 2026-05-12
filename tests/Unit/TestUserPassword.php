<?php

namespace Tests\Unit\AuthIdentity;

use App\Modules\AuthIdentity\Models\User;
use App\Modules\AuthIdentity\Repositories\UserRepository;
use App\Modules\AuthIdentity\Services\UserService;
use App\Modules\LoggingAudit\Services\AuditService;
use App\Modules\LoggingAudit\Services\LogService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class TestUserPassword extends TestCase
{
    public function test_password_is_hashed_on_create(): void
    {
        $plainPassword = 'Secret1234';
        $capturedData = [];

        $userRepository = Mockery::mock(UserRepository::class);
        $logService = Mockery::mock(LogService::class);
        $auditService = Mockery::mock(AuditService::class);
        $notificationService = Mockery::mock(NotificationService::class);

        $userRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }))
            ->andReturn(tap(new User(['role' => 'FleetManager']), function (User $user) {
                $user->user_id = 1;
            }));

        $auditService->shouldReceive('log')->once();
        $logService->shouldReceive('info')->once();
        $notificationService->shouldReceive('send')->once();

        $service = new UserService(
            $userRepository,
            $logService,
            $auditService,
            $notificationService
        );

        $service->createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $plainPassword,
            'role' => 'FleetManager',
        ]);

        $this->assertArrayHasKey('password', $capturedData);
        $this->assertNotSame($plainPassword, $capturedData['password']);
        $this->assertTrue(Hash::check($plainPassword, $capturedData['password']));
    }
}
