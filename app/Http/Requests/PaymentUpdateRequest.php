<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_type' => 'sometimes|integer|min:0|max:6',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The amount must be greater than zero.',
            'payment_type.min' => 'The payment_type must be between 0 and 6.',
            'payment_type.max' => 'The payment_type must be between 0 and 6.',
        ];
    }
}
