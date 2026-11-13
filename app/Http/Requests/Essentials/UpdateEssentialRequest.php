<?php

namespace App\Http\Requests\Essentials;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEssentialRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:64'],
            'default_store' => ['nullable', 'string', 'max:120'],
            'pack_size' => ['nullable', 'string', 'max:64'],
            'cadence_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'stock_level' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'unit_price_minor' => ['sometimes', 'integer', 'min:0'],
            'last_spent_minor' => ['sometimes', 'integer', 'min:0'],
            'total_spent_minor' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
