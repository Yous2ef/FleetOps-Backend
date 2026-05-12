<?php

namespace App\Modules\RouteDispatch\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->vehicle_id,
            'plate_number' => $this->VehicleLicense,
            'model' => $this->VehicleModel,
            'status' => $this->Status,
            'last_maintenance' => $this->UpdatedAt,
        ];
    }
}
