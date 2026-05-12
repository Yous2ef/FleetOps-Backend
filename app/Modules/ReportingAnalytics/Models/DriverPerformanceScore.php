<?php

/**
 * @file: DriverPerformanceScore.php
 * @description: نموذج Eloquent لنقاط أداء السائقين - Reporting & Analytics Service (AN-02 / fn22)
 * @module: ReportingAnalytics
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DriverPerformanceScore extends Model
{
    use HasFactory;

    protected $table = 'driver_performance';
    protected $primaryKey = 'performance_id';
    public $incrementing = true;

    protected $fillable = [
        'driver_id',
        'period_start',
        'period_end',
        'punctuality_rate',
        'fuel_efficiency',
        'customer_rating',
        'performance_score',
        'total_deliveries',
        'successful_deliveries',
        'breakdown',
    ];

    protected $casts = [
        'punctuality_rate'       => 'float',
        'fuel_efficiency'        => 'float',
        'customer_rating'        => 'float',
        'performance_score'      => 'float',
        'total_deliveries'       => 'integer',
        'successful_deliveries'  => 'integer',
        'breakdown'              => 'array',
        'period_start'           => 'datetime',
        'period_end'             => 'datetime',
        'created_at'             => 'datetime',
    ];
}