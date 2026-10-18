<?php

namespace Tests\Feature\Recurring;

use App\Enums\Cadence;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The recurring-rule API, on both client surfaces.
 */
class RecurringRuleApiTest extends TestCase
{
    use CreatesTenants;

    /** A Sunday. */
    private const TODAY = '2026-10-18 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_member_can_create_list_update_and_delete_a_rule(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $sarah = $this->createMember($tenant, 'member', ['name' => 'Sarah']);
        $shop = $this->createPlace($tenant, 'Whole Foods Market');

        $token = $this->tokenFor($admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Weekly shop',
                'quantity' => '1 trolley',
                'category' => 'grocery',
                'cadence' => 'daily',
                'assignee_id' => $sarah->id,
                'place_id' => $shop->id,
                'amount_minor' => 250_000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.cadence', 'daily')
            ->assertJsonPath('data.active', true)
            // Created today, so the first run is tomorrow, never today.
            ->assertJsonPath('data.next_run_on', '2026-10-19')
            ->assertJsonPath('data.amount_minor', 250000)
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/recurring-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.next_run_on', '2026-10-19')
            ->assertJsonPath('data.0.active', true)
            ->assertJsonPath('data.0.place_name', 'Whole Foods Market')
            ->assertJsonPath('data.0.assignee.name', 'Sarah'); // resolves the member's name

        $this->withToken($token)
            ->patchJson("/api/v1/recurring-rules/{$id}", ['cadence' => 'weekly', 'weekday' => 3])
            ->assertOk()
            ->assertJsonPath('data.cadence', 'weekly')
            ->assertJsonPath('data.weekday', 3)
            // Recomputed from today: the coming Wednesday.
            ->assertJsonPath('data.next_run_on', '2026-10-21');

        $this->withToken($token)
            ->patchJson("/api/v1/recurring-rules/{$id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->withToken($token)
            ->deleteJson("/api/v1/recurring-rules/{$id}")
            ->assertOk();

        $this->withToken($token)->getJson('/api/v1/recurring-rules')->assertJsonCount(0, 'data');
    }

    public function test_a_cadence_that_needs_a_day_insists_on_one(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', ['title' => 'Bin day', 'cadence' => 'weekly'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekday');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', ['title' => 'Rent', 'cadence' => 'monthly'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('day_of_month');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Rent', 'cadence' => 'monthly', 'day_of_month' => 32,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('day_of_month');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Bin day', 'cadence' => 'weekly', 'weekday' => 8,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekday');
    }

    public function test_the_reminder_cue_needs_a_place_and_a_direction_together(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $home = $this->createPlace($tenant, 'Home');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Take the recycling out',
                'cadence' => 'weekly',
                'weekday' => 1,
                'location_trigger_place_id' => $home->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_trigger_direction');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Take the recycling out',
                'cadence' => 'weekly',
                'weekday' => 1,
                'location_trigger_direction' => 'exit',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_trigger_direction');
    }

    public function test_references_must_belong_to_the_same_household(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $other = $this->createTenant('Household B');
        $stranger = $this->createMember($other, 'member');
        $strangerPlace = $this->createPlace($other, 'Their house');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Nope', 'cadence' => 'daily', 'assignee_id' => $stranger->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assignee_id');

        $this->withToken($token)
            ->postJson('/api/v1/recurring-rules', [
                'title' => 'Nope', 'cadence' => 'daily', 'place_id' => $strangerPlace->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('place_id');
    }

    public function test_a_junior_may_read_the_rules_but_not_reconfigure_them(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $junior = $this->createMember($tenant, 'junior');

        $id = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/recurring-rules', ['title' => 'Bin day', 'cadence' => 'daily'])
            ->json('data.id');

        $juniorToken = $this->tokenFor($junior);

        $this->withToken($juniorToken)->getJson('/api/v1/recurring-rules')->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($juniorToken)
            ->postJson('/api/v1/recurring-rules', ['title' => 'Sneaky', 'cadence' => 'daily'])
            ->assertForbidden();

        $this->withToken($juniorToken)
            ->deleteJson("/api/v1/recurring-rules/{$id}")
            ->assertForbidden();
    }

    public function test_another_households_rule_is_invisible(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $adminB = $this->createMember($tenantB, 'tenant_admin');

        $foreign = $this->createRecurringRule($tenantB, $adminB, ['title' => 'Their bin day']);

        $this->withToken($this->tokenFor($adminA))
            ->getJson('/api/v1/recurring-rules')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($this->tokenFor($adminA))
            ->patchJson("/api/v1/recurring-rules/{$foreign->id}", ['title' => 'Mine now'])
            ->assertNotFound();

        $this->withToken($this->tokenFor($adminA))
            ->deleteJson("/api/v1/recurring-rules/{$foreign->id}")
            ->assertNotFound();

        // And the rule is untouched.
        $this->withBypass(fn () => $this->assertDatabaseHas('recurring_rules', [
            'id' => $foreign->id,
            'title' => 'Their bin day',
        ]));
    }

    public function test_the_mobile_surface_serves_the_same_rules(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $token = $this->postJson('/mobile/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'flutter',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->postJson('/mobile/v1/recurring-rules', ['title' => 'Mobile rule', 'cadence' => 'daily'])
            ->assertCreated();

        // Same controllers, same rows: the dashboard sees what the app created.
        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/recurring-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mobile rule')
            ->assertJsonPath('data.0.cadence', Cadence::Daily->value);
    }
}
