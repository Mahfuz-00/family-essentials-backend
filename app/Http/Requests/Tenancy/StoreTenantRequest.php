<?php

namespace App\Http\Requests\Tenancy;

use App\Plans\QuotaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // A known tier only: an unknown value used to be stored happily and
            // then silently resolved to a default plan.
            'subscription_tier' => ['sometimes', 'string', Rule::in(QuotaService::tiers())],
            'status' => ['sometimes', 'in:trial,active,suspended'],
        ];
    }
}
