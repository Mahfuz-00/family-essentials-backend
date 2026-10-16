<?php

namespace App\Reminders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Household quiet hours (stored in tenant settings) evaluated in each
 * recipient's own timezone.
 */
class QuietHours
{
    public function isQuiet(Tenant $tenant, User $user, Carbon $moment): bool
    {
        $window = $this->window($tenant);

        if ($window === null) {
            return false;
        }

        [$start, $end] = $window;
        $minutes = $this->localMinutes($user, $moment);

        // Windows may wrap midnight (e.g. 22:00 -> 07:00).
        return $start <= $end
            ? $minutes >= $start && $minutes < $end
            : $minutes >= $start || $minutes < $end;
    }

    /**
     * The next instant at which delivery may resume.
     */
    public function resumeAt(Tenant $tenant, User $user, Carbon $moment): Carbon
    {
        $window = $this->window($tenant);

        if ($window === null) {
            return $moment;
        }

        [, $end] = $window;

        $local = $moment->copy()->setTimezone($this->timezone($user));
        $resume = $local->copy()->startOfDay()->addMinutes($end);

        if ($resume->lessThanOrEqualTo($local)) {
            $resume = $resume->addDay();
        }

        return $resume->setTimezone('UTC');
    }

    /**
     * @return array{0: int, 1: int}|null [startMinutes, endMinutes]
     */
    private function window(Tenant $tenant): ?array
    {
        $settings = $tenant->settings ?? [];
        $quiet = $settings['quiet_hours'] ?? null;

        if (! is_array($quiet) || empty($quiet['enabled'])) {
            return null;
        }

        return [
            $this->minutes((string) ($quiet['start'] ?? '22:00')),
            $this->minutes((string) ($quiet['end'] ?? '07:00')),
        ];
    }

    private function localMinutes(User $user, Carbon $moment): int
    {
        $local = $moment->copy()->setTimezone($this->timezone($user));

        return ($local->hour * 60) + $local->minute;
    }

    private function timezone(User $user): string
    {
        return $user->timezone ?: 'UTC';
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }
}
