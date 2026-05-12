<?php

namespace Database\Seeders;

use App\Modules\RealtimeTracking\Models\GpsPing;
use App\Modules\RouteDispatch\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GpsPingSeeder extends Seeder
{
    private const TRACKABLE_ORDER_STATUSES = ['InTransit', 'Out for Delivery'];

    public function run(): void
    {
        $routes = Route::whereIn('status', ['Active', 'InProgress', 'In_progress'])
            ->orderBy('route_id')
            ->get();

        foreach ($routes as $route) {
            if (!$route->driver_id) {
                continue;
            }

            GpsPing::where('route_id', $route->route_id)->delete();

            $points = $this->buildTrailPoints($route);
            if (empty($points)) {
                continue;
            }

            $startedAt = now()->subMinutes(count($points) * 2);

            foreach ($points as $index => $point) {
                GpsPing::create([
                    'driver_id' => $route->driver_id,
                    'vehicle_id' => $route->vehicle_id,
                    'route_id' => $route->route_id,
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'speed_kmh' => $index === count($points) - 1 ? 0 : mt_rand(24, 58),
                    'accuracy_m' => mt_rand(5, 18),
                    'heading' => $this->headingFor($points, $index),
                    'is_spoofed' => false,
                    'recorded_at' => (clone $startedAt)->addMinutes($index * 2),
                ]);
            }
        }
    }

    private function buildTrailPoints(Route $route): array
    {
        $orderPoints = $this->getOrderPointsForRoute($route);

        if (empty($orderPoints)) {
            return [];
        }

        $depot = $this->buildDepotPoint($orderPoints[0], (int) $route->route_id);
        $anchors = array_merge([$depot], $orderPoints);
        $points = [];

        foreach ($anchors as $index => $anchor) {
            if ($index === 0) {
                $points[] = $anchor;
                continue;
            }

            $previous = $anchors[$index - 1];
            foreach ($this->interpolatePoints($previous, $anchor, 3) as $point) {
                $points[] = $point;
            }
        }

        return $points;
    }

    private function getOrderPointsForRoute(Route $route): array
    {
        $limit = max(1, (int) ($route->total_stops ?: 6));

        $query = DB::table('order')
            ->select('OrderID', 'Latitude', 'Longitude')
            ->whereNotNull('Latitude')
            ->whereNotNull('Longitude')
            ->whereIn('Status', self::TRACKABLE_ORDER_STATUSES)
            ->where('DriverID(FK)', $route->driver_id);

        if ($route->vehicle_id) {
            $query->where('vehicle_id(FK)', $route->vehicle_id);
        }

        $orders = $query
            ->orderBy('OrderID')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty() && $route->vehicle_id) {
            $orders = DB::table('order')
                ->select('OrderID', 'Latitude', 'Longitude')
                ->whereNotNull('Latitude')
                ->whereNotNull('Longitude')
                ->whereIn('Status', self::TRACKABLE_ORDER_STATUSES)
                ->where('DriverID(FK)', $route->driver_id)
                ->orderBy('OrderID')
                ->limit($limit)
                ->get();
        }

        return $orders
            ->map(fn ($order) => [
                'lat' => (float) $order->Latitude,
                'lng' => (float) $order->Longitude,
            ])
            ->filter(fn ($point) => $this->isValidPoint($point))
            ->values()
            ->all();
    }

    private function buildDepotPoint(array $firstOrderPoint, int $routeId): array
    {
        $offset = 0.01 + ($routeId % 4) * 0.002;

        return [
            'lat' => $firstOrderPoint['lat'] - $offset,
            'lng' => $firstOrderPoint['lng'] - ($offset / 1.5),
        ];
    }

    private function interpolatePoints(array $from, array $to, int $steps): array
    {
        $points = [];

        for ($step = 1; $step <= $steps; $step++) {
            $ratio = $step / $steps;
            $points[] = [
                'lat' => $from['lat'] + (($to['lat'] - $from['lat']) * $ratio),
                'lng' => $from['lng'] + (($to['lng'] - $from['lng']) * $ratio),
            ];
        }

        return $points;
    }

    private function isValidPoint(array $point): bool
    {
        return $point['lat'] >= -90
            && $point['lat'] <= 90
            && $point['lng'] >= -180
            && $point['lng'] <= 180;
    }

    private function headingFor(array $points, int $index): float
    {
        if ($index >= count($points) - 1) {
            return 0.0;
        }

        $current = $points[$index];
        $next = $points[$index + 1];
        $angle = atan2($next['lng'] - $current['lng'], $next['lat'] - $current['lat']);

        return round(fmod(rad2deg($angle) + 360, 360), 2);
    }
}
