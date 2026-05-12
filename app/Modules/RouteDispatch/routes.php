<?php

/**
 * @file: routes.php
 * @description: Route & Dispatch Service Routes (Complete)
 * @module: RouteDispatch
 * @author: Team Leader (Khalid)
 */

use Illuminate\Support\Facades\Route;
use App\Modules\RouteDispatch\Controllers\RouteController;
use App\Modules\RouteDispatch\Controllers\RouteStopController;
use App\Modules\RouteDispatch\Controllers\VehicleController;
use App\Modules\RouteDispatch\Controllers\DispatchController;

Route::prefix('api/v1/dispatch')->middleware('auth:sanctum')->group(function () {

    // =====================================================================
    // Route Management
    // =====================================================================
    Route::prefix('routes')->group(function () {

        // Specialized routes MUST come before /{id}
        Route::get('/driver/{driverId}', [RouteController::class, 'driverRoutes'])
            ->name('dispatch.routes.driver')
            ->where('driverId', '[0-9]+');

        // CRUD
        Route::get('/',        [RouteController::class, 'index'])->name('dispatch.routes.index');
        Route::post('/',       [RouteController::class, 'store'])->name('dispatch.routes.store');
        Route::get('/{id}',    [RouteController::class, 'show'])->name('dispatch.routes.show')->where('id', '[0-9]+');
        Route::put('/{id}',    [RouteController::class, 'update'])->name('dispatch.routes.update')->where('id', '[0-9]+');
        Route::delete('/{id}', [RouteController::class, 'destroy'])->name('dispatch.routes.destroy')->where('id', '[0-9]+');

        // Route Lifecycle
        Route::post('/{id}/start',    [RouteController::class, 'startRoute'])->name('dispatch.routes.start')->where('id', '[0-9]+');
        Route::post('/{id}/complete', [RouteController::class, 'completeRoute'])->name('dispatch.routes.complete')->where('id', '[0-9]+');

        // Optimization & Dispatch Operations
        Route::post('/optimize',[RouteController::class, 'optimizeRoute'])->name('dispatch.routes.optimize');
        Route::post('/{id}/insert-urgent',    [RouteController::class, 'insertUrgentOrder'])->name('dispatch.routes.insert-urgent')->where('id', '[0-9]+');
        Route::post('/{id}/shift-transition', [RouteController::class, 'shiftTransition'])->name('dispatch.routes.shift-transition')->where('id', '[0-9]+');

        // Route Stops (nested under route)
        // GET  /api/v1/dispatch/routes/{routeId}/stops
        Route::get('/{routeId}/stops',           [RouteStopController::class, 'index'])->name('dispatch.routes.stops.index')->where('routeId', '[0-9]+');
        // POST /api/v1/dispatch/routes/{routeId}/stops
        Route::post('/{routeId}/stops',          [RouteStopController::class, 'store'])->name('dispatch.routes.stops.store')->where('routeId', '[0-9]+');
        // PUT  /api/v1/dispatch/routes/{routeId}/stops/reorder
        Route::put('/{routeId}/stops/reorder',   [RouteStopController::class, 'reorder'])->name('dispatch.routes.stops.reorder')->where('routeId', '[0-9]+');
    });

    // =====================================================================
    // Route Stop Actions (not nested under a specific route)
    // =====================================================================
    Route::prefix('stops')->group(function () {
        // GET /api/v1/dispatch/stops/{stopId}
        Route::get('/{stopId}', [RouteStopController::class, 'show'])->name('dispatch.stops.show')->where('stopId', '[0-9]+');
        // PATCH /api/v1/dispatch/stops/{stopId}/status
        Route::patch('/{stopId}/status', [RouteStopController::class, 'updateStatus'])->name('dispatch.stops.status')->where('stopId', '[0-9]+');
        // DELETE /api/v1/dispatch/stops/{stopId}
        Route::delete('/{stopId}', [RouteStopController::class, 'destroy'])->name('dispatch.stops.destroy')->where('stopId', '[0-9]+');
    });

    // =====================================================================
    // Vehicle Management
    // =====================================================================
    Route::prefix('vehicles')->group(function () {

        // Specialized routes first
        Route::get('/available', [VehicleController::class, 'available'])->name('dispatch.vehicles.available');

        // CRUD
        Route::get('/',        [VehicleController::class, 'index'])->name('dispatch.vehicles.index');
        Route::post('/',       [VehicleController::class, 'store'])->name('dispatch.vehicles.store');
        Route::get('/{id}',    [VehicleController::class, 'show'])->name('dispatch.vehicles.show')->where('id', '[0-9]+');
        Route::put('/{id}',    [VehicleController::class, 'update'])->name('dispatch.vehicles.update')->where('id', '[0-9]+');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->name('dispatch.vehicles.destroy')->where('id', '[0-9]+');

        // Vehicle Status
        Route::post('/{id}/lock',   [VehicleController::class, 'lock'])->name('dispatch.vehicles.lock')->where('id', '[0-9]+');
        Route::post('/{id}/unlock', [VehicleController::class, 'unlock'])->name('dispatch.vehicles.unlock')->where('id', '[0-9]+');
    });

    // =====================================================================
    // Dispatch & Assignment Operations
    // =====================================================================

    // GET /api/v1/dispatch/live-snapshot  (Unified endpoint for Live Monitoring)
    Route::get('/live-snapshot', [DispatchController::class, 'liveSnapshot'])->name('dispatch.live-snapshot');

    // POST /api/v1/dispatch/assign  (Assign driver + vehicle to route - RD-01 / fn01)
    Route::post('/assign', [DispatchController::class, 'assign'])->name('dispatch.assign');

    // POST /api/v1/dispatch/redistribute  (RD-07 / fn04)
    Route::post('/redistribute', [DispatchController::class, 'redistribute'])->name('dispatch.redistribute');

    // POST /api/v1/dispatch/cluster-orders  (RD-02 / fn02)
    Route::post('/cluster-orders', [DispatchController::class, 'clusterOrders'])->name('dispatch.cluster-orders');

    // POST /api/v1/dispatch/capacity-check  (RD-03 / fn03)
    Route::post('/capacity-check', [DispatchController::class, 'capacityCheck'])->name('dispatch.capacity-check');

    // POST /api/v1/dispatch/priority-score  (order priority scoring stub)
    Route::post('/priority-score', [DispatchController::class, 'priorityScore'])->name('dispatch.priority-score');

    // GET  /api/v1/dispatch/license-check  (RD-09 / fn08)
    Route::get('/license-check', [DispatchController::class, 'licenseCheck'])->name('dispatch.license-check');

    // GET  /api/v1/dispatch/drivers/{driverId}/availability
    Route::get('/drivers/{driverId}/availability', [DispatchController::class, 'driverAvailability'])
        ->name('dispatch.driver.availability')
        ->where('driverId', '[0-9]+');

    Route::prefix('fleet')->group(function () {

        // POST /api/v1/dispatch/fleet/vehicles  ← Add new vehicle from Fleet screen
        Route::post('/vehicles', [VehicleController::class, 'storeFleetVehicle'])
            ->name('dispatch.fleet.vehicles.store');

        // PUT/PATCH /api/v1/dispatch/fleet/vehicles/{id} ← Update vehicle
        Route::match(['put', 'patch'], '/vehicles/{id}', [VehicleController::class, 'updateFleetVehicle'])
            ->name('dispatch.fleet.vehicles.update')
            ->where('id', '[0-9]+');

        // GET /api/v1/dispatch/fleet/vehicles/{id}  ← detail (must precede the list route)
        Route::get('/vehicles/{id}', [VehicleController::class, 'fleetVehicleDetail'])
            ->name('dispatch.fleet.vehicles.detail')
            ->where('id', '[0-9]+');

        // GET /api/v1/dispatch/fleet/vehicles  ← list
        Route::get('/vehicles', [VehicleController::class, 'fleetVehicles'])
            ->name('dispatch.fleet.vehicles.fleet-screen');

        Route::get('/drivers', [\App\Modules\RouteDispatch\Controllers\DriverController::class, 'index'])
            ->name('dispatch.fleet.drivers.fleet-screen');
        
        Route::get('/drivers/{id}', [\App\Modules\RouteDispatch\Controllers\DriverController::class, 'show'])
            ->name('dispatch.fleet.drivers.show')
            ->where('id', '[0-9]+');

        Route::post('/drivers', [\App\Modules\RouteDispatch\Controllers\DriverController::class, 'store'])
            ->name('dispatch.fleet.drivers.store');
    });
});