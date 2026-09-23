<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Shared\Security\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;

class CreateConnectorRequest extends FormRequest
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
            'name' => 'required|string|min:3|max:255',
            'allowed_events' => 'nullable|array',
            'allowed_events.*'=> 'string|max:100',
            'active' => 'sometimes|boolean',
            'callback_url' => ['nullable', 'string', 'max:2048', 'url:https', new PublicHttpsUrl()],
        ];
    }
}
