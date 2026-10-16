<?php

namespace Tests\Unit;

use App\Models\Essential;
use Tests\TestCase;

/**
 * Unit scope: the pantry due rule in isolation. No HTTP, no routes.
 *
 * An essential is due when it has fallen to or below its safety-stock
 * threshold, or when its restock cadence has elapsed since the last restock.
 */
class EssentialDueRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The rule is clock-based, so pin the clock: an unfrozen now() makes
        // day arithmetic off by one through truncation.
        $this->freezeTime();
    }

    public function test_an_item_with_no_stock_is_due_for_safety_stock(): void
    {
        $essential = new Essential(['name' => 'Milk', 'stock_level' => 0, 'threshold' => 1]);

        $this->assertTrue($essential->isDue());
        $this->assertSame('safety_stock', $essential->dueReason());
    }

    public function test_an_item_at_the_threshold_is_due(): void
    {
        $essential = new Essential([
            'name' => 'Milk',
            'stock_level' => 1,
            'threshold' => 1,
            'last_restocked_at' => now(),
            'cadence_days' => 30,
        ]);

        $this->assertTrue($essential->isDue());
        $this->assertSame('safety_stock', $essential->dueReason());
    }

    public function test_a_stocked_item_inside_its_cadence_is_not_due(): void
    {
        $essential = new Essential([
            'name' => 'Rice',
            'stock_level' => 5,
            'threshold' => 1,
            'last_restocked_at' => now()->subDays(3),
            'cadence_days' => 30,
        ]);

        $this->assertFalse($essential->isDue());
        $this->assertNull($essential->dueReason());
        $this->assertSame(27, $essential->daysUntilDue());
    }

    public function test_a_stocked_item_becomes_due_once_the_cadence_elapses(): void
    {
        $essential = new Essential([
            'name' => 'Coffee',
            'stock_level' => 4,
            'threshold' => 0,
            'last_restocked_at' => now()->subDays(8),
            'cadence_days' => 7,
        ]);

        $this->assertTrue($essential->isDue());
        $this->assertSame('cadence', $essential->dueReason());
    }

    public function test_an_item_never_restocked_is_due(): void
    {
        $essential = new Essential(['name' => 'Nappies', 'stock_level' => 9, 'threshold' => 0]);

        $this->assertTrue($essential->isDue());
        $this->assertSame('cadence', $essential->dueReason());
    }

    public function test_an_inactive_item_is_never_due(): void
    {
        $essential = new Essential([
            'name' => 'Discontinued brand',
            'stock_level' => 0,
            'threshold' => 1,
            'is_active' => false,
        ]);

        $this->assertFalse($essential->isDue());
        $this->assertNull($essential->dueReason());
    }

    public function test_a_freshly_created_item_matches_the_table_defaults(): void
    {
        // Guards against the create()/reload mismatch that once made isDue()
        // report false until the model was fetched again.
        $essential = new Essential(['name' => 'Soap']);

        $this->assertSame(14, $essential->cadence_days);
        $this->assertSame(1, $essential->threshold);
        $this->assertSame(0, $essential->stock_level);
        $this->assertTrue($essential->is_active);
        $this->assertTrue($essential->isDue());
    }
}
