<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file is loaded by the application bootstrap so Laravel can register
| API routes without failing when the file is missing.
|
*/

Route::middleware('api')->group(function (): void {
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'fleetops-backend',
            'timestamp' => now()->toIso8601String(),
            'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        ]);
    });

    // API routes will be registered here.
    Route::get('/v1/vehicles', [\App\Modules\RouteDispatch\Controllers\VehicleController::class, 'index']);
});