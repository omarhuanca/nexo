<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|min:8|max:20|regex:/^\d+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'The organization_id is required.',
            'organization_id.exists' => 'The selected organization does not exist.',
            'name.required' => 'The buyer name is required.',
            'name.max' => 'The buyer name cannot exceed 255 characters.',
            'tax_id.regex' => 'The tax_id must contain only digits.',
            'tax_id.min' => 'The tax_id must be at least 8 digits.',
            'tax_id.max' => 'The tax_id cannot exceed 20 digits.',
        ];
    }
}
