<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|max:2048',
            'salePrice' => 'required|numeric|min:0|max:999999999.99',
            'costPrice' => 'required|numeric|min:0|max:999999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'The organization_id is required.',
            'organization_id.exists' => 'The selected organization does not exist.',
            'code.required' => 'The product code is required.',
            'code.max' => 'The product code cannot exceed 30 characters.',
            'name.required' => 'The product name is required.',
            'name.max' => 'The product name cannot exceed 255 characters.',
            'description.max' => 'The product description cannot exceed 2048 characters.',
            'salePrice.required' => 'The sale price is required.',
            'salePrice.min' => 'The sale price cannot be negative.',
            'costPrice.required' => 'The cost price is required.',
            'costPrice.min' => 'The cost price cannot be negative.',
        ];
    }
}
