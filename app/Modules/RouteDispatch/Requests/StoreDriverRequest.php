<?php

namespace App\Modules\RouteDispatch\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => 'required|exists:users,user_id|unique:drivers,driver_id', // Assuming new driver needs a user account
            'license_no'   => 'required|string|max:50|unique:drivers,license_no',
            'license_type' => 'required|string|in:light,heavy,refrigerated',
            'vehicle_id'   => 'nullable|integer|exists:vehicles,vehicle_id',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The provided driver data is invalid.',
            'errors'  => $validator->errors()->toArray(),
            'data'    => [],
        ], 422));
    }
}
