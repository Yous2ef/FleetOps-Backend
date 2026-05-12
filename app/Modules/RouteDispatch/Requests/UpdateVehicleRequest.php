<?php

/**
 * @file: StoreVehicleRequest.php
 * @description: Validates the "Add Vehicle" form payload submitted from the
 *               Fleet Management screen.
 *
 *   Frontend sends (view.js › saveVehicleBtn.onclick):
 *     plate       (string)  — VehicleLicense in DB
 *     type        (string)  — VehicleType    in DB  e.g. "light|heavy|refrigerated"
 *     max_weight  (numeric) — MaxWeightCapacity
 *     max_volume  (numeric) — MaxVolume
 *     odometer    (numeric) — Current_odometer
 *     market_value(numeric) — MarketValue
 *
 *   All error responses follow the standard envelope:
 *     { success: false, message: string, errors: {}, data: [] }
 *
 * @module: RouteDispatch
 */

namespace App\Modules\RouteDispatch\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicleId = $this->route('id');

        return [
            // VehicleLicense — must be unique across the vehicles table.
            // The SQL Server column is VehicleLicense; we reference it explicitly
            // so Laravel's unique rule hits the right column. We ignore the current vehicle ID.
            'plate' => [
                'required',
                'string',
                'max:20',
                'unique:vehicles,VehicleLicense,' . $vehicleId . ',vehicle_id',
            ],

            // VehicleType — constrained to the three types the UI offers.
            'type' => [
                'required',
                'string',
                'in:light,heavy,refrigerated',
            ],

            // MaxWeightCapacity — kg, must be a positive number.
            'max_weight' => [
                'required',
                'numeric',
                'min:0',
            ],

            // MaxVolume — m³, must be a positive number.
            'max_volume' => [
                'required',
                'numeric',
                'min:0',
            ],

            // Current_odometer — km. Required by the frontend form.
            'odometer' => [
                'required',
                'numeric',
                'min:0',
            ],

            // MarketValue — SAR. Required by the frontend form.
            'market_value' => [
                'required',
                'numeric',
                'min:0',
            ],

            // VehicleModel — required make/model string (e.g. "Toyota Hilux").
            // Sent by the frontend as 'vehicle_model'.
            'vehicle_model' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required'          => 'A plate number is required.',
            'plate.unique'            => 'A vehicle with this plate number already exists.',
            'plate.max'               => 'Plate number cannot exceed 20 characters.',
            'type.required'           => 'Vehicle type is required.',
            'type.in'                 => 'Vehicle type must be one of: light, heavy, refrigerated.',
            'max_weight.required'     => 'Maximum weight capacity is required.',
            'max_weight.numeric'      => 'Maximum weight must be a number.',
            'max_weight.min'          => 'Maximum weight cannot be negative.',
            'max_volume.required'     => 'Maximum volume is required.',
            'max_volume.numeric'      => 'Maximum volume must be a number.',
            'max_volume.min'          => 'Maximum volume cannot be negative.',
            'odometer.required'       => 'Current odometer reading is required.',
            'odometer.numeric'        => 'Odometer must be a number.',
            'odometer.min'            => 'Odometer reading cannot be negative.',
            'market_value.required'   => 'Market value is required.',
            'market_value.numeric'    => 'Market value must be a number.',
            'market_value.min'        => 'Market value cannot be negative.',
            'vehicle_model.required'  => 'Vehicle make/model is required.',
            'vehicle_model.max'       => 'Vehicle make/model cannot exceed 100 characters.',
        ];
    }

    /**
     * Override failedValidation to return the standard FleetOps JSON envelope
     * instead of Laravel's default redirect/422 response.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The provided vehicle data is invalid.',
            'errors'  => $validator->errors()->toArray(),
            'data'    => [],
        ], 422));
    }
}
