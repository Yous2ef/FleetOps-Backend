<?php

/**
 * @file: routes.php
 * @description: Maintenance Service Routes (Complete)
 * @module: Maintenance
 * @author: Team Leader (Khalid)
 */

use Illuminate\Support\Facades\Route;
use App\Modules\Maintenance\Controllers\WorkOrderController;
use App\Modules\Maintenance\Controllers\SparePartController;
use App\Modules\Maintenance\Controllers\VehicleInspectionController;
use App\Modules\Maintenance\Controllers\DashboardController;
use App\Modules\Maintenance\Controllers\EmergencyDispatchController;
use App\Modules\Maintenance\Controllers\AlertsController;

Route::prefix('api/v1/maintenance')->middleware('auth:sanctum')->group(function () {

    // =====================================================================
    // Dashboard
    // =====================================================================
    Route::get('/dashboard-summary', [DashboardController::class, 'getDashboardSummary'])->name('maintenance.dashboard-summary');

    // =====================================================================
    // Maintenance Vehicles
    // =====================================================================
    Route::get('/vehicles', [\App\Modules\RouteDispatch\Controllers\VehicleController::class, 'maintenanceVehicles'])
        ->name('maintenance.vehicles.index');
    // =====================================================================
    // Work Orders (MT-02/03/04/06)
    // =====================================================================
    Route::prefix('work-orders')->group(function () {

        // Specialized routes first
        Route::get('/open',                  [WorkOrderController::class, 'openOrders'])->name('maintenance.work-orders.open');
        Route::get('/vehicle/{vehicleId}',   [WorkOrderController::class, 'forVehicle'])->name('maintenance.work-orders.by-vehicle')->where('vehicleId', '[0-9]+');
        Route::get('/mechanic/{mechanicId}', [WorkOrderController::class, 'forMechanic'])->name('maintenance.work-orders.by-mechanic')->where('mechanicId', '[0-9]+');

        // CRUD
        Route::get('/',        [WorkOrderController::class, 'index'])->name('maintenance.work-orders.index');
        Route::post('/',       [WorkOrderController::class, 'store'])->name('maintenance.work-orders.store');
        Route::get('/{id}',    [WorkOrderController::class, 'show'])->name('maintenance.work-orders.show')->where('id', '[0-9]+');
        Route::put('/{id}',    [WorkOrderController::class, 'update'])->name('maintenance.work-orders.update')->where('id', '[0-9]+');
        Route::delete('/{id}', [WorkOrderController::class, 'destroy'])->name('maintenance.work-orders.destroy')->where('id', '[0-9]+');

        // Work Order Actions
        Route::post('/{id}/assign',  [WorkOrderController::class, 'assignMechanic'])->name('maintenance.work-orders.assign')->where('id', '[0-9]+');
        Route::patch('/{id}/status', [WorkOrderController::class, 'updateStatus'])->name('maintenance.work-orders.status')->where('id', '[0-9]+');
        Route::post('/{id}/parts',   [WorkOrderController::class, 'recordParts'])->name('maintenance.work-orders.parts')->where('id', '[0-9]+');
    });

    // =====================================================================
    // Spare Parts Inventory (MT-05 / fn31)
    // =====================================================================
    Route::prefix('parts')->group(function () {

        // Specialized routes first
        Route::get('/low-stock', [SparePartController::class, 'lowStock'])->name('maintenance.parts.low-stock');

        // CRUD
        Route::get('/',        [SparePartController::class, 'index'])->name('maintenance.parts.index');
        Route::post('/',       [SparePartController::class, 'store'])->name('maintenance.parts.store');
        Route::get('/{id}',    [SparePartController::class, 'show'])->name('maintenance.parts.show')->where('id', '[0-9]+');
        Route::put('/{id}',    [SparePartController::class, 'update'])->name('maintenance.parts.update')->where('id', '[0-9]+');
        Route::delete('/{id}', [SparePartController::class, 'destroy'])->name('maintenance.parts.destroy')->where('id', '[0-9]+');

        // Stock Adjustment
        Route::post('/{id}/adjust-stock', [SparePartController::class, 'adjustStock'])->name('maintenance.parts.adjust-stock')->where('id', '[0-9]+');
    });

    // =====================================================================
    // Vehicle Inspections - Annual & Periodic (MT-07 / fn32)
    // =====================================================================
    Route::prefix('inspections')->group(function () {

        // Specialized routes first
        Route::get('/overdue',              [VehicleInspectionController::class, 'overdue'])->name('maintenance.inspections.overdue');
        Route::get('/upcoming',             [VehicleInspectionController::class, 'upcoming'])->name('maintenance.inspections.upcoming');
        Route::get('/vehicle/{vehicleId}',  [VehicleInspectionController::class, 'forVehicle'])->name('maintenance.inspections.by-vehicle')->where('vehicleId', '[0-9]+');

        // CRUD
        Route::get('/',     [VehicleInspectionController::class, 'index'])->name('maintenance.inspections.index');
        Route::post('/',    [VehicleInspectionController::class, 'store'])->name('maintenance.inspections.store');
        Route::get('/{id}', [VehicleInspectionController::class, 'show'])->name('maintenance.inspections.show')->where('id', '[0-9]+');
    });

    // =====================================================================
    // Emergency Dispatch
    // =====================================================================
    Route::prefix('emergency')->group(function () {
        Route::get('/incidents', [EmergencyDispatchController::class, 'incidents'])->name('maintenance.emergency.incidents');
        Route::get('/incident-details/{id}', [EmergencyDispatchController::class, 'incidentDetails'])->name('maintenance.emergency.incident-details')->where('id', '[0-9]+');
        Route::get('/nearby-mechanics/{id}', [EmergencyDispatchController::class, 'nearbyMechanics'])->name('maintenance.emergency.nearby-mechanics')->where('id', '[0-9]+');
        Route::post('/dispatch-mechanic/{incident_id}', [EmergencyDispatchController::class, 'dispatchMechanic'])->name('maintenance.emergency.dispatch-mechanic');
    });

    // =====================================================================
    // Alerts
    // =====================================================================
    Route::prefix('alerts')->group(function () {
        Route::get('/odometer', [AlertsController::class, 'odometerAlerts'])->name('maintenance.alerts.odometer');
        Route::get('/insurance', [AlertsController::class, 'insuranceAlerts'])->name('maintenance.alerts.insurance');
        Route::get('/inspection', [AlertsController::class, 'inspectionAlerts'])->name('maintenance.alerts.inspection');
        Route::get('/parts', [AlertsController::class, 'partsAlerts'])->name('maintenance.alerts.parts');

        Route::patch('/insurance-renew/{id}', [AlertsController::class, 'renewInsurance'])->name('maintenance.alerts.insurance-renew')->where('id', '[0-9]+');
        Route::patch('/inspection-complete/{id}', [AlertsController::class, 'completeInspection'])->name('maintenance.alerts.inspection-complete')->where('id', '[0-9]+');
    });
});
