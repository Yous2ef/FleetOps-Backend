<?php

/**
 * @file: TrackingPreferencesRequest.php
 * @description: Validates the delivery preferences payload submitted by the customer.
 *               Fields: ring_doorbell (bool), leave_at_door (bool), notes (string).
 * @module: OrderManagement
 */

namespace App\Modules\OrderManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TrackingPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — token-gated at service level
    }

    public function rules(): array
    {
        return [
            'ring_doorbell' => 'nullable|boolean',
            'leave_at_door' => 'nullable|boolean',
            'notes'         => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max'             => 'Delivery notes cannot exceed 500 characters.',
            'ring_doorbell.boolean' => 'ring_doorbell must be true or false.',
            'leave_at_door.boolean' => 'leave_at_door must be true or false.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The provided data is invalid.',
            'errors'  => $validator->errors()->toArray(),
            'data'    => [],
        ], 422));
    }
}
