<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:2048',
            'quantity' => 'required|numeric|min:0.001|max:999999',
            'unit_price' => 'required|numeric|min:0|max:999999999.99',
            'total_amount' => 'required|numeric|min:0|max:999999999.99',
            'labels' => 'required|array|min:1',
            'labels.*' => 'required|string|in:A,B,C,D,E,F,G,H',
            'account_code' => 'required|string|max:10',
            'gtin' => 'nullable|string|min:8|max:14|regex:/^\d+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'The code is required.',
            'code.max' => 'The code cannot exceed 30 characters.',
            'name.required' => 'The name is required.',
            'name.max' => 'The name cannot exceed 2048 characters.',
            'quantity.required' => 'The quantity is required.',
            'quantity.min' => 'The quantity must be greater than zero.',
            'quantity.max' => 'The quantity cannot exceed 999999.',
            'unit_price.required' => 'The unit_price is required.',
            'unit_price.min' => 'The unit_price cannot be negative.',
            'unit_price.max' => 'The unit_price cannot exceed 999999999.99.',
            'total_amount.required' => 'The total_amount is required.',
            'total_amount.min' => 'The total_amount cannot be negative.',
            'total_amount.max' => 'The total_amount cannot exceed 999999999.99.',
            'labels.required' => 'The labels field is required.',
            'labels.min' => 'At least one tax label is required.',
            'labels.*.in' => 'Invalid tax label. Allowed values: A, B, C, D, E, F, G, H.',
            'account_code.required' => 'The account_code is required.',
            'account_code.max' => 'The account_code cannot exceed 10 characters.',
            'gtin.regex' => 'The gtin must contain only digits.',
            'gtin.min' => 'The gtin must be at least 8 digits.',
            'gtin.max' => 'The gtin cannot exceed 14 digits.',
        ];
    }
}
