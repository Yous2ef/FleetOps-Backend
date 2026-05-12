<?php

/**
 * @file Order.php
 * @description Eloquent Model for the orders table — OrderManagement Module
 * @module OrderManagement
 * @table Order
 *
 * NOTE: OrderID is NOT auto-incremented — IDs are assigned externally per DDL.
 *
 * @author Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\AuthIdentity\Models\Customer;
use App\Modules\AuthIdentity\Models\Driver;
use App\Modules\RouteDispatch\Models\RouteStop;
use App\Modules\RouteDispatch\Models\Vehicle;
use App\Modules\ReportingAnalytics\Models\CashLedger;

class Order extends Model
{
    use HasFactory;

    protected $table      = 'order';
    protected $primaryKey = 'OrderID';
    protected $keyType    = 'int';
    public $incrementing  = true; // Enabled auto-increment

    const CREATED_AT = 'Created_at';
    const UPDATED_AT = 'UpdatedAt';

    // ─── Boot ─────────────────────────────────────────────────────────────────

    /**
     * Auto-generate a UUID tracking token whenever a new order is created.
     *
     * Two things happen:
     *   1. A URL-safe token is written to `LiveTrackingLink` on the order row
     *      itself so it's always retrievable without a join.
     *   2. A corresponding row is inserted into `tracking_tokens` with a 30-day
     *      expiry so the CustomerTrackingService can validate it.
     *
     * Both steps are in the same DB connection; if `tracking_tokens` doesn't
     * exist yet the try/catch ensures the order itself still saves cleanly.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->LiveTrackingLink)) {
                $token = Str::uuid()->toString();
                $order->LiveTrackingLink = $token;

                // Best-effort: insert tracking token row.
                // Wrapped in try/catch so a missing `tracking_tokens` table
                // during early dev doesn't block order creation.
                try {
                    DB::table('tracking_tokens')->insert([
                        'token'      => $token,
                        'order_id'   => null,  // order_id filled after insert via saved() hook
                        'expires_at' => now()->addDays(30),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // Non-fatal: order saves, token row will be backfilled
                }
            }
        });

        // After the order is persisted we know its OrderID — update the token row.
        static::created(function (self $order) {
            try {
                DB::table('tracking_tokens')
                    ->where('token', $order->LiveTrackingLink)
                    ->whereNull('order_id')
                    ->update(['order_id' => $order->OrderID]);
            } catch (\Throwable) {
                // Non-fatal
            }
        });
    }

    /** @var array<string> */
    protected $fillable = [
        'OrderID',
        'DriverID(FK)',
        'CustomerID(FK)',
        'vehicle_id(FK)',
        'TransactionID(FK)',
        'Status',
        'ETA',
        'PromisedWindow',
        'Priority',
        'Type',
        'Price',
        'digital_signature',
        'Delivery_preference',
        'Payment_method',
        'Created_at',
        'UpdatedAt',
        'Perishable',
        'DeliveredAt',
        'Weight',
        'Volume',
        'LiveTrackingLink',
        'DeliveryTimeWindow',
        'Longitude',
        'Latitude',
        'Area'
    ];

    /** @var array<string, string> */
    protected $casts = [
        'Priority'           => 'integer',
        'Price'              => 'integer',
        'Weight'             => 'integer',
        'Volume'             => 'integer',
        'Perishable'         => 'boolean',
        'DeliveryTimeWindow' => 'decimal:2',
        'Longitude'          => 'decimal:8',
        'Latitude'           => 'decimal:8',
        'PromisedWindow'     => 'datetime',
        'DeliveredAt'        => 'datetime',
        'Created_at'         => 'datetime',
        'UpdatedAt'         => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Customer who placed this order */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerID(FK)', 'customer_id');
    }

    /** Driver assigned to deliver this order */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'DriverID(FK)', 'driver_id');
    }

    /** Vehicle assigned to deliver this order */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id(FK)', 'vehicle_id');
    }

    /** Route stop(s) associated with this order */
    public function routeStops()
    {
        return $this->hasMany(RouteStop::class, 'order_id', 'OrderID');
    }

    /** Cash ledger entry for this order */
    public function cashLedgerEntry()
    {
        return $this->belongsTo(CashLedger::class, 'TransactionID(FK)', 'transaction_id');
    }
}
