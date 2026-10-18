<?php

namespace Tests\Unit\Recurring;

use App\Enums\Cadence;
use App\Recurring\RecurringSchedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure date arithmetic: when does a rule next come round?
 *
 * The invariant under test is that the answer is always *strictly after* the
 * reference day — that is what makes a missed cycle get skipped instead of
 * replayed, and a rule created today start tomorrow rather than in the past.
 */
class RecurringScheduleTest extends TestCase
{
    public function test_daily_advances_one_day(): void
    {
        $next = RecurringSchedule::next(Cadence::Daily, null, null, Carbon::parse('2026-10-18'));

        $this->assertSame('2026-10-19', $next->toDateString());
    }

    public function test_weekly_lands_on_the_next_matching_weekday(): void
    {
        // 2026-10-18 is a Sunday. A Wednesday rule (3) is three days away…
        $wednesday = RecurringSchedule::next(Cadence::Weekly, 3, null, Carbon::parse('2026-10-18'));
        $this->assertSame('2026-10-21', $wednesday->toDateString());
        $this->assertSame(3, $wednesday->dayOfWeek);

        // …and the same weekday is a full week away, never "today".
        $sunday = RecurringSchedule::next(Cadence::Weekly, 0, null, Carbon::parse('2026-10-18'));
        $this->assertSame('2026-10-25', $sunday->toDateString());
        $this->assertSame(0, $sunday->dayOfWeek);
    }

    public function test_monthly_keeps_the_same_day_of_the_month(): void
    {
        $next = RecurringSchedule::next(Cadence::Monthly, null, 15, Carbon::parse('2026-10-01'));

        $this->assertSame('2026-10-15', $next->toDateString());
    }

    public function test_monthly_clamps_the_thirty_first_to_a_short_month(): void
    {
        // January the 31st moves to the 28th in February…
        $february = RecurringSchedule::next(Cadence::Monthly, null, 31, Carbon::parse('2026-01-31'));
        $this->assertSame('2026-02-28', $february->toDateString());

        // …and back to the 31st in March.
        $march = RecurringSchedule::next(Cadence::Monthly, null, 31, $february);
        $this->assertSame('2026-03-31', $march->toDateString());
    }

    public function test_monthly_clamps_to_the_twenty_ninth_in_a_leap_february(): void
    {
        $next = RecurringSchedule::next(Cadence::Monthly, null, 31, Carbon::parse('2028-01-31'));

        $this->assertSame('2028-02-29', $next->toDateString());
    }

    public function test_the_next_run_is_never_today(): void
    {
        $today = Carbon::parse('2026-10-18');

        $this->assertSame('2026-10-19', RecurringSchedule::next(Cadence::Daily, null, null, $today)->toDateString());
        $this->assertTrue(RecurringSchedule::next(Cadence::Monthly, null, 18, $today)->greaterThan($today));
        $this->assertTrue(RecurringSchedule::next(Cadence::Weekly, 0, null, $today)->greaterThan($today));
    }
}
