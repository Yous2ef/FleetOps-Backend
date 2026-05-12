<?php

/**
 * @file: ModuleServiceProvider.php
 * @description: مسؤول عن تسجيل كل الموديولات والـ Repositories كـ Singletons
 * وتحميل الـ Routes تلقائياً (Service Locator Pattern)
 * @author: Team Leader (Khalid)
 * @version: 3.0  — Updated for new 8-service architecture
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * قائمة الموديولات الجديدة (مطابقة لأسماء الخدمات في Project.md)
     * @var array
     */
    protected $modules = [
        'AuthIdentity',
        'RealtimeTracking',
        'RouteDispatch',
        'OrderManagement',
        'Notification',
        'Maintenance',
        'ReportingAnalytics',
        'LoggingAudit',
        'StartFromHere',
        'Shared',
    ];

    /**
     * تسجيل الـ Repositories والـ Services كـ Singletons
     * Singleton يضمن instance واحد فقط لكل App Lifecycle
     * @return void
     */
    public function register(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // Module 1: Auth & Identity Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\AuthIdentity\Repositories\UserRepository::class,
            fn() => new \App\Modules\AuthIdentity\Repositories\UserRepository(
                new \App\Modules\AuthIdentity\Models\User()
            )
        );

        // $this->app->singleton(
        //     \App\Modules\AuthIdentity\Repositories\RoleRepository::class,
        //     fn () => new \App\Modules\AuthIdentity\Repositories\RoleRepository(
        //         new \App\Modules\AuthIdentity\Models\Role()
        //     )
        // );

        $this->app->singleton(
            \App\Modules\AuthIdentity\Services\AuthService::class,
            fn($app) => new \App\Modules\AuthIdentity\Services\AuthService(
                $app->make(\App\Modules\AuthIdentity\Repositories\UserRepository::class),
                $app->make(\App\Modules\LoggingAudit\Services\LogService::class),
                $app->make(\App\Modules\LoggingAudit\Services\AuditService::class)
            )
        );

        $this->app->singleton(
            \App\Modules\AuthIdentity\Services\UserService::class,
            fn($app) => new \App\Modules\AuthIdentity\Services\UserService(
                $app->make(\App\Modules\AuthIdentity\Repositories\UserRepository::class),
                $app->make(\App\Modules\LoggingAudit\Services\LogService::class),
                $app->make(\App\Modules\LoggingAudit\Services\AuditService::class),
                $app->make(\App\Modules\Notification\Services\NotificationService::class)
            )
        );

        // $this->app->singleton(
        //     \App\Modules\AuthIdentity\Services\RBACService::class,
        //     fn($app) => new \App\Modules\AuthIdentity\Services\RBACService(
        //         $app->make(\App\Modules\AuthIdentity\Repositories\RoleRepository::class),
        //         $app->make(\App\Modules\AuthIdentity\Repositories\UserRepository::class),
        //         $app->make(\App\Modules\LoggingAudit\Services\AuditService::class)
        //     )
        // );

        // ══════════════════════════════════════════════════════════════════════
        // Module 2: Real-time Tracking & GPS Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Repositories\GpsPingRepository::class,
            fn() => new \App\Modules\RealtimeTracking\Repositories\GpsPingRepository(
                new \App\Modules\RealtimeTracking\Models\GpsPing()
            )
        );

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Repositories\GeofenceRepository::class,
            fn() => new \App\Modules\RealtimeTracking\Repositories\GeofenceRepository(
                new \App\Modules\RealtimeTracking\Models\Geofence()
            )
        );

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Repositories\TrackingLinkRepository::class,
            fn() => new \App\Modules\RealtimeTracking\Repositories\TrackingLinkRepository(
                new \App\Modules\RealtimeTracking\Models\TrackingLink()
            )
        );

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Services\LocationService::class,
            fn($app) => new \App\Modules\RealtimeTracking\Services\LocationService(
                $app->make(\App\Modules\RealtimeTracking\Repositories\GpsPingRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Services\GeofenceService::class,
            fn($app) => new \App\Modules\RealtimeTracking\Services\GeofenceService(
                $app->make(\App\Modules\RealtimeTracking\Repositories\GeofenceRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\RealtimeTracking\Services\TrackingLinkService::class,
            fn($app) => new \App\Modules\RealtimeTracking\Services\TrackingLinkService(
                $app->make(\App\Modules\RealtimeTracking\Repositories\TrackingLinkRepository::class)
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 3: Route & Dispatch Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\RouteDispatch\Repositories\RouteRepository::class,
            fn() => new \App\Modules\RouteDispatch\Repositories\RouteRepository(
                new \App\Modules\RouteDispatch\Models\Route()
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Repositories\VehicleRepository::class,
            fn() => new \App\Modules\RouteDispatch\Repositories\VehicleRepository(
                new \App\Modules\RouteDispatch\Models\Vehicle()
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Services\RouteService::class,
            fn($app) => new \App\Modules\RouteDispatch\Services\RouteService(
                $app->make(\App\Modules\RouteDispatch\Repositories\RouteRepository::class),
                $app->make(\App\Modules\RouteDispatch\Repositories\VehicleRepository::class),
                $app->make(\App\Modules\RouteDispatch\Repositories\RouteStopRepository::class),
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Services\RouteOptimizationService::class,
            fn($app) => new \App\Modules\RouteDispatch\Services\RouteOptimizationService(
                $app->make(\App\Modules\RouteDispatch\Repositories\RouteRepository::class),
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class),
                $app->make(\App\Modules\RouteDispatch\Repositories\VehicleRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Services\VehicleService::class,
            fn($app) => new \App\Modules\RouteDispatch\Services\VehicleService(
                $app->make(\App\Modules\RouteDispatch\Repositories\VehicleRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Repositories\RouteStopRepository::class,
            fn() => new \App\Modules\RouteDispatch\Repositories\RouteStopRepository(
                new \App\Modules\RouteDispatch\Models\RouteStop()
            )
        );

        $this->app->singleton(
            \App\Modules\RouteDispatch\Services\DispatchService::class,
            fn($app) => new \App\Modules\RouteDispatch\Services\DispatchService(
                $app->make(\App\Modules\RouteDispatch\Repositories\RouteRepository::class),
                $app->make(\App\Modules\RouteDispatch\Repositories\VehicleRepository::class),
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class)
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 4: Order Management Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\OrderManagement\Repositories\OrderRepository::class,
            fn() => new \App\Modules\OrderManagement\Repositories\OrderRepository(
                new \App\Modules\OrderManagement\Models\Order()
            )
        );

        $this->app->singleton(
            \App\Modules\OrderManagement\Services\OrderService::class,
            fn($app) => new \App\Modules\OrderManagement\Services\OrderService(
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\OrderManagement\Services\OrderImportService::class,
            fn($app) => new \App\Modules\OrderManagement\Services\OrderImportService(
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\OrderManagement\Services\ProofOfDeliveryService::class,
            fn($app) => new \App\Modules\OrderManagement\Services\ProofOfDeliveryService(
                $app->make(\App\Modules\OrderManagement\Repositories\OrderRepository::class),
                $app->make(\App\Modules\OrderManagement\Repositories\ProofOfDeliveryRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\OrderManagement\Repositories\ProofOfDeliveryRepository::class,
            fn() => new \App\Modules\OrderManagement\Repositories\ProofOfDeliveryRepository(
                new \App\Modules\OrderManagement\Models\ProofOfDelivery()
            )
        );

        $this->app->singleton(
            \App\Modules\OrderManagement\Repositories\InspectionRepository::class,
            fn() => new \App\Modules\OrderManagement\Repositories\InspectionRepository(
                new \App\Modules\OrderManagement\Models\PreTripInspection()
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 5: Notification Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\Notification\Repositories\NotificationRepository::class,
            fn() => new \App\Modules\Notification\Repositories\NotificationRepository(
                new \App\Modules\Notification\Models\Notification()
            )
        );

        $this->app->singleton(
            \App\Modules\Notification\Services\NotificationService::class,
            fn($app) => new \App\Modules\Notification\Services\NotificationService(
                $app->make(\App\Modules\Notification\Repositories\NotificationRepository::class),
                $app->make(\App\Modules\Notification\Repositories\NotificationPreferenceRepository::class),
                $app->make(\App\Modules\Notification\Services\TemplateService::class)
            )
        );

        $this->app->singleton(
            \App\Modules\Notification\Repositories\NotificationPreferenceRepository::class,
            fn() => new \App\Modules\Notification\Repositories\NotificationPreferenceRepository(
                new \App\Modules\Notification\Models\NotificationPreference()
            )
        );

        $this->app->singleton(
            \App\Modules\Notification\Services\TemplateService::class,
            fn() => new \App\Modules\Notification\Services\TemplateService()
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 6: Maintenance Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\Maintenance\Repositories\WorkOrderRepository::class,
            fn() => new \App\Modules\Maintenance\Repositories\WorkOrderRepository(
                new \App\Modules\Maintenance\Models\WorkOrder()
            )
        );

        $this->app->singleton(
            \App\Modules\Maintenance\Repositories\SparePartRepository::class,
            fn() => new \App\Modules\Maintenance\Repositories\SparePartRepository(
                new \App\Modules\Maintenance\Models\SparePart()
            )
        );

        $this->app->singleton(
            \App\Modules\Maintenance\Services\WorkOrderService::class,
            fn($app) => new \App\Modules\Maintenance\Services\WorkOrderService(
                $app->make(\App\Modules\Maintenance\Repositories\WorkOrderRepository::class),
                $app->make(\App\Modules\Maintenance\Repositories\SparePartRepository::class),
                $app->make(\App\Modules\LoggingAudit\Services\AuditService::class)
            )
        );

        $this->app->singleton(
            \App\Modules\Maintenance\Services\SparePartService::class,
            fn($app) => new \App\Modules\Maintenance\Services\SparePartService(
                $app->make(\App\Modules\Maintenance\Repositories\SparePartRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\Maintenance\Repositories\VehicleInspectionRepository::class,
            fn() => new \App\Modules\Maintenance\Repositories\VehicleInspectionRepository(
                new \App\Modules\Maintenance\Models\VehicleInspection()
            )
        );

        $this->app->singleton(
            \App\Modules\Maintenance\Services\VehicleInspectionService::class,
            fn($app) => new \App\Modules\Maintenance\Services\VehicleInspectionService(
                $app->make(\App\Modules\Maintenance\Repositories\VehicleInspectionRepository::class)
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 7: Reporting & Analytics Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Repositories\KpiRepository::class,
            fn() => new \App\Modules\ReportingAnalytics\Repositories\KpiRepository(
                new \App\Modules\ReportingAnalytics\Models\KpiSnapshot()
            )
        );

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Services\KpiService::class,
            fn() => new \App\Modules\ReportingAnalytics\Services\KpiService()
        );

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Repositories\DriverPerformanceRepository::class,
            fn() => new \App\Modules\ReportingAnalytics\Repositories\DriverPerformanceRepository(
                new \App\Modules\ReportingAnalytics\Models\DriverPerformanceScore()
            )
        );

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Services\ReportService::class,
            fn() => new \App\Modules\ReportingAnalytics\Services\ReportService()
        );

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Repositories\FuelAuditLogRepository::class,
            fn() => new \App\Modules\ReportingAnalytics\Repositories\FuelAuditLogRepository(
                new \App\Modules\ReportingAnalytics\Models\FuelAuditLog()
            )
        );

        $this->app->singleton(
            \App\Modules\ReportingAnalytics\Services\FuelService::class,
            fn($app) => new \App\Modules\ReportingAnalytics\Services\FuelService(
                $app->make(\App\Modules\ReportingAnalytics\Repositories\FuelAuditLogRepository::class)
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 8: Logging & Audit Service
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\LoggingAudit\Repositories\AuditLogRepository::class,
            fn() => new \App\Modules\LoggingAudit\Repositories\AuditLogRepository(
                new \App\Modules\LoggingAudit\Models\AuditLog()
            )
        );

        $this->app->singleton(
            \App\Modules\LoggingAudit\Services\AuditService::class,
            fn($app) => new \App\Modules\LoggingAudit\Services\AuditService(
                $app->make(\App\Modules\LoggingAudit\Repositories\AuditLogRepository::class)
            )
        );

        $this->app->singleton(
            \App\Modules\LoggingAudit\Repositories\SystemLogRepository::class,
            fn() => new \App\Modules\LoggingAudit\Repositories\SystemLogRepository(
                new \App\Modules\LoggingAudit\Models\SystemLog()
            )
        );

        $this->app->singleton(
            \App\Modules\LoggingAudit\Services\LogService::class,
            fn($app) => new \App\Modules\LoggingAudit\Services\LogService(
                $app->make(\App\Modules\LoggingAudit\Repositories\SystemLogRepository::class)
            )
        );

        // ══════════════════════════════════════════════════════════════════════
        // Module 9: StartFromHere (Reference Demo Module)
        // ══════════════════════════════════════════════════════════════════════

        $this->app->singleton(
            \App\Modules\StartFromHere\Repositories\StartRepository::class,
            fn() => new \App\Modules\StartFromHere\Repositories\StartRepository(
                new \App\Modules\StartFromHere\Models\Start()
            )
        );

        $this->app->singleton(
            \App\Modules\StartFromHere\Services\StartService::class,
            fn($app) => new \App\Modules\StartFromHere\Services\StartService(
                $app->make(\App\Modules\StartFromHere\Repositories\StartRepository::class)
            )
        );
    }

    /**
     * تحميل الـ Routes تلقائياً من كل موديول
     * يبحث عن routes.php في كل مجلد داخل app/Modules/ تلقائياً
     * بدون الحاجة لتعديل هذا الملف عند إضافة موديول جديد
     * @return void
     */
    public function boot(): void
    {
        $moduleRoutes = glob(app_path('Modules/*/routes.php'));
        foreach ($moduleRoutes as $routeFile) {
            \Illuminate\Support\Facades\Route::middleware('api')->group($routeFile);
        }
    }
    /**
     * الحصول على قائمة الموديولات المسجلة
     * @return array
     */
    public function getModules(): array
    {
        return $this->modules;
    }
}
