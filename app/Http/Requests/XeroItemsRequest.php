<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class XeroItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string|max:30',
            'items.*.name' => 'required|string|max:50',
            'items.*.description' => 'nullable|string|max:4000',
            'items.*.salePrice' => 'required|numeric|min:0',
            'items.*.costPrice' => 'required|numeric|min:0',
            'items.*.salesAccountCode' => 'required|string|max:10',
            'items.*.purchaseAccountCode' => 'required|string|max:10',
        ];
    }
}
