<?php

/**
 * @file IncidentReport.php
 * @description Eloquent Model for the incident_reports table — ReportingAnalytics Module
 * @module ReportingAnalytics
 * @table incident_reports
 * @author Team Leader (Khalid)
 */

namespace App\Modules\ReportingAnalytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\AuthIdentity\Models\Driver;
use App\Modules\RouteDispatch\Models\Vehicle;

class IncidentReport extends Model
{
    use HasFactory;

    protected $table      = 'incident_reports';
    protected $primaryKey = 'incident_id';
    protected $keyType    = 'int';
    public $incrementing  = true;

    // DDL has created_at only (no updated_at); also has incident_ts
    const UPDATED_AT = null;
    const CREATED_AT = 'created_at';

    /** @var array<string> */
    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'type',
        'severity',
        'description',
        'status',
        'latitude',
        'longitude',
        'photo_urls',   // JSON field
        'incident_ts',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'photo_urls'  => 'array',   // DDL: JSON validated by CK_IR_Photos
        'incident_ts' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Driver involved in the incident */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'driver_id');
    }

    /** Vehicle involved in the incident */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    /** The maintenance assignment linked to this incident */
    public function maintenanceAssignment()
    {
        return $this->hasOne(\App\Modules\Maintenance\Models\MaintenanceAssignment::class, 'incident_id', 'incident_id');
    }
}
