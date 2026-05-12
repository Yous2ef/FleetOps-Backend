<?php

/**
 * @file: OrderRequest.php
 * @description: التحقق من بيانات الطلبات - Order Management Service
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_name'          => 'required|string|max:255',
            'customer_phone'         => 'required|string|max:20',
            'customer_email'         => 'nullable|email|max:255',
            'delivery_address'       => 'required|string|max:500',
            'lat'                    => 'nullable|numeric|between:-90,90',
            'lng'                    => 'nullable|numeric|between:-180,180',
            'weight_kg'              => 'nullable|numeric|min:0',
            'volume_m3'              => 'nullable|numeric|min:0',
            'payment_type'           => 'required|in:prepaid,COD',
            'cod_amount'             => 'required_if:payment_type,COD|numeric|min:0',
            'priority'               => 'nullable|in:normal,express',
            'vehicle_id'             => 'nullable|integer',
            'promised_window_start'  => 'nullable|date',
            'promised_window_end'    => 'nullable|date|after:promised_window_start',
            'delivery_preference'    => 'nullable|string|max:1000',
            'notes'                  => 'nullable|string|max:1000',
        ];
    }
}
