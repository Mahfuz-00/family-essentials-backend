<?php

namespace App\Http\Requests\Tenancy;

use App\Plans\QuotaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'subscription_tier' => ['sometimes', 'string', Rule::in(QuotaService::tiers())],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
