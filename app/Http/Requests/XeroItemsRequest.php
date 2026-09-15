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
            'productIds' => 'required|array|min:1',
            'productIds.*' => 'required|integer|exists:products,id',
        ];
    }
}
