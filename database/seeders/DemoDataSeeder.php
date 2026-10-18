<?php

namespace Database\Seeders;

use App\Enums\PlaceCategory;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\DeviceToken;
use App\Models\Essential;
use App\Models\FeedbackReport;
use App\Models\Item;
use App\Models\Place;
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

        $this->command?->info('Demo data seeded: places, requests, pantry, feedback and device tokens.');
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
}
