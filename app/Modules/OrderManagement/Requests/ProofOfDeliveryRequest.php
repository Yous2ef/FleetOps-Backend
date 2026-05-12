<?php

namespace App\Modules\OrderManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProofOfDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'digital_signature' => 'nullable|string',
            'customer_name'     => 'nullable|string',
            'geolocation'       => 'nullable|array',
            'geolocation.lat'   => 'nullable|numeric',
            'geolocation.lng'   => 'nullable|numeric',
            'timestamp'         => 'nullable|string',
        ];
    }
}
