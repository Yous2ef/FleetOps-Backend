<?php

namespace App\Modules\RouteDispatch\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource is an instance of AuthIdentity\Models\Driver
        // Eager loaded: user, vehicle

        $name = $this->user ? $this->user->name : '';
        $initials = '';
        if ($name) {
            $initials = implode('', array_map(
                fn($word) => strtoupper(mb_substr($word, 0, 1)),
                array_filter(explode(' ', $name))
            ));
        }

        return [
            'driver_id'       => (string) $this->driver_id,
            'name'            => $this->user->name ?? 'N/A',
            'phone_no'        => $this->user->phone_no ?? 'N/A',
            'initials'        => $initials,
            'status'          => $this->status ?? '',
            'score'           => (int) ($this->score ?? 0),
            'shift'           => $this->status ?? '', // mirrors status until dedicated shift col exists
            'license_type'    => $this->license_type ?? '',
            'license_no'      => $this->license_no ?? '',
            'stats'           => [
                'deliveries'   => 0,
                'success_rate' => 0,
                'on_time_rate' => 0,
                'avg_time'     => 0,
            ],
            'current_vehicle' => $this->vehicle_id ? (string) $this->vehicle_id : null,
            // Resolved from Vehicle relationship
            'plate_no'        => $this->vehicle->VehicleLicense ?? 'N/A',
            'vehicle_type'    => $this->vehicle ? strtolower($this->vehicle->VehicleType ?? '') : null,
            'current_route'   => null,
        ];
    }
}
