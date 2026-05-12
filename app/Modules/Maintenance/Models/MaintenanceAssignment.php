<?php

/**
 * @file MaintenanceAssignment.php
 * @description Eloquent Model for the maintenance_assignments table — Maintenance Module
 * @module Maintenance
 * @table maintenance_assignments
 * @author Team Leader (Khalid)
 */

namespace App\Modules\Maintenance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\RouteDispatch\Models\Vehicle;
use App\Modules\AuthIdentity\Models\FleetManager;
use App\Modules\AuthIdentity\Models\Mechanic;

class MaintenanceAssignment extends Model
{
    use HasFactory;

    protected $table      = 'maintenance_assignments';
    protected $primaryKey = 'assignment_id';
    protected $keyType    = 'int';
    public $incrementing  = true;

    // DDL has both created_at and updated_at (datetimeoffset)
    public $timestamps = true;
    const CREATED_AT   = 'created_at';
    const UPDATED_AT   = 'updated_at';

    /** @var array<string> */
    protected $fillable = [
        'vehicle_id',
        'fleet_manager_id',
        'mechanic_id',
        'incident_id',
        'service_type',
        'priority',
        'status',
        'issue',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Vehicle this assignment is for */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    /** Fleet manager who created this assignment */
    public function fleetManager()
    {
        return $this->belongsTo(FleetManager::class, 'fleet_manager_id', 'fleet_manager_id');
    }

    /** Mechanic assigned to carry out the work */
    public function mechanic()
    {
        return $this->belongsTo(Mechanic::class, 'mechanic_id', 'mechanic_id');
    }
}
