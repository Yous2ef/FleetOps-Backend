<?php

/**
 * @file: routes.php
 * @description: Reporting & Analytics Service Routes (Complete)
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

use Illuminate\Support\Facades\Route;
use App\Modules\ReportingAnalytics\Controllers\KpiController;
use App\Modules\ReportingAnalytics\Controllers\ReportController;
use App\Modules\ReportingAnalytics\Controllers\incidentReportController;
use App\Modules\ReportingAnalytics\Controllers\FuelController;
use App\Modules\ReportingAnalytics\Controllers\CashLedgerController;

Route::prefix('api/v1/analytics')->middleware('auth:sanctum')->group(function () {

    // ══════════════════════════════════════════════════════════════════════════
    // Analytics Page Endpoints
    // ══════════════════════════════════════════════════════════════════════════

    // GET /api/v1/analytics/analytics-kpis?range=today|7d|30d
    Route::get('/analytics-kpis',              [KpiController::class, 'analyticsKpis'])->name('analytics.analytics-kpis');

    // GET /api/v1/analytics/analytics-fleet-distribution
    Route::get('/analytics-fleet-distribution',[KpiController::class, 'fleetDistribution'])->name('analytics.fleet-distribution');

    // GET /api/v1/analytics/analytics-fuel-audit?range=today|7d|30d
    Route::get('/analytics-fuel-audit',        [KpiController::class, 'fuelAudit'])->name('analytics.fuel-audit');

    // GET /api/v1/analytics/analytics-revenue-chart?months=6
    Route::get('/analytics-revenue-chart',     [ReportController::class, 'revenueChart'])->name('analytics.revenue-chart');

    // GET /api/v1/analytics/analytics-maintenance-cost?period_start=&period_end=
    Route::get('/analytics-maintenance-cost',  [ReportController::class, 'maintenanceCost'])->name('analytics.maintenance-cost');

    // ══════════════════════════════════════════════════════════════════════════
    // Fuel & Efficiency Page (Fuel Efficiency frontend page)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('fuel')->group(function () {
        // GET  /api/v1/analytics/fuel/audit?period_start=YYYY-MM-DD&period_end=YYYY-MM-DD
        Route::get('/audit',      [FuelController::class, 'audit'])->name('analytics.fuel.audit');

        // GET  /api/v1/analytics/fuel/efficiency?period_start=YYYY-MM-DD&period_end=YYYY-MM-DD
        Route::get('/efficiency', [FuelController::class, 'efficiency'])->name('analytics.fuel.efficiency');

        // POST /api/v1/analytics/fuel/invoices  — add a fuel invoice
        Route::post('/invoices',  [FuelController::class, 'storeInvoice'])->name('analytics.fuel.invoices.store');

        // GET  /api/v1/analytics/fuel/export?tab=audit|efficiency&period_start=...&period_end=...
        Route::get('/export',     [FuelController::class, 'export'])->name('analytics.fuel.export');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // KPIs & Metrics (AN-01/02/03/04/07)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('kpis')->group(function () {
        // Specialized routes (before wildcard)
        Route::get('/on-time-rate',               [KpiController::class, 'onTimeRate'])->name('analytics.kpis.on-time-rate');
        Route::get('/co2-report',                 [KpiController::class, 'co2Report'])->name('analytics.kpis.co2-report');
        Route::get('/anomalies',                  [KpiController::class, 'anomalies'])->name('analytics.kpis.anomalies');
        Route::get('/driver-score/{driverId}',    [KpiController::class, 'driverScore'])->name('analytics.kpis.driver-score')->where('driverId', '[0-9]+');

        // Paginated KPI snapshot list
        Route::get('/', [KpiController::class, 'index'])->name('analytics.kpis.index');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Reports (AN-04/05/06)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('reports')->group(function () {
        // GET /api/v1/analytics/reports/daily-dashboard
        Route::get('/daily-dashboard',   [ReportController::class, 'dailyDashboard'])->name('analytics.reports.daily-dashboard');

        // GET /api/v1/analytics/reports/delivery-summary
        Route::get('/delivery-summary',  [ReportController::class, 'deliverySummary'])->name('analytics.reports.delivery-summary');

        // GET /api/v1/analytics/reports/maintenance-cost
        Route::get('/maintenance-cost',  [ReportController::class, 'maintenanceCost'])->name('analytics.reports.maintenance-cost');

        // GET /api/v1/analytics/reports/driver-leaderboard  (AN-05)
        Route::get('/driver-leaderboard',[ReportController::class, 'driverLeaderboard'])->name('analytics.reports.driver-leaderboard');

        // POST /api/v1/analytics/reports/export  (AN-06 / fn42)
        Route::post('/export',           [ReportController::class, 'export'])->name('analytics.reports.export');

        // POST /api/v1/analytics/reports/incidents-reports
        Route::post('/incidents-reports', [incidentReportController::class, 'createIncidentReport'])->name('analytics.reports.incidents-reports');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Cash Ledger (COD Reconciliation)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('reconciliation')->group(function () {
        Route::get('/summary/{routeId}', [CashLedgerController::class, 'getSummary'])->name('analytics.reconciliation.summary');
        Route::post('/submit', [CashLedgerController::class, 'submitReconciliation'])->name('analytics.reconciliation.submit');
    });
});

