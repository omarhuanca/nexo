<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'invoiceType' => 'nullable|integer|in:0,1,2,3,4',
            'transactionType' => 'nullable|integer|in:0,1',
            'fiscalNumber' => 'nullable|string|max:60',
            'dateFrom' => 'nullable|date_format:Y-m-d',
            'dateTo' => 'nullable|date_format:Y-m-d|after_or_equal:dateFrom',
            'page' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'dateTo.after_or_equal' => 'The dateTo must be equal to or after dateFrom.',
        ];
    }
}
