<?php

namespace App\Http\Requests\Requests;

use App\Models\TenantUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AssignRequestRequest extends FormRequest
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
            'assignee_id' => ['required', 'integer', $this->tenantMemberRule()],
        ];
    }

    private function tenantMemberRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
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
