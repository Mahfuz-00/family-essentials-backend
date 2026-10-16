<?php

namespace App\Plans;

use App\Models\Essential;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\VoiceCallLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Plan limits, actual usage, and the enforcement that connects them.
 *
 * Until now the caps were presentation constants in the dashboard and nothing
 * enforced them. This is the server's copy of the truth: the dashboard reads its
 * numbers from here, and the same numbers refuse the write.
 *
 * Two deliberate choices:
 *
 *  - Every count is a query against live rows rather than a stored counter. A
 *    counter that drifts from reality is worse than a cheap count.
 *  - Counts take an explicit tenant and step around the tenant global scope, so
 *    the same service answers correctly inside a household request, on the mobile
 *    surface, from the platform console, and while provisioning.
 */
class QuotaService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The tier keys a tenant may be on.
     *
     * @return array<int, string>
     */
    public static function tiers(): array
    {
        return array_keys((array) config('plans.tiers'));
    }

    public static function defaultTier(): string
    {
        return (string) config('plans.default', 'pilot');
    }

    public function enforcementEnabled(): bool
    {
        return (bool) config('plans.enforce', true);
    }

    /**
     * The limits for a tier. An unknown tier falls back to the default rather
     * than to no limits at all — a typo must not open the gates.
     *
     * @return array<string, mixed>
     */
    public function planFor(?string $tier): array
    {
        $tiers = (array) config('plans.tiers');
        $key = is_string($tier) && array_key_exists($tier, $tiers) ? $tier : self::defaultTier();

        return ['key' => $key] + $tiers[$key];
    }

    /**
     * The tenant of the current request, when there is one.
     */
    public function tenant(): ?Tenant
    {
        $id = $this->context->id();

        return $id === null ? null : Tenant::query()->find($id);
    }

    /**
     * Live usage for a household.
     *
     * @return array<string, int>
     */
    public function usage(Tenant $tenant): array
    {
        return [
            'members' => $this->count(TenantUser::class, $tenant),
            'seats_reserved' => $this->reservedSeats($tenant),
            'places' => $this->count(Place::class, $tenant),
            'essentials' => $this->count(Essential::class, $tenant),
            'requests_this_month' => $this->count(
                ServiceRequest::class,
                $tenant,
                fn ($query) => $query->where('created_at', '>=', Carbon::now()->startOfMonth()),
            ),
            'voice_calls_today' => $this->count(
                VoiceCallLog::class,
                $tenant,
                fn ($query) => $query
                    ->where('created_at', '>=', Carbon::now()->startOfDay())
                    ->whereNotIn('status', ['skipped']),
            ),
        ];
    }

    /**
     * Pending invitations hold a seat: a household that invites five people has
     * five people coming, and letting it invite fifty would make the cap
     * meaningless.
     */
    public function reservedSeats(Tenant $tenant): int
    {
        return $this->count(
            TenantInvitation::class,
            $tenant,
            fn ($query) => $query
                ->whereNull('accepted_at')
                ->where(fn ($inner) => $inner
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now())),
        );
    }

    /**
     * The full picture for the console: limits, usage, what is left, and whether
     * anything is at its cap.
     *
     * @return array<string, mixed>
     */
    public function report(Tenant $tenant): array
    {
        $plan = $this->planFor($tenant->subscription_tier);
        $usage = $this->usage($tenant);

        $meters = [
            $this->meter(
                'seats',
                'Family member seats',
                $usage['members'] + $usage['seats_reserved'],
                (int) $plan['seat_cap'],
                'group',
                [
                    'members' => $usage['members'],
                    'reserved_by_invitations' => $usage['seats_reserved'],
                ],
            ),
            $this->meter('places', 'Saved places', $usage['places'], (int) $plan['place_cap'], 'pin_drop'),
            $this->meter('requests', 'Requests this month', $usage['requests_this_month'], (int) $plan['monthly_requests'], 'shopping_bag'),
            // Global bound, configured in config/voice.php and enforced by the
            // escalation policy rather than by this service.
            $this->meter('voice', 'Voice calls today', $usage['voice_calls_today'], (int) config('voice.daily_budget'), 'ring_volume'),
        ];

        return [
            'plan' => $plan,
            'enforced' => $this->enforcementEnabled(),
            'usage' => $usage,
            'meters' => $meters,
            'at_cap' => array_values(array_map(
                static fn (array $meter): string => $meter['key'],
                array_filter($meters, static fn (array $meter): bool => $meter['at_cap']),
            )),
            'period' => [
                'requests_since' => Carbon::now()->startOfMonth()->toIso8601String(),
                'voice_since' => Carbon::now()->startOfDay()->toIso8601String(),
            ],
        ];
    }

    // ------------------------------------------------------------ enforcement

    public function assertCanAddSeat(?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant();

        if (! $this->enforcementEnabled() || $tenant === null) {
            return;
        }

        $plan = $this->planFor($tenant->subscription_tier);
        $cap = (int) $plan['seat_cap'];
        $used = $tenant->members()->count() + $this->reservedSeats($tenant);

        if ($used + 1 > $cap) {
            throw new QuotaExceededException(
                "This household has used all {$cap} seats on the {$plan['label']} plan. ".
                'Remove a member or an invitation, or move to a larger plan.',
                'seats',
            );
        }
    }

    public function assertCanAddPlace(?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant();

        if (! $this->enforcementEnabled() || $tenant === null) {
            return;
        }

        $plan = $this->planFor($tenant->subscription_tier);
        $cap = (int) $plan['place_cap'];

        if ($this->count(Place::class, $tenant) + 1 > $cap) {
            throw new QuotaExceededException(
                "This household has saved all {$cap} places on the {$plan['label']} plan. ".
                'Remove a place, or move to a larger plan.',
                'places',
            );
        }
    }

    public function assertCanCreateRequest(?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant();

        if (! $this->enforcementEnabled() || $tenant === null) {
            return;
        }

        $plan = $this->planFor($tenant->subscription_tier);
        $cap = (int) $plan['monthly_requests'];
        $used = $this->count(
            ServiceRequest::class,
            $tenant,
            fn ($query) => $query->where('created_at', '>=', Carbon::now()->startOfMonth()),
        );

        if ($cap > 0 && $used + 1 > $cap) {
            throw new QuotaExceededException(
                "This household has created all {$cap} requests included in the {$plan['label']} ".
                'plan this month. Nothing is lost — it resets at the start of next month.',
                'requests',
            );
        }
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Count a tenant's rows without leaning on the ambient tenant context.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  callable(\Illuminate\Database\Eloquent\Builder<*>): void  $constrain
     */
    private function count(string $model, Tenant $tenant, ?callable $constrain = null): int
    {
        $query = $model::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id);

        if ($constrain !== null) {
            $constrain($query);
        }

        return $query->count();
    }

    /**
     * @param  array<string, int>  $detail
     * @return array<string, mixed>
     */
    private function meter(string $key, string $label, int $used, int $cap, string $icon, array $detail = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'used' => $used,
            'cap' => $cap,
            'remaining' => max(0, $cap - $used),
            'at_cap' => $cap > 0 && $used >= $cap,
            'percent' => $cap > 0 ? (int) round(min(100, $used / $cap * 100)) : 0,
            'detail' => $detail,
        ];
    }
}
