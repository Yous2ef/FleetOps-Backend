<?php

/**
 * @file: TrackingFeedbackRequest.php
 * @description: Validates the post-delivery feedback payload from the customer.
 *               Fields: rating (1–5), condition (Excellent|Good|Damaged), comments (string).
 * @module: OrderManagement
 */

namespace App\Modules\OrderManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TrackingFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — token-gated at service level
    }

    public function rules(): array
    {
        return [
            'rating'    => 'required|integer|min:1|max:5',
            'condition' => 'nullable|string|in:Excellent,Good,Damaged',
            'comments'  => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required'  => 'A star rating (1–5) is required.',
            'rating.min'       => 'Rating must be at least 1 star.',
            'rating.max'       => 'Rating cannot exceed 5 stars.',
            'condition.in'     => 'Condition must be one of: Excellent, Good, Damaged.',
            'comments.max'     => 'Comments cannot exceed 1 000 characters.',
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
