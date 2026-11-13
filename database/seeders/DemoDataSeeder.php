<?php

namespace Database\Seeders;

use App\Enums\Cadence;
use App\Enums\GeofenceEventType;
use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerEntrySource;
use App\Enums\LedgerRole;
use App\Enums\PlaceCategory;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Ledgers\LedgerMembership;
use App\Models\DeviceToken;
use App\Models\Essential;
use App\Models\FeedbackReport;
use App\Models\FinancialAsset;
use App\Models\FinancialGoal;
use App\Models\FinancialLiability;
use App\Models\FinancialTax;
use App\Models\Item;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Place;
use App\Models\RecurringRule;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo content for a development database.
 *
 * Deliberately shaped so every screen has something to show: a populated board
 * in all five buckets, saved places of each kind, a pantry with items due and
 * items healthy, feedback in three states, and two members opted in to arrival
 * notifications so the feature can be demonstrated without changing a setting.
 *
 * Safe to run repeatedly — each row is looked up by its natural key first.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * The household being seeded.
     *
     * Passed explicitly rather than relying on the `creating` hook: `db:seed`
     * runs the parent seeder with model events suppressed, so `tenant_id` is not
     * stamped automatically — and PostgreSQL's WITH CHECK would reject the row.
     */
    private int $tenantId = 0;

    public function run(): void
    {
        $tanvir = $this->findUser('tanvir@familyessentials.app');
        $sarah = $this->findUser('sarah@familyessentials.app');
        $bradley = $this->findUser('bradley@familyessentials.app');
        $elena = $this->findUser('elena@familyessentials.app');
        $platform = $this->findUser('platform@familyessentials.app');

        if ($tanvir === null || $sarah === null || $bradley === null || $elena === null) {
            $this->command?->warn('Demo accounts are missing — run DatabaseSeeder first.');

            return;
        }

        $tanvirHousehold = $this->householdOf($tanvir);
        $bradleyHousehold = $this->householdOf($bradley);

        if ($tanvirHousehold === null || $bradleyHousehold === null) {
            $this->command?->warn('Demo households are missing — run DatabaseSeeder first.');

            return;
        }

        // Two members opted in, two opted out: both paths of the arrival feature
        // are visible without anyone having to change a setting to test it.
        $this->optIn($tanvir, true);
        $this->optIn($bradley, true);
        $this->optIn($sarah, false);
        $this->optIn($elena, false);

        $this->tenantId = (int) $tanvirHousehold->id;
        Tenancy::forTenant(
            $tanvirHousehold->id,
            fn () => $this->seedTutorialHousehold($tanvir, $sarah, $platform),
            (int) $tanvir->id,
        );

        $this->tenantId = (int) $bradleyHousehold->id;
        Tenancy::forTenant(
            $bradleyHousehold->id,
            fn () => $this->seedSecondHousehold($bradley, $elena),
            (int) $bradley->id,
        );

        $this->command?->info(
            'Demo data seeded: places, requests, pantry, feedback, device tokens, '
            .'a household ledger and two recurring rules.'
        );
    }

    /**
     * The household that demonstrates everything: all three kinds of place, a
     * board with work in every column, and an opted-in admin.
     */
    private function seedTutorialHousehold(User $admin, User $member, ?User $platform): void
    {
        $home = $this->place('Home', PlaceCategory::Home, 23.8040, 90.4070, 250);
        $office = $this->place('Office', PlaceCategory::Office, 23.7980, 90.4150, 300);
        // The app's default demo coordinate, so "simulate arriving" lands here.
        $shop = $this->place('Whole Foods Market', PlaceCategory::Grocery, 23.8103, 90.4125, 300);
        $pharmacy = $this->place('CVS Pharmacy', PlaceCategory::Pharmacy, 23.8130, 90.4160, 200);
        $school = $this->place('School Gate', PlaceCategory::School, 23.8070, 90.4200, 250);

        $milk = $this->item('Milk', 'dairy', 'Whole Foods Market');
        $bread = $this->item('Bread', 'bakery', 'Whole Foods Market');
        $coffee = $this->item('Coffee beans', 'pantry', 'Whole Foods Market');
        $paracetamol = $this->item('Paracetamol', 'health', 'CVS Pharmacy');

        // Overdue and urgent, at home: "You have 1 household task..."
        $this->request($admin, [
            'title' => 'Fix the leaking kitchen tap',
            'priority' => RequestPriority::Urgent,
            'status' => RequestStatus::InProgress,
            'place_id' => $home->id,
            'assignee_id' => $admin->id,
            'due_at' => Carbon::now()->subDays(2)->setTime(18, 0),
            'notes' => 'Washer needs replacing — under the sink.',
        ]);

        // Overdue and assigned to the other member: her board, her notification.
        $this->request($admin, [
            'title' => 'Collect the prescription',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Pending,
            'place_id' => $pharmacy->id,
            'assignee_id' => $member->id,
            'item_id' => $paracetamol->id,
            'quantity' => '1 pack',
            'due_at' => Carbon::now()->subDay()->setTime(17, 30),
        ]);

        // Two open items at the shop: "You have 2 items to buy here."
        $this->request($admin, [
            'title' => 'Weekly shop',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Accepted,
            'place_id' => $shop->id,
            'assignee_id' => $admin->id,
            'item_id' => $milk->id,
            'quantity' => '2 litres',
            'due_at' => Carbon::now()->addHours(4),
        ]);

        $this->request($admin, [
            'title' => 'Fresh bread and eggs',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Pending,
            'place_id' => $shop->id,
            'assignee_id' => $admin->id,
            'item_id' => $bread->id,
            'quantity' => '1 loaf',
            'due_at' => Carbon::now()->addDay()->setTime(10, 0),
        ]);

        // At the office: "You have 1 task associated with work."
        $this->request($admin, [
            'title' => 'Drop off the signed contract',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Pending,
            'place_id' => $office->id,
            'assignee_id' => $admin->id,
            'due_at' => Carbon::now()->addDay()->setTime(11, 0),
        ]);

        $this->request($admin, [
            'title' => 'Pick up the school forms',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Pending,
            'place_id' => $school->id,
            'assignee_id' => $admin->id,
            'due_at' => Carbon::now()->addHours(6),
        ]);

        // Completed today, so that column is not empty.
        $this->request($admin, [
            'title' => 'Milk run',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Completed,
            'place_id' => $shop->id,
            'assignee_id' => $admin->id,
            'due_at' => Carbon::now()->subHours(3),
            'completed_at' => Carbon::now()->subHours(2),
        ]);

        // Backlog: no place, no due date — the quick-add shape.
        $this->request($admin, [
            'title' => 'Order more coffee beans',
            'priority' => RequestPriority::Low,
            'status' => RequestStatus::Pending,
            'item_id' => $coffee->id,
        ]);

        // Free text only: the pre-place shape, which still works.
        $this->request($admin, [
            'title' => 'Batteries for the remote',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Pending,
            'store_name' => 'Corner Shop',
            'assignee_id' => $member->id,
            'due_at' => Carbon::now()->addDays(2)->setTime(12, 0),
        ]);

        // No location at all.
        $this->request($admin, [
            'title' => 'Renew the car insurance',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Pending,
            'due_at' => Carbon::now()->addDays(3)->setTime(9, 0),
        ]);

        $this->request($admin, [
            'title' => 'Book the boiler service',
            'priority' => RequestPriority::Low,
            'status' => RequestStatus::Pending,
            'notes' => 'Any weekday morning works.',
        ]);

        // Pantry: two due (one by safety stock, one by cadence), one healthy,
        // one inactive.
        $this->essential('Milk', 'dairy', 'Whole Foods Market', 3, 1, 0, Carbon::now()->subDays(2));
        $this->essential('Bread', 'bakery', 'Whole Foods Market', 2, 1, 1, Carbon::now()->subDays(3));
        $this->essential('Coffee beans', 'pantry', 'Whole Foods Market', 14, 1, 4, Carbon::now()->subDays(2));
        $this->essential('Bin liners', 'home', 'Corner Shop', 30, 0, 0, Carbon::now()->subDays(40));
        $this->essential('Dish soap', 'cleaning', 'Corner Shop', 21, 1, 2, Carbon::now()->subDays(5), active: false);

        $this->device($admin, 'ios', 'demo-ios-tanvir');
        $this->device($member, 'android', 'demo-android-sarah');

        // Money: one ledger the admin owns, the other adult in it as a
        // summary-only member, and entries across two months and multiple categories
        // so the expense tracker and ledger breakdown are rich with data.
        $ledger = $this->ledger($admin, 'Household budget');
        $ledger->forceFill([
            'monthly_budget_minor' => 250_000_00,
            'weekly_budget_minor' => 60_000_00,
            'yearly_budget_minor' => 300_000_00,
        ])->save();
        $this->ledgerMembership($ledger, $admin, LedgerRole::Owner);
        $this->ledgerMembership($ledger, $member, LedgerRole::Summary);

        $this->ledgerEntry($ledger, $admin, [
            'description' => 'Monthly Apartment Rent',
            'category' => 'rent',
            'amount_minor' => 120_000_00,
            'occurred_on' => Carbon::today()->startOfMonth(),
        ]);

        $this->ledgerEntry($ledger, $admin, [
            'description' => 'Electric & Gas Utility Bill',
            'category' => 'utility_bill',
            'amount_minor' => 18_500_00,
            'occurred_on' => Carbon::today()->startOfMonth()->addDays(5),
        ]);

        $this->ledgerEntry($ledger, $admin, [
            'description' => 'Big Monthly Grocery Shop',
            'category' => 'grocery',
            'amount_minor' => 51_200_00,
            'occurred_on' => Carbon::today()->subMonthNoOverflow()->startOfMonth()->addDays(9),
        ]);

        $this->ledgerEntry($ledger, $member, [
            'description' => 'Internet & Mobile Plan',
            'category' => 'utility_bill',
            'amount_minor' => 12_000_00,
            'occurred_on' => Carbon::today()->subMonthNoOverflow()->endOfMonth(),
        ]);

        $this->ledgerEntry($ledger, $admin, [
            'description' => 'Pharmacy & Prescription Copay',
            'category' => 'healthcare',
            'amount_minor' => 6_850_00,
            'occurred_on' => Carbon::today()->startOfMonth(),
        ]);

        $this->ledgerEntry($ledger, $member, [
            'description' => 'Household Cleaning Supplies',
            'category' => 'household',
            'amount_minor' => 9_400_00,
            'occurred_on' => Carbon::today()->startOfMonth(),
        ]);

        $this->ledgerEntry($ledger, $admin, [
            'description' => 'Weekly Fresh Grocery Run',
            'category' => 'grocery',
            'amount_minor' => 24_500_00,
            'occurred_on' => Carbon::today(),
        ]);

        $this->ledgerEntry($ledger, $member, [
            'description' => 'Monthly Household Salary Contribution',
            'amount_minor' => 350_000_00,
            'direction' => LedgerEntryDirection::Income,
            'category' => null,
            'occurred_on' => Carbon::today()->startOfMonth(),
        ]);

        // Multi-item Request with dual quantities (asked vs got)
        $this->request($admin, [
            'title' => 'Weekly Organic Grocery Run',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::InProgress,
            'place_id' => $shop->id,
            'assignee_id' => $admin->id,
            'due_at' => Carbon::now()->addHours(3),
            'items' => [
                ['name' => 'Organic Whole Milk', 'quantity_asked' => '2 gal', 'quantity_got' => '2 gal', 'is_picked' => true],
                ['name' => 'Free-range Eggs', 'quantity_asked' => '1 dozen', 'quantity_got' => '1 dozen', 'is_picked' => true],
                ['name' => 'Sourdough Bread', 'quantity_asked' => '1 loaf', 'quantity_got' => '1 loaf', 'is_picked' => true],
                ['name' => 'Organic Bananas', 'quantity_asked' => '1 bunch', 'quantity_got' => '1 bunch', 'is_picked' => true],
            ],
            'quantity' => '4 items',
            'quantity_got' => '4 of 4 items picked',
            'amount_minor' => 4_850_00,
            'ledger_id' => $ledger->id,
        ]);

        // Financial Goals
        $this->financialGoal($admin, [
            'name' => 'Emergency Rainy Day Fund',
            'category' => 'savings',
            'target_amount_minor' => 100_000_00,
            'current_amount_minor' => 45_000_00,
            'currency' => 'USD',
            'target_date' => Carbon::today()->addMonths(8),
            'status' => 'active',
            'notes' => '6 months of living expenses safely deposited in high-yield account',
        ]);

        $this->financialGoal($admin, [
            'name' => 'Summer Family Beach Holiday',
            'category' => 'vacation',
            'target_amount_minor' => 35_000_00,
            'current_amount_minor' => 20_000_00,
            'currency' => 'USD',
            'target_date' => Carbon::today()->addMonths(4),
            'status' => 'active',
            'notes' => 'Flights and coastal accommodation',
        ]);

        $this->financialGoal($admin, [
            'name' => 'Home Solar & Energy Battery',
            'category' => 'home_improvement',
            'target_amount_minor' => 150_000_00,
            'current_amount_minor' => 32_000_00,
            'currency' => 'USD',
            'target_date' => Carbon::today()->addMonths(14),
            'status' => 'active',
            'notes' => 'Roof solar array installation',
        ]);

        // Financial Liabilities (Mortgage, Vehicle Loan)
        $this->financialLiability($admin, [
            'name' => 'Primary Home Mortgage',
            'type' => 'mortgage',
            'lender' => 'Chase Home Finance',
            'principal_amount_minor' => 340_000_00,
            'remaining_balance_minor' => 285_000_00,
            'monthly_payment_minor' => 18_500_00,
            'interest_rate_bps' => 415,
            'due_day_of_month' => 1,
            'currency' => 'USD',
            'status' => 'active',
            'notes' => '30-year fixed rate mortgage',
        ]);

        $this->financialLiability($admin, [
            'name' => 'Family Vehicle Loan',
            'type' => 'loan',
            'lender' => 'Ally Auto',
            'principal_amount_minor' => 28_000_00,
            'remaining_balance_minor' => 14_200_00,
            'monthly_payment_minor' => 3_850_00,
            'interest_rate_bps' => 375,
            'due_day_of_month' => 15,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        // Financial Assets (Real Estate, Cash, Investments)
        $this->financialAsset($admin, [
            'name' => 'Primary Family Residence',
            'type' => 'real_estate',
            'institution' => 'Zillow Valuation',
            'balance_minor' => 460_000_00,
            'currency' => 'USD',
            'notes' => 'Estimated real estate market valuation',
        ]);

        $this->financialAsset($admin, [
            'name' => 'High-Yield Savings Account',
            'type' => 'cash',
            'institution' => 'Marcus by Goldman Sachs',
            'balance_minor' => 28_500_00,
            'contribution_monthly_minor' => 5_000_00,
            'return_rate_bps' => 450,
            'currency' => 'USD',
            'notes' => '4.5% APY compounding monthly',
        ]);

        $this->financialAsset($admin, [
            'name' => 'S&P 500 Index Portfolio',
            'type' => 'investment',
            'institution' => 'Vanguard (VFIAX)',
            'balance_minor' => 74_200_00,
            'contribution_monthly_minor' => 6_000_00,
            'return_rate_bps' => 850,
            'currency' => 'USD',
        ]);

        // Financial Taxes
        $this->financialTax($admin, [
            'name' => 'Estimated Household Income Tax 2026',
            'type' => 'income_tax',
            'tax_year' => 2026,
            'amount_minor' => 34_000_00,
            'due_date' => Carbon::today()->addMonths(2),
            'status' => 'pending',
            'currency' => 'USD',
            'notes' => 'Q4 estimated federal payment',
        ]);

        $this->financialTax($admin, [
            'name' => 'Municipal Property Tax',
            'type' => 'property_tax',
            'tax_year' => 2026,
            'amount_minor' => 18_500_00,
            'due_date' => Carbon::today()->subMonths(1),
            'status' => 'paid',
            'paid_at' => Carbon::today()->subMonths(1),
            'currency' => 'USD',
        ]);

        // Chores that come round on their own: a weekly one that reminds on
        // arrival at the shop, and a monthly one that carries money into the
        // ledger. Both are due within days, so the board shows them moving.
        $this->recurringRule($admin, [
            'title' => 'Weekly grocery run',
            'cadence' => Cadence::Weekly,
            'weekday' => (int) Carbon::today()->addDays(3)->dayOfWeek,
            'next_run_on' => Carbon::today()->addDays(3)->toDateString(),
            'category' => 'grocery',
            'priority' => RequestPriority::Normal,
            'assignee_id' => $admin->id,
            'place_id' => $shop->id,
            'location_trigger_place_id' => $shop->id,
            'location_trigger_direction' => GeofenceEventType::Enter,
        ]);

        $this->recurringRule($admin, [
            'title' => 'Settle the household bills',
            'cadence' => Cadence::Monthly,
            'day_of_month' => (int) Carbon::today()->addDays(12)->day,
            'next_run_on' => Carbon::today()->addDays(12)->toDateString(),
            'category' => 'household',
            'priority' => RequestPriority::High,
            'notes' => 'Water, gas and internet in one payment.',
            'assignee_id' => $admin->id,
            'amount_minor' => 320_000,
            'ledger_id' => $ledger->id,
        ]);

        $this->feedback($admin, [
            'type' => 'bug',
            'priority' => 'high',
            'status' => 'new',
            'subject' => 'Shopping list loses items on reload',
            'body' => 'Add three items, pull the board to refresh, and only two come back. '
                .'Started after the last update.',
            'context' => ['platform' => 'web', 'app_version' => '1.4.0', 'screen' => '/requests'],
        ]);

        $this->feedback($member, [
            'type' => 'feature_request',
            'priority' => 'normal',
            'status' => 'triaged',
            'subject' => 'Let us export the shopping list',
            'body' => 'A shareable list would be easier than reading it off the phone at the till.',
            'context' => ['platform' => 'android', 'app_version' => '0.9.2', 'screen' => '/shopping'],
        ]);

        $this->feedback($admin, [
            'type' => 'bug',
            'priority' => 'normal',
            'status' => 'resolved',
            'subject' => 'Pantry reminder fired twice',
            'body' => 'Two notifications for the same essential, a few seconds apart.',
            'resolution_note' => 'Fixed in 1.4.1 — the dedupe key ignored the restock window.',
            'resolved_at' => Carbon::now()->subDay(),
            'resolved_by' => $platform?->id,
        ]);
    }

    /**
     * The second household exists to prove isolation, so it stays smaller — and
     * its member has arrival notifications off.
     */
    private function seedSecondHousehold(User $admin, User $member): void
    {
        $home = $this->place('Home', PlaceCategory::Home, 23.7800, 90.3900, 250);
        $office = $this->place('Office', PlaceCategory::Office, 23.7900, 90.4000, 300);
        $store = $this->place('Tesco Express', PlaceCategory::Grocery, 23.7850, 90.3950, 250);

        $milk = $this->item('Milk', 'dairy', 'Tesco Express');
        $cereal = $this->item('Cereal', 'breakfast', 'Tesco Express');

        $this->request($admin, [
            'title' => 'Take out the recycling',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Pending,
            'place_id' => $home->id,
            'assignee_id' => $member->id,
            'due_at' => Carbon::now()->subDay()->setTime(20, 0),
        ]);

        $this->request($admin, [
            'title' => 'Milk and cereal',
            'priority' => RequestPriority::Normal,
            'status' => RequestStatus::Accepted,
            'place_id' => $store->id,
            'assignee_id' => $admin->id,
            'item_id' => $milk->id,
            'quantity' => '2 pints',
            'due_at' => Carbon::now()->addHours(3),
        ]);

        $this->request($admin, [
            'title' => 'Service the bike',
            'priority' => RequestPriority::Low,
            'status' => RequestStatus::Pending,
            'place_id' => $office->id,
            'assignee_id' => $admin->id,
            'notes' => 'The back brake is rubbing.',
        ]);

        $this->essential('Milk', 'dairy', 'Tesco Express', 3, 1, 0, Carbon::now()->subDays(2));
        $this->essential('Cereal', 'breakfast', 'Tesco Express', 10, 1, 3, Carbon::now()->subDays(3));

        $this->device($admin, 'android', 'demo-android-bradley');

        $this->feedback($admin, [
            'type' => 'feature_request',
            'priority' => 'low',
            'status' => 'new',
            'subject' => 'Add a shared calendar view',
            'body' => 'Seeing everyone’s week in one grid would help plan the school run.',
            'context' => ['platform' => 'web', 'screen' => '/'],
        ]);
    }

    // ------------------------------------------------------------------ helpers

    private function findUser(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    private function householdOf(User $user): ?Tenant
    {
        if ($user->active_tenant_id === null) {
            return null;
        }

        return Tenancy::bypass(
            fn (): ?Tenant => Tenant::query()->find($user->active_tenant_id)
        );
    }

    private function optIn(User $user, bool $optIn): void
    {
        $user->forceFill(['location_notifications_opt_in' => $optIn])->save();
    }

    private function place(string $name, PlaceCategory $category, float $latitude, float $longitude, int $radiusMeters): Place
    {
        $existing = Place::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $place = new Place;

        $place->forceFill([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'category' => $category->value,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
        ])->save();

        return $place;
    }

    private function item(string $name, string $category, ?string $defaultStore = null): Item
    {
        $existing = Item::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $item = new Item;

        $item->forceFill([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'category' => $category,
            'default_store' => $defaultStore,
        ])->save();

        return $item;
    }

    /**
     * `status`, `requested_by` and `completed_at` are workflow-owned and not
     * mass-assignable, so a seeded request is filled by hand.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function request(User $requester, array $attributes): ServiceRequest
    {
        $existing = ServiceRequest::query()->where('title', $attributes['title'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $request = new ServiceRequest;

        $request->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'requested_by' => $requester->id,
            'status' => RequestStatus::Pending,
            'priority' => RequestPriority::Normal,
        ], $attributes))->save();

        return $request;
    }

    private function essential(
        string $name,
        string $category,
        ?string $store,
        int $cadenceDays,
        int $threshold,
        int $stockLevel,
        Carbon $lastRestocked,
        bool $active = true,
    ): Essential {
        $existing = Essential::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $essential = new Essential;

        $essential->forceFill([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'category' => $category,
            'default_store' => $store,
            'cadence_days' => $cadenceDays,
            'threshold' => $threshold,
            'stock_level' => $stockLevel,
            'last_restocked_at' => $lastRestocked,
            'is_active' => $active,
        ])->save();

        return $essential;
    }

    private function device(User $user, string $platform, string $token): void
    {
        DeviceToken::query()->firstOrCreate(
            ['token' => $token],
            [
                'tenant_id' => $this->tenantId,
                'user_id' => $user->id,
                'platform' => $platform,
                'last_used_at' => Carbon::now()->subMinutes(12),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function feedback(User $reporter, array $attributes): FeedbackReport
    {
        $existing = FeedbackReport::query()->where('subject', $attributes['subject'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $report = new FeedbackReport;

        $report->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'user_id' => $reporter->id,
            'type' => 'bug',
            'priority' => 'normal',
            'status' => 'new',
        ], $attributes))->save();

        return $report;
    }

    private function ledger(User $owner, string $name): Ledger
    {
        $existing = Ledger::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $ledger = new Ledger;

        $ledger->forceFill([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'owner_id' => $owner->id,
            'currency' => (string) config('ledger.currency'),
        ])->save();

        return $ledger;
    }

    /**
     * Added through the same service the API uses, so a seeded membership row is
     * shaped exactly like one the app wrote — and is idempotent by natural key.
     */
    private function ledgerMembership(Ledger $ledger, User $member, LedgerRole $role): void
    {
        app(LedgerMembership::class)->put($ledger, $member, $role);
    }

    /**
     * `amount_minor` is an integer count of minor units: the ledger never sees a
     * float.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ledgerEntry(Ledger $ledger, User $writer, array $attributes): LedgerEntry
    {
        $existing = LedgerEntry::query()->where('description', $attributes['description'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $entry = new LedgerEntry;

        $entry->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'ledger_id' => $ledger->id,
            'direction' => LedgerEntryDirection::Expense,
            'occurred_on' => Carbon::today(),
            'source' => LedgerEntrySource::Manual,
            'created_by' => $writer->id,
            'paid_by' => $writer->id,
        ], $attributes))->save();

        return $entry;
    }

    /**
     * A repeating chore. The rule is a recipe, never the task: the daily spawn
     * command turns each cycle into a fresh request.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recurringRule(User $creator, array $attributes): RecurringRule
    {
        $existing = RecurringRule::query()->where('title', $attributes['title'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $rule = new RecurringRule;

        $rule->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'created_by' => $creator->id,
            'priority' => RequestPriority::Normal,
            'active' => true,
        ], $attributes))->save();

        return $rule;
    }

    private function financialGoal(User $creator, array $attributes): FinancialGoal
    {
        $existing = FinancialGoal::query()->where('name', $attributes['name'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $goal = new FinancialGoal;
        $goal->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'created_by_id' => $creator->id,
            'currency' => 'USD',
            'status' => 'active',
        ], $attributes))->save();

        return $goal;
    }

    private function financialLiability(User $creator, array $attributes): FinancialLiability
    {
        $existing = FinancialLiability::query()->where('name', $attributes['name'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $liability = new FinancialLiability;
        $liability->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'created_by_id' => $creator->id,
            'currency' => 'USD',
            'status' => 'active',
        ], $attributes))->save();

        return $liability;
    }

    private function financialAsset(User $creator, array $attributes): FinancialAsset
    {
        $existing = FinancialAsset::query()->where('name', $attributes['name'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $asset = new FinancialAsset;
        $asset->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'created_by_id' => $creator->id,
            'currency' => 'USD',
        ], $attributes))->save();

        return $asset;
    }

    private function financialTax(User $creator, array $attributes): FinancialTax
    {
        $existing = FinancialTax::query()->where('name', $attributes['name'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $tax = new FinancialTax;
        $tax->forceFill(array_merge([
            'tenant_id' => $this->tenantId,
            'created_by_id' => $creator->id,
            'currency' => 'USD',
            'status' => 'pending',
        ], $attributes))->save();

        return $tax;
    }
}
