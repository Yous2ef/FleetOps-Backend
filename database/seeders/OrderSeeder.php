<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OrderSeeder — 100 delivery orders with logically consistent data.
 *
 * Business rules enforced:
 *  - Pending  → no driver, no vehicle, no ETA
 *  - Assigned → driver + vehicle assigned, ETA in future (1-3 days)
 *  - InTransit / Out for Delivery → driver + vehicle, ETA = today (hours away)
 *  - Delivered / Failed / Returned → driver + vehicle, ETA & PromisedWindow in the past
 *  - ETA and PromisedWindow are full datetime2 values (not just HH:MM)
 */
class OrderSeeder extends Seeder
{
    // ── Status distribution ────────────────────────────────────────────────────
    private const STATUS_POOL = [
        'Pending'          => 15,
        'Assigned'         => 15,
        'InTransit'        => 12,
        'Out for Delivery' => 10,
        'Delivered'        => 35,
        'Failed'           => 8,
        'Returned'         => 5,
    ];

    private const TYPES               = ['Normal', 'Express', 'Low'];
    private const DELIVERY_PREFS      = ['Morning', 'Afternoon', 'Evening', 'Any'];
    private const PAYMENT_METHODS     = ['Cash', 'Card', 'Wallet'];
    private const AREAS               = ['Downtown', 'Nasr City', 'Maadi', 'Zamalek', 'Heliopolis', 'Dokki', '6th of October'];
    private const START_ORDER_ID      = 1001;
    private const ORDERS_TO_GENERATE  = 100;

    public function run(): void
    {
        // ── Load dependencies ──────────────────────────────────────────────────
        $driverRows = DB::table('drivers')
            ->select('driver_id', 'vehicle_id')
            ->get();

        $customers = DB::table('customers')
            ->pluck('customer_id')
            ->toArray();

        if ($driverRows->isEmpty() || empty($customers)) {
            $this->command->warn('OrderSeeder: No drivers or customers found. Run UserSeeder + ProfileSeeder first.');
            return;
        }

        // Drivers that have an assigned vehicle (preferred for active orders)
        $driversWithVehicle = $driverRows
            ->filter(fn ($d) => ! is_null($d->vehicle_id))
            ->values();

        // Fallback: any driver
        $anyDrivers = $driverRows->values();

        // ── Build status pool (deterministic shuffle) ──────────────────────────
        $statusPool = [];
        foreach (self::STATUS_POOL as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $statusPool[] = $status;
            }
        }
        // Deterministic shuffle so re-runs produce same distribution
        srand(42);
        shuffle($statusPool);

        // ── Skip already-seeded IDs ────────────────────────────────────────────
        $endId       = self::START_ORDER_ID + self::ORDERS_TO_GENERATE - 1;
        $existingIds = DB::table('order')
            ->whereBetween('OrderID', [self::START_ORDER_ID, $endId])
            ->pluck('OrderID')
            ->flip()
            ->toArray();

        $orders = [];
        $now    = Carbon::now();

        for ($index = 0; $index < self::ORDERS_TO_GENERATE; $index++) {
            $orderId = self::START_ORDER_ID + $index;

            if (isset($existingIds[$orderId])) {
                continue;
            }

            $status     = $statusPool[$index];
            $customerId = $customers[$index % count($customers)];

            // Order was placed between 1 and 45 days ago
            $createdAt = $now->copy()
                ->subDays(mt_rand(1, 45))
                ->subMinutes(mt_rand(0, 1440));

            // ── Assign driver, vehicle, ETA, PromisedWindow by status ─────────
            [$driverId, $vehicleId, $eta, $promisedWindow, $deliveredAt, $isFinished]
                = $this->resolveStatusFields($status, $index, $driversWithVehicle, $anyDrivers, $now, $createdAt);

            $orders[] = [
                'OrderID'             => $orderId,
                'DriverID(FK)'        => $driverId,
                'CustomerID(FK)'      => $customerId,
                'vehicle_id(FK)'      => $vehicleId,
                'TransactionID(FK)'   => null,
                'Status'              => $status,
                'ETA'                 => $eta,
                'PromisedWindow'      => $promisedWindow,
                'Priority'            => mt_rand(0, 100),
                'Type'                => self::TYPES[$index % count(self::TYPES)],
                'Price'               => mt_rand(150, 6000),
                'digital_signature'   => $isFinished ? sprintf('SIG%06d', $orderId) : null,
                'Delivery_preference' => self::DELIVERY_PREFS[$index % count(self::DELIVERY_PREFS)],
                'Payment_method'      => self::PAYMENT_METHODS[$index % count(self::PAYMENT_METHODS)],
                'Perishable'          => mt_rand(0, 1),
                'Weight'              => mt_rand(1, 50),
                'Volume'              => mt_rand(1, 5),
                'LiveTrackingLink'    => 'https://fleetops.com/track/' . $orderId,
                'DeliveryTimeWindow'  => mt_rand(1, 8),
                'Longitude'           => round(mt_rand(31150000, 31320000) / 1000000, 6),
                'Latitude'            => round(mt_rand(29950000, 30200000) / 1000000, 6),
                'Area'                => self::AREAS[$index % count(self::AREAS)],
                'Created_at'          => $createdAt->toDateTimeString(),
                'UpdatedAt'           => $createdAt->copy()->addMinutes(mt_rand(10, 720))->toDateTimeString(),
                'DeliveredAt'         => $deliveredAt,
            ];
        }

        if (empty($orders)) {
            $this->command->info('OrderSeeder: all orders already exist, nothing to insert.');
            return;
        }

        DB::connection()->getPdo()->exec('SET IDENTITY_INSERT [order] ON');
        try {
            foreach (array_chunk($orders, 50) as $chunk) {
                DB::table('order')->insert($chunk);
            }
        } finally {
            DB::connection()->getPdo()->exec('SET IDENTITY_INSERT [order] OFF');
        }

        $this->command->info("OrderSeeder: seeded " . count($orders) . " orders (target: 100).");
    }

    /**
     * Resolve status-specific fields.
     *
     * Returns: [driverId, vehicleId, eta, promisedWindow, deliveredAt, isFinished]
     */
    private function resolveStatusFields(
        string $status,
        int $index,
        $driversWithVehicle,
        $anyDrivers,
        Carbon $now,
        Carbon $createdAt
    ): array {
        $driverId       = null;
        $vehicleId      = null;
        $eta            = null;
        $promisedWindow = null;
        $deliveredAt    = null;
        $isFinished     = false;

        $driverPool = $driversWithVehicle->isNotEmpty() ? $driversWithVehicle : $anyDrivers;
        $driver     = $driverPool[$index % $driverPool->count()];

        switch ($status) {
            case 'Pending':
                // Order placed — not yet assigned to anyone
                $promisedWindow = $now->copy()
                    ->addDays(mt_rand(1, 7))
                    ->setHour(mt_rand(8, 20))
                    ->setMinute(0)->setSecond(0)
                    ->toDateTimeString();
                break;

            case 'Assigned':
                // Driver and vehicle assigned, pickup scheduled
                $driverId  = $driver->driver_id;
                $vehicleId = $driver->vehicle_id;
                $etaCarbon = $now->copy()
                    ->addDays(mt_rand(1, 3))
                    ->setHour(mt_rand(8, 18))
                    ->setMinute(0)->setSecond(0);
                $eta            = $etaCarbon->toDateTimeString();
                $promisedWindow = $etaCarbon->copy()->addHours(mt_rand(1, 4))->toDateTimeString();
                break;

            case 'InTransit':
                // Currently on the road — ETA a few hours out
                $driverId  = $driver->driver_id;
                $vehicleId = $driver->vehicle_id;
                $etaCarbon = $now->copy()
                    ->addHours(mt_rand(2, 8))
                    ->setMinute(0)->setSecond(0);
                $eta            = $etaCarbon->toDateTimeString();
                $promisedWindow = $now->copy()->addHours(mt_rand(4, 12))->toDateTimeString();
                break;

            case 'Out for Delivery':
                // Last-mile — ETA within the hour
                $driverId  = $driver->driver_id;
                $vehicleId = $driver->vehicle_id;
                $etaCarbon = $now->copy()->addMinutes(mt_rand(15, 90));
                $eta            = $etaCarbon->toDateTimeString();
                $promisedWindow = $now->copy()->addHours(mt_rand(1, 3))->toDateTimeString();
                break;

            case 'Delivered':
                $isFinished = true;
                $driverId   = $driver->driver_id;
                $vehicleId  = $driver->vehicle_id;
                // PromisedWindow was set at order time (1-3 days after creation)
                $pwCarbon = $createdAt->copy()
                    ->addDays(mt_rand(1, 3))
                    ->setHour(mt_rand(10, 18))
                    ->setMinute(0)->setSecond(0);
                $promisedWindow = $pwCarbon->toDateTimeString();
                // ETA was close to PromisedWindow
                $eta         = $pwCarbon->copy()->subHours(mt_rand(0, 2))->toDateTimeString();
                // Delivered slightly before or after promised window
                $deliveredAt = $pwCarbon->copy()->addMinutes(mt_rand(-20, 60))->toDateTimeString();
                break;

            case 'Failed':
                $isFinished = true;
                $driverId   = $driver->driver_id;
                $vehicleId  = $driver->vehicle_id;
                $pwCarbon = $createdAt->copy()
                    ->addDays(mt_rand(1, 3))
                    ->setHour(mt_rand(10, 18))
                    ->setMinute(0)->setSecond(0);
                $promisedWindow = $pwCarbon->toDateTimeString();
                $eta            = $pwCarbon->copy()->subHours(mt_rand(0, 1))->toDateTimeString();
                // Failed — no deliveredAt
                break;

            case 'Returned':
                $isFinished = true;
                $driverId   = $driver->driver_id;
                $vehicleId  = $driver->vehicle_id;
                $pwCarbon = $createdAt->copy()
                    ->addDays(mt_rand(1, 3))
                    ->setHour(mt_rand(10, 18))
                    ->setMinute(0)->setSecond(0);
                $promisedWindow = $pwCarbon->toDateTimeString();
                $eta            = $pwCarbon->copy()->subHours(mt_rand(0, 1))->toDateTimeString();
                // Returned after failed attempt
                $deliveredAt = $pwCarbon->copy()->addDays(mt_rand(1, 4))->toDateTimeString();
                break;
        }

        return [$driverId, $vehicleId, $eta, $promisedWindow, $deliveredAt, $isFinished];
    }
}
