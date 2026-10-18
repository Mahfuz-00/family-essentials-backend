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
     * The period defaults to the current month, so `GET .../summary` with no
     * parameters is always "this month's money".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
