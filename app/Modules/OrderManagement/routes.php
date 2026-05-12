<?php

/**
 * @file: routes.php
 * @description: Order Management Service Routes
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

use Illuminate\Support\Facades\Route;
use App\Modules\OrderManagement\Controllers\OrderController;
use App\Modules\OrderManagement\Controllers\ProofOfDeliveryController;
use App\Modules\OrderManagement\Controllers\InspectionController;
use App\Modules\OrderManagement\Controllers\CustomerPortalController;
use App\Modules\OrderManagement\Controllers\CustomerTrackingController;
use App\Modules\OrderManagement\Controllers\CodController;

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {

    // =====================================================================
    // Order Management
    // =====================================================================
    Route::prefix('orders')->group(function () {

        // Specialized routes MUST come before /{id}
        Route::post('/import',              [OrderController::class, 'bulkImport'])->name('orders.import');
        Route::get('/route/{routeId}',      [OrderController::class, 'routeOrders'])->name('orders.by-route')->where('routeId', '[0-9]+');
        Route::get('/driver/{driverId}',    [OrderController::class, 'driverOrders'])->name('orders.by-driver')->where('driverId', '[0-9]+');

        // Pre-Trip Inspections (fn12)
        Route::get('/inspections/vehicle/{vehicleId}', [InspectionController::class, 'forVehicle'])->name('inspections.by-vehicle')->where('vehicleId', '[0-9]+');
        Route::get('/inspections/route/{routeId}',     [InspectionController::class, 'forRoute'])->name('inspections.by-route')->where('routeId', '[0-9]+');
        Route::post('/inspections',                    [InspectionController::class, 'store'])->name('inspections.store');

        // CRUD
        Route::get('/',     [OrderController::class, 'index'])->name('orders.index');
        Route::post('/',    [OrderController::class, 'store'])->name('orders.store');
        Route::get('/{id}', [OrderController::class, 'show'])->name('orders.show')->where('id', '[0-9]+');
        Route::put('/{id}', [OrderController::class, 'update'])->name('orders.update')->where('id', '[0-9]+');
        Route::delete('/{id}', [OrderController::class, 'destroy'])->name('orders.destroy')->where('id', '[0-9]+');
        Route::get('/{status}', [OrderController::class, 'getByStatus'])->name('orders.by-status')->where('status', '[a-zA-Z]+');
        Route::get('/cash/{driverId}', [OrderController::class, 'driverCashOrders'])->name('orders.cash')->where('driverId', '[0-9]+');

        // Order State Machine Actions
        Route::patch('/{id}/status',     [OrderController::class, 'updateStatus'])->name('orders.update-status')->where('id', '[0-9]+');
        Route::post('/{id}/verify-qr',   [OrderController::class, 'verifyQr'])->name('orders.verify-qr')->where('id', '[0-9]+');
        Route::post('/{id}/return',      [OrderController::class, 'initiateReturn'])->name('orders.return')->where('id', '[0-9]+');

        // Proof of Delivery (fn13/14)
        Route::get('/{orderId}/pod',  [ProofOfDeliveryController::class, 'show'])->name('orders.pod.show')->where('orderId', '[0-9]+');
        Route::post('/{orderId}/pod', [ProofOfDeliveryController::class, 'store'])->name('orders.pod.store')->where('orderId', '[0-9]+');
    });

    // =====================================================================
    // COD Management
    // =====================================================================
    Route::prefix('cod')->group(function () {
        Route::get('/', [CodController::class, 'index'])->name('cod.index');
        Route::get('/{id}', [CodController::class, 'show'])->name('cod.show');
        Route::patch('/{id}/handover', [CodController::class, 'markHandover'])->name('cod.handover');
    });
});

// =============================================================================
// Customer Portal (public – validated by tracking token, no sanctum auth)
// =============================================================================
Route::prefix('api/customer-portal')->name('customer-portal.')->group(function () {

    Route::get('orders/{token}/validate',     [CustomerPortalController::class, 'validateToken'])
        ->name('validate-token')
        ->middleware('throttle:60,1');

    Route::get('orders/{token}',              [CustomerPortalController::class, 'getOrderDetails'])
        ->name('order-details')
        ->middleware('throttle:60,1');

    Route::get('orders/{token}/tracking',     [CustomerPortalController::class, 'getTrackingData'])
        ->name('tracking')
        ->middleware('throttle:120,1');

    Route::put('orders/{token}/instructions', [CustomerPortalController::class, 'updateDeliveryInstructions'])
        ->name('update-instructions')
        ->middleware('throttle:30,1');

    Route::get('orders/{token}/arrival',      [CustomerPortalController::class, 'getArrivalDetails'])
        ->name('arrival-details')
        ->middleware('throttle:60,1');

    Route::post('orders/{token}/ready',       [CustomerPortalController::class, 'confirmCustomerReady'])
        ->name('confirm-ready')
        ->middleware('throttle:10,1');

    Route::get('orders/{token}/delivery',     [CustomerPortalController::class, 'getDeliveryProof'])
        ->name('delivery-proof')
        ->middleware('throttle:30,1');

    Route::post('orders/{token}/feedback',    [CustomerPortalController::class, 'submitFeedback'])
        ->name('submit-feedback')
        ->middleware('throttle:5,1');

    Route::get('orders/{token}/attempt',      [CustomerPortalController::class, 'getUnsuccessfulAttempt'])
        ->name('unsuccessful-attempt')
        ->middleware('throttle:30,1');


    Route::get('support',                     [CustomerPortalController::class, 'getSupportInfo'])
        ->name('support-info')
        ->middleware('throttle:30,1');
});

// =============================================================================
// Customer Tracking API  (api/v1/customer — distinct from legacy customer-portal)
// Public — no auth:sanctum; token-level security enforced in service layer.
// =============================================================================
Route::prefix('api/v1/customer')
    ->name('customer.tracking.')
    ->group(function () {

        // GET  api/v1/customer/tracking/{tracking_code}
        // ── Returns full order snapshot: status, ETA, address, driver, timeline, items.
        Route::get('/tracking/{tracking_code}', [CustomerTrackingController::class, 'show'])
            ->name('show')
            ->middleware('throttle:60,1');

        // POST api/v1/customer/tracking/{tracking_code}/preferences
        // ── Save delivery preferences (ring doorbell, leave at door, notes).
        Route::post('/tracking/{tracking_code}/preferences', [CustomerTrackingController::class, 'storePreferences'])
            ->name('preferences')
            ->middleware('throttle:30,1');

        // POST api/v1/customer/tracking/{tracking_code}/feedback
        // ── Submit post-delivery feedback (rating 1-5, condition, comments).
        Route::post('/tracking/{tracking_code}/feedback', [CustomerTrackingController::class, 'storeFeedback'])
            ->name('feedback')
            ->middleware('throttle:5,1');
    });
