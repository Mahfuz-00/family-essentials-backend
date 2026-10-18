<?php

namespace App\Recurring;

use App\Enums\Cadence;
use Illuminate\Support\Carbon;

/**
 * When does this rule next come round?
 *
 * Pure date arithmetic, so it is unit-tested directly. The one rule that matters
 * is that the answer is always *strictly after* the reference day: that is what
 * makes a missed cycle get skipped rather than back-filled, and what makes the
 * spawn command idempotent for the day it runs.
 */
class RecurringSchedule
{
    public static function next(
        Cadence $cadence,
        ?int $weekday = null,
        ?int $dayOfMonth = null,
        ?Carbon $from = null,
    ): Carbon {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();

        return match ($cadence) {
            Cadence::Daily => $from->addDay(),
            Cadence::Weekly => self::nextWeekly($from, $weekday),
            Cadence::Monthly => self::nextMonthly($from, $dayOfMonth),
        };
    }

    /**
     * 0 = Sunday, matching Carbon's dayOfWeek.
     */
    private static function nextWeekly(Carbon $from, ?int $weekday): Carbon
    {
        // A weekly rule always carries a weekday (validation insists on it); if
        // one is somehow missing, keeping the same weekday is the honest fallback.
        $target = $weekday ?? (int) $from->dayOfWeek;
        $delta = ($target - (int) $from->dayOfWeek + 7) % 7;

        return $from->addDays($delta === 0 ? 7 : $delta);
    }

    /**
     * A short month clamps rather than overflowing: the 31st of the month runs
     * on the 28th (or 29th) in February, and on the 31st again in March.
     */
    private static function nextMonthly(Carbon $from, ?int $dayOfMonth): Carbon
    {
        $target = $dayOfMonth ?? (int) $from->day;
        $cursor = $from->copy()->startOfMonth();

        // Bounded: a target day always lands within the next couple of months.
        for ($month = 0; $month < 24; $month++) {
            $candidate = $cursor->copy()->addDays(min($target, $cursor->daysInMonth) - 1);

            if ($candidate->greaterThan($from)) {
                return $candidate;
            }

            $cursor->addMonth();
        }

        return $from->addMonth();
    }
}
