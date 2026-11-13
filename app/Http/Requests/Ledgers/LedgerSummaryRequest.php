<?php

namespace App\Http\Requests\Ledgers;

use Illuminate\Foundation\Http\FormRequest;

class LedgerSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The period defaults to the current month or week, so `GET .../summary` with no
     * parameters is always "current period money".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_type' => ['nullable', 'string', 'in:monthly,weekly,yearly,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
