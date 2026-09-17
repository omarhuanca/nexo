<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class XeroProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'sales_account_code' => 'required|string|max:10',
            'purchase_account_code' => 'required|string|max:10',
        ];
    }
}
