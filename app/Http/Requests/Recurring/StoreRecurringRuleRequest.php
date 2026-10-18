<?php

namespace App\Http\Requests\Recurring;

use App\Enums\Cadence;
use App\Enums\GeofenceEventType;
use App\Enums\RequestPriority;
use App\Models\Ledger;
use App\Models\Place;
use App\Models\TenantUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringRuleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', Rule::enum(RequestPriority::class)],

            'cadence' => ['required', Rule::enum(Cadence::class)],
            // A weekly rule without a weekday has no meaning, and a monthly rule
            // without a day has none either, so each is required by its cadence.
            'weekday' => [
                'nullable', 'integer', 'between:0,6',
                Rule::requiredIf(fn (): bool => $this->input('cadence') === Cadence::Weekly->value),
            ],
            'day_of_month' => [
                'nullable', 'integer', 'between:1,31',
                Rule::requiredIf(fn (): bool => $this->input('cadence') === Cadence::Monthly->value),
            ],

            'assignee_id' => ['nullable', 'integer', $this->tenantMemberRule()],
            'place_id' => ['nullable', 'integer', $this->tenantPlaceRule()],
            'location_trigger_place_id' => ['nullable', 'integer', $this->tenantPlaceRule()],
            'location_trigger_direction' => $this->triggerDirectionRules(),

            'amount_minor' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'ledger_id' => ['nullable', 'integer', $this->tenantLedgerRule()],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A direction without a place is meaningless, and a place without a direction
     * would silently default to "enter" — so the two are validated together.
     *
     * @return array<int, mixed>
     */
    private function triggerDirectionRules(): array
    {
        return [
            'nullable',
            Rule::enum(GeofenceEventType::class),
            'required_with:location_trigger_place_id',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $this->input('location_trigger_place_id') === null) {
                    $fail('Choose a place for the reminder, or remove the direction.');
                }
            },
        ];
    }

    private function tenantPlaceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! Place::query()->whereKey($value)->exists()) {
                $fail('The selected place does not belong to this household.');
            }
        };
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

    /**
     * The ledger must be one the caller can see — and RLS decides that, because
     * a ledger they are not a member of simply does not exist for them.
     */
    private function tenantLedgerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! Ledger::query()->whereKey($value)->exists()) {
                $fail('The selected ledger is not one you take part in.');
            }
        };
    }
}
