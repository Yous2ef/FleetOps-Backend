<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RouteStopSeeder — links routes to real orders with realistic stop data.
 *
 * Rules:
 *  - Completed routes  → Delivered orders, all stops have actual_arrival_time
 *  - InProgress routes → InTransit / Out for Delivery orders, partial arrivals
 *  - Planned routes    → Assigned / Pending orders, no actual_arrival_time yet
 *
 * Coordinates are real Cairo-area locations.
 */
class RouteStopSeeder extends Seeder
{
    /**
     * Named Cairo-area waypoints [lat, lng, label]
     */
    private const WAYPOINTS = [
        [30.0444, 31.2357, 'Cairo Downtown'],
        [30.0626, 31.3417, 'Nasr City'],
        [29.9602, 31.2569, 'Maadi'],
        [30.0131, 31.2089, 'Giza Square'],
        [30.1286, 31.3422, 'Heliopolis'],
        [30.0595, 31.2233, 'Dokki'],
        [30.0769, 31.2864, 'Heliopolis South'],
        [30.0050, 31.4200, 'New Cairo'],
        [29.8741, 31.3438, '6th of October'],
        [30.5234, 31.6789, '10th of Ramadan'],
    ];

    public function run(): void
    {
        $routes = DB::table('routes')->orderBy('route_id')->get();

        if ($routes->isEmpty()) {
            $this->command->warn('⚠️  RouteStopSeeder: No routes found. Run RouteSeeder first.');
            return;
        }

        // Fetch order IDs grouped by status
        $deliveredOrderIds = DB::table('order')
            ->where('Status', 'Delivered')
            ->pluck('OrderID')
            ->toArray();

        $activeOrderIds = DB::table('order')
            ->whereIn('Status', ['InTransit', 'Out for Delivery'])
            ->pluck('OrderID')
            ->toArray();

        $assignedOrderIds = DB::table('order')
            ->whereIn('Status', ['Assigned', 'Pending'])
            ->pluck('OrderID')
            ->toArray();

        $totalInserted = 0;

        foreach ($routes as $route) {
            // Skip if stops already exist for this route
            $existingCount = DB::table('route_stops')
                ->where('route_id', $route->route_id)
                ->count();

            if ($existingCount > 0) {
                continue;
            }

            [$orderPool, $includeArrival, $partialArrival] = $this->resolvePoolByStatus($route->status, $deliveredOrderIds, $activeOrderIds, $assignedOrderIds);

            $totalStops  = (int) $route->total_stops;
            $schedStart  = Carbon::parse($route->scheduled_start_time);
            $schedEnd    = Carbon::parse($route->scheduled_end_time);
            $legDuration = $totalStops > 1
                ? (int) ($schedStart->diffInMinutes($schedEnd) / $totalStops)
                : 60;

            $stops = [];

            for ($stopNo = 1; $stopNo <= $totalStops; $stopNo++) {
                $waypointIndex = ($stopNo - 1) % count(self::WAYPOINTS);
                $waypoint      = self::WAYPOINTS[$waypointIndex];

                // ETA for this stop: scheduled start + cumulative leg duration
                $stopEta = $schedStart->copy()->addMinutes($legDuration * $stopNo);

                // Actual arrival time logic
                $actualArrival = null;
                if ($includeArrival) {
                    // All stops arrived (Completed route)
                    $actualArrival = $stopEta->copy()->addMinutes(mt_rand(-5, 15));
                } elseif ($partialArrival && $stopNo <= intdiv($totalStops, 2)) {
                    // InProgress: first half of stops have arrivals
                    $actualArrival = $stopEta->copy()->addMinutes(mt_rand(-5, 10));
                }

                // Pick order for this stop (cycle through pool)
                $orderId = ! empty($orderPool)
                    ? $orderPool[($stopNo - 1) % count($orderPool)]
                    : null;

                $stops[] = [
                    'route_id'            => $route->route_id,
                    'stop_no'             => $stopNo,
                    'order_id'            => $orderId,
                    'eta'                 => $stopEta->toDateTimeString(),
                    'actual_arrival_time' => $actualArrival?->toDateTimeString(),
                    'latitude'            => $waypoint[0],
                    'longitude'           => $waypoint[1],
                ];
            }

            if (! empty($stops)) {
                DB::table('route_stops')->insert($stops);
                $totalInserted += count($stops);
            }
        }

        $this->command->info("✅ RouteStopSeeder: {$totalInserted} route stops created across {$routes->count()} routes.");
    }

    /**
     * Returns [orderPool, includeAllArrivals, partialArrivals] based on route status.
     */
    private function resolvePoolByStatus(
        string $status,
        array $deliveredIds,
        array $activeIds,
        array $assignedIds
    ): array {
        return match (true) {
            $status === 'Completed'  => [$deliveredIds, true,  false],
            in_array($status, ['InProgress', 'Active', 'In_progress'], true)
                                     => [$activeIds,    false, true],
            default                  => [$assignedIds,  false, false],
        };
    }
}
