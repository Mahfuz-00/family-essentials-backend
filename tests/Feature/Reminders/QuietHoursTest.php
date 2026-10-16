<?php

namespace Tests\Feature\Reminders;

use App\Models\Tenant;
use App\Models\User;
use App\Reminders\QuietHours;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuietHoursTest extends TestCase
{
    private function tenant(array $quietHours = ['enabled' => true, 'start' => '22:00', 'end' => '07:00']): Tenant
    {
        return (new Tenant)->forceFill(['settings' => ['quiet_hours' => $quietHours]]);
    }

    private function user(string $timezone = 'UTC'): User
    {
        return (new User)->forceFill(['timezone' => $timezone]);
    }

    public function test_a_window_that_wraps_midnight(): void
    {
        $quiet = app(QuietHours::class);
        $tenant = $this->tenant();
        $user = $this->user();

        $this->assertTrue($quiet->isQuiet($tenant, $user, Carbon::parse('2026-10-16 23:30:00', 'UTC')));
        $this->assertTrue($quiet->isQuiet($tenant, $user, Carbon::parse('2026-10-16 03:00:00', 'UTC')));
        $this->assertFalse($quiet->isQuiet($tenant, $user, Carbon::parse('2026-10-16 12:00:00', 'UTC')));
        $this->assertFalse($quiet->isQuiet($tenant, $user, Carbon::parse('2026-10-16 07:00:00', 'UTC')));
    }

    public function test_it_evaluates_in_the_recipients_timezone(): void
    {
        $quiet = app(QuietHours::class);
        $tenant = $this->tenant();
        $dhaka = $this->user('Asia/Dhaka'); // UTC+6, no DST

        // 23:30 UTC == 05:30 in Dhaka -> inside 22:00-07:00
        $this->assertTrue($quiet->isQuiet($tenant, $dhaka, Carbon::parse('2026-10-16 23:30:00', 'UTC')));

        // 02:00 UTC == 08:00 in Dhaka -> outside the window
        $this->assertFalse($quiet->isQuiet($tenant, $dhaka, Carbon::parse('2026-10-16 02:00:00', 'UTC')));
    }

    public function test_it_computes_when_delivery_may_resume(): void
    {
        $quiet = app(QuietHours::class);

        $resume = $quiet->resumeAt(
            $this->tenant(),
            $this->user(),
            Carbon::parse('2026-10-16 23:30:00', 'UTC'),
        );

        $this->assertSame('2026-10-17 07:00:00', $resume->setTimezone('UTC')->toDateTimeString());
    }

    public function test_disabled_quiet_hours_never_apply(): void
    {
        $quiet = app(QuietHours::class);

        $this->assertFalse(
            $quiet->isQuiet(
                $this->tenant(['enabled' => false, 'start' => '22:00', 'end' => '07:00']),
                $this->user(),
                Carbon::parse('2026-10-16 23:30:00', 'UTC'),
            )
        );
    }
}
