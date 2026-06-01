<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaxCoreConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
            'certificate' => 'required|file|extensions:pfx,p12',
            'password'    => 'required|string',
            'pac'         => 'required|string',
            'environment' => 'required|string|in:sandbox,production',
        ];
    }
}
