<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            'settings.quiet_hours' => ['sometimes', 'array'],
            'settings.quiet_hours.enabled' => ['required_with:settings.quiet_hours', 'boolean'],
            'settings.quiet_hours.start' => ['nullable', 'date_format:H:i'],
            'settings.quiet_hours.end' => ['nullable', 'date_format:H:i'],
        ];
    }
}
