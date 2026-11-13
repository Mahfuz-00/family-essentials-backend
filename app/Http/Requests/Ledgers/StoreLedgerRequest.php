<?php

namespace App\Http\Requests\Ledgers;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'monthly_budget_minor' => ['nullable', 'integer', 'min:0'],
            'weekly_budget_minor' => ['nullable', 'integer', 'min:0'],
            'yearly_budget_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
