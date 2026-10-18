<?php

namespace App\Http\Requests\Requests;

use App\Enums\GeofenceEventType;
use App\Enums\RequestPriority;
use App\Models\TenantUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['sometimes', Rule::enum(RequestPriority::class)],
            'store_name' => ['nullable', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', $this->tenantMemberRule()],
            'place_id' => ['nullable', 'integer', $this->tenantPlaceRule()],
            'location_trigger_place_id' => ['nullable', 'integer', $this->tenantPlaceRule()],
            'location_trigger_direction' => [
                'nullable',
                Rule::enum(GeofenceEventType::class),
                'required_with:location_trigger_place_id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $this->input('location_trigger_place_id') === null) {
                        $fail('Choose a place for the reminder, or remove the direction.');
                    }
                },
            ],
        ];
    }

    private function tenantPlaceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! \App\Models\Place::query()->whereKey($value)->exists()) {
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
}
