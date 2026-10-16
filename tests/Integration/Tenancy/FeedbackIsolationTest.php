<?php

namespace Tests\Integration\Tenancy;

use App\Models\FeedbackReport;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The database-level barrier for feedback, not the endpoint's.
 *
 * A household's reports are invisible without a tenant context — enforced by
 * PostgreSQL, so a missed `where` in application code cannot leak them.
 */
class FeedbackIsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_feedback_reports_carry_forced_row_level_security(): void
    {
        $flags = DB::selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where relname = ?',
            ['feedback_reports']
        );

        $this->assertTrue($flags->relrowsecurity, 'RLS is not enabled on feedback_reports');
        $this->assertTrue($flags->relforcerowsecurity, 'RLS is not FORCED on feedback_reports');
    }

    public function test_no_tenant_context_returns_no_reports(): void
    {
        $tenant = $this->createTenant('Household A');
        $reporter = $this->createMember($tenant, 'member');

        $this->withBypass(fn () => FeedbackReport::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'user_id' => $reporter->id,
            'type' => 'bug',
            'subject' => 'Exist only inside the household',
            'body' => 'Enough detail to be a valid report body.',
        ]));

        // Drop the tenant context and the bypass: the runtime role must see nothing.
        DB::statement("select set_config('app.current_tenant', '', false)");
        DB::statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, DB::table('feedback_reports')->count());
    }

    public function test_the_platform_console_reads_across_households_only_with_the_bypass(): void
    {
        $one = $this->createTenant('Household A');
        $two = $this->createTenant('Household B');

        foreach ([$one, $two] as $tenant) {
            $this->withBypass(fn () => FeedbackReport::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'user_id' => $this->createMember($tenant, 'member')->id,
                'type' => 'bug',
                'subject' => "A report from {$tenant->name}",
                'body' => 'Enough detail to be a valid report body.',
            ]));
        }

        $this->withBypass(function (): void {
            $this->assertSame(2, DB::table('feedback_reports')->count());
        });

        DB::statement("select set_config('app.bypass_rls', 'off', false)");
        DB::statement("select set_config('app.current_tenant', '".$one->id."', false)");

        // One household's context sees exactly one household's reports.
        $this->assertSame(1, DB::table('feedback_reports')->count());
    }
}
