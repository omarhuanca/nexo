<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|integer|min:0|max:6',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.min' => 'The amount must be greater than zero.',
            'payment_type.required' => 'The payment_type is required.',
            'payment_type.min' => 'The payment_type must be between 0 and 6.',
            'payment_type.max' => 'The payment_type must be between 0 and 6.',
        ];
    }
}
