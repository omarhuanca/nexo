<?php

namespace App\Http\Requests;

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
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999999',
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
            'product_id.required' => 'The product is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'quantity.required' => 'The quantity is required.',
            'quantity.integer' => 'The quantity must be a whole number.',
            'quantity.min' => 'The quantity must be at least 1.',
            'quantity.max' => 'The quantity cannot exceed 999999.',
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
