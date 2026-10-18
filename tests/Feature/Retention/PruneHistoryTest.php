<?php

namespace Tests\Feature\Retention;

use App\Enums\GeofenceEventType;
use App\Enums\LocationKind;
use App\Models\AuditEvent;
use App\Models\FeedbackReport;
use App\Models\GeofenceEvent;
use App\Models\LocationNotification;
use App\Models\NotificationLog;
use App\Models\Place;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceCallLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * `essentials:prune-history` — the retention policy in config/retention.php.
 *
 * The clock is frozen, so each test states what the window *is* rather than
 * what today's date happens to make of it.
 */
class PruneHistoryTest extends TestCase
{
    use CreatesTenants;

    private const NOW = '2026-10-18 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_row_beyond_the_window_is_deleted_and_one_inside_it_is_kept(): void
    {
        $household = $this->household();

        // 90 days before today is 2026-07-20, so one row is just inside the
        // window and one just outside it.
        $kept = $this->geofenceEvent($household, Carbon::now()->subDays(89));
        $this->geofenceEvent($household, Carbon::now()->subDays(91)->subSecond());

        $this->artisan('essentials:prune-history')
            ->expectsOutputToContain('geofence_events')
            ->assertSuccessful();

        $this->assertSame([$kept->id], $this->keys('geofence_events'));
    }

    public function test_every_window_in_the_config_is_applied(): void
    {
        $household = $this->household();

        // Inside 180 days: the arrival decision and the delivery log survive.
        $this->locationNotification($household, Carbon::now()->subDays(100));
        $this->notificationLog($household, Carbon::now()->subDays(100));

        // Outside 180 days, but inside the 365-day voice window.
        $this->locationNotification($household, Carbon::now()->subDays(200));
        $this->notificationLog($household, Carbon::now()->subDays(200));
        $this->voiceCallLog($household, Carbon::now()->subDays(200));

        // Outside every window except the ledger ones.
        $this->voiceCallLog($household, Carbon::now()->subDays(400));

        $this->artisan('essentials:prune-history')
            ->expectsOutputToContain('location_notifications')
            ->expectsOutputToContain('notification_logs')
            ->expectsOutputToContain('voice_call_logs')
            ->assertSuccessful();

        $this->assertCountRows(1, 'location_notifications');
        $this->assertCountRows(1, 'notification_logs');
        $this->assertCountRows(1, 'voice_call_logs');
    }

    public function test_batching_reaps_every_old_row_and_stops_at_the_window(): void
    {
        $household = $this->household();

        config(['retention.batch_size' => 2]);

        for ($i = 0; $i < 5; $i++) {
            $this->geofenceEvent($household, Carbon::now()->subDays(120));
        }

        $kept = $this->geofenceEvent($household, Carbon::now()->subDays(10));

        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertSame([$kept->id], $this->keys('geofence_events'));
    }

    public function test_it_reaps_every_household_not_only_the_one_in_context(): void
    {
        $one = $this->household('Household A');
        $two = $this->household('Household B');

        $this->geofenceEvent($one, Carbon::now()->subDays(120));
        $this->geofenceEvent($two, Carbon::now()->subDays(120));

        // The tenant tables are FORCE ROW LEVEL SECURITY, so a command that did
        // not enable the bypass would find nothing and report a clean sweep.
        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertCountRows(0, 'geofence_events');
    }

    public function test_a_dry_run_reports_without_deleting_anything(): void
    {
        $household = $this->household();

        $this->geofenceEvent($household, Carbon::now()->subDays(400));
        $this->voiceCallLog($household, Carbon::now()->subDays(400));
        $expired = $household['admin']->createToken('expired', ['dashboard'], Carbon::now()->subDay());

        $this->artisan('essentials:prune-history', ['--dry-run' => true])
            ->expectsOutputToContain('geofence_events')
            ->expectsOutputToContain('voice_call_logs')
            ->expectsOutputToContain('personal_access_tokens')
            ->expectsOutputToContain('would be deleted')
            ->assertSuccessful();

        $this->assertCountRows(1, 'geofence_events');
        $this->assertCountRows(1, 'voice_call_logs');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $expired->accessToken->id]);
    }

    public function test_dry_run_reports_the_rows_it_would_have_deleted(): void
    {
        $household = $this->household();

        $this->geofenceEvent($household, Carbon::now()->subDays(400));

        $this->artisan('essentials:prune-history', ['--dry-run' => true])
            ->expectsOutputToContain('1 would be deleted')
            ->assertSuccessful();

        $this->assertCountRows(1, 'geofence_events');
    }

    public function test_a_table_with_no_configured_window_is_left_alone(): void
    {
        $household = $this->household();

        // feedback_reports is listed with `days => null`: a ledger, kept forever
        // until somebody asks for a window.
        $this->feedbackReport($household, Carbon::now()->subDays(900));

        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertCountRows(1, 'feedback_reports');
    }

    public function test_a_table_absent_from_the_config_is_never_touched(): void
    {
        $household = $this->household();

        $this->geofenceEvent($household, Carbon::now()->subDays(900));

        config(['retention.tables' => []]);

        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertCountRows(1, 'geofence_events');
    }

    public function test_the_audit_ledger_survives_the_default_run(): void
    {
        // Deliberately ancient: the point is that age alone never reaps it.
        AuditEvent::create([
            'trace_id' => '01HZZZZZZZZZZZZZZZZZZZZZAA',
            'occurred_at' => Carbon::now()->subYears(5),
            'level' => 'info',
            'action' => 'tenant.created',
        ]);

        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_the_audit_ledger_is_reaped_once_a_window_is_configured(): void
    {
        AuditEvent::create([
            'trace_id' => '01HZZZZZZZZZZZZZZZZZZZZZBB',
            'occurred_at' => Carbon::now()->subDays(400),
            'level' => 'info',
            'action' => 'tenant.created',
        ]);

        $recent = AuditEvent::create([
            'trace_id' => '01HZZZZZZZZZZZZZZZZZZZZZCC',
            'occurred_at' => Carbon::now()->subDays(10),
            'level' => 'info',
            'action' => 'tenant.created',
        ]);

        config(['retention.tables.audit_events.days' => 365]);

        $this->artisan('essentials:prune-history')->assertSuccessful();

        $this->assertSame([$recent->id], AuditEvent::query()->pluck('id')->all());
    }

    public function test_expired_tokens_are_deleted_and_a_live_one_is_kept(): void
    {
        $admin = $this->household()['admin'];

        $admin->createToken('expired', ['dashboard'], Carbon::now()->subMinute());
        $live = $admin->createToken('live', ['dashboard'], Carbon::now()->addDay());
        // A token with no expiry is a deliberate choice, not litter.
        $forever = $admin->createToken('forever', ['dashboard']);

        $this->artisan('essentials:prune-history')->assertSuccessful();

        // Exactly the two that are still useful, and the expired one is gone.
        $this->assertSame(
            [$live->accessToken->id, $forever->accessToken->id],
            $admin->tokens()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_spent_phone_codes_are_deleted_and_a_usable_one_is_kept(): void
    {
        $household = $this->household();
        $admin = $household['admin'];

        $usable = $admin->phoneVerificationCodes()->create([
            'code_hash' => 'hash',
            'expires_at' => Carbon::now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // Timed out.
        $admin->phoneVerificationCodes()->create([
            'code_hash' => 'hash',
            'expires_at' => Carbon::now()->subMinute(),
            'attempts' => 1,
        ]);

        // Attempt budget exhausted without ever matching.
        $admin->phoneVerificationCodes()->create([
            'code_hash' => 'hash',
            'expires_at' => Carbon::now()->addMinutes(5),
            'attempts' => (int) config('sms.max_attempts'),
        ]);

        $this->artisan('essentials:prune-history')
            ->expectsOutputToContain('phone_verification_codes')
            ->assertSuccessful();

        $this->assertSame([$usable->id], $admin->phoneVerificationCodes()->pluck('id')->all());
    }

    public function test_a_second_run_has_nothing_left_to_do(): void
    {
        $household = $this->household();

        $this->geofenceEvent($household, Carbon::now()->subDays(400));
        $kept = $this->geofenceEvent($household, Carbon::now()->subDays(1));

        $this->artisan('essentials:prune-history')->assertSuccessful();
        $this->artisan('essentials:prune-history')
            ->expectsOutputToContain('0 deleted')
            ->assertSuccessful();

        $this->assertSame([$kept->id], $this->keys('geofence_events'));
    }

    // ----------------------------------------------------------------- fixtures

    /**
     * @return array{tenant: Tenant, admin: User, place: Place}
     */
    private function household(string $name = 'Household A'): array
    {
        $tenant = $this->createTenant($name);
        $admin = $this->createMember($tenant, 'tenant_admin');

        return [
            'tenant' => $tenant,
            'admin' => $admin,
            'place' => $this->createPlace($tenant, 'Home'),
        ];
    }

    /**
     * @param  array{tenant: Tenant, admin: User, place: Place}  $household
     */
    private function geofenceEvent(array $household, Carbon $occurredAt): GeofenceEvent
    {
        return $this->withBypass(fn (): GeofenceEvent => GeofenceEvent::create([
            'tenant_id' => $household['tenant']->id,
            'place_id' => $household['place']->id,
            'user_id' => $household['admin']->id,
            'event' => GeofenceEventType::Enter,
            'occurred_at' => $occurredAt,
        ]));
    }

    /**
     * `created_at` is not mass-assignable on these models, so the fixtures are
     * back-dated with forceFill — the same way the demo seeder writes rows it
     * wants to look older than they are.
     *
     * @param  array{tenant: Tenant, admin: User, place: Place}  $household
     */
    private function locationNotification(array $household, Carbon $createdAt): LocationNotification
    {
        return $this->withBypass(function () use ($household, $createdAt): LocationNotification {
            $notification = new LocationNotification;

            $notification->forceFill([
                'tenant_id' => $household['tenant']->id,
                'place_id' => $household['place']->id,
                'user_id' => $household['admin']->id,
                'status' => 'sent',
                'kind' => LocationKind::Home->value,
                'title' => 'You are home',
                'idempotency_key' => 'arrival-'.$createdAt->getTimestamp(),
                'notified_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            return $notification;
        });
    }

    /**
     * @param  array{tenant: Tenant, admin: User, place: Place}  $household
     */
    private function notificationLog(array $household, Carbon $createdAt): NotificationLog
    {
        return $this->withBypass(function () use ($household, $createdAt): NotificationLog {
            $log = new NotificationLog;

            $log->forceFill([
                'tenant_id' => $household['tenant']->id,
                'user_id' => $household['admin']->id,
                'channel' => 'push',
                'event' => 'reminder.due',
                'status' => 'sent',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            return $log;
        });
    }

    /**
     * @param  array{tenant: Tenant, admin: User, place: Place}  $household
     */
    private function voiceCallLog(array $household, Carbon $createdAt): VoiceCallLog
    {
        return $this->withBypass(function () use ($household, $createdAt): VoiceCallLog {
            $call = new VoiceCallLog;

            $call->forceFill([
                'tenant_id' => $household['tenant']->id,
                'user_id' => $household['admin']->id,
                'idempotency_key' => 'call-'.$createdAt->getTimestamp(),
                'status' => 'completed',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            return $call;
        });
    }

    /**
     * @param  array{tenant: Tenant, admin: User, place: Place}  $household
     */
    private function feedbackReport(array $household, Carbon $createdAt): FeedbackReport
    {
        return $this->withBypass(function () use ($household, $createdAt): FeedbackReport {
            $report = new FeedbackReport;

            $report->forceFill([
                'tenant_id' => $household['tenant']->id,
                'user_id' => $household['admin']->id,
                'type' => 'bug',
                'priority' => 'normal',
                'status' => 'resolved',
                'subject' => 'Ancient report',
                'body' => 'Kept because the ledger default is forever.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            return $report;
        });
    }

    // ------------------------------------------------------------------ asserts

    /**
     * Tenant tables are FORCE ROW LEVEL SECURITY, so even a test has to lift the
     * bypass to see what the command left behind.
     */
    private function assertCountRows(int $expected, string $table): void
    {
        $actual = $this->withBypass(fn (): int => DB::table($table)->count());

        $this->assertSame($expected, $actual, "Expected {$expected} row(s) in {$table}.");
    }

    /**
     * @return array<int, int>
     */
    private function keys(string $table): array
    {
        return $this->withBypass(
            fn (): array => DB::table($table)->orderBy('id')->pluck('id')->all(),
        );
    }
}
