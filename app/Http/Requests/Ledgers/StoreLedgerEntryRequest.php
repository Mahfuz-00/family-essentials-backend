<?php

namespace App\Http\Requests\Ledgers;

use App\Enums\LedgerEntryDirection;
use App\Models\TenantUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Amounts are integer minor units. `min:1` because a movement of zero is not
     * a movement, and a positive count of minor units cannot be mistaken for a
     * decimal amount by anything downstream.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'direction' => ['required', Rule::enum(LedgerEntryDirection::class)],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'occurred_on' => ['nullable', 'date'],
            'paid_by' => ['nullable', 'integer', $this->tenantMemberRule()],
        ];
    }

    private function tenantMemberRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $isMember = TenantUser::query()
                ->where('user_id', $value)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                $fail('The selected member is not part of this household.');
            }
        };
    }
}
