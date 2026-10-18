<?php

namespace Tests\Unit;

use App\Enums\GeofenceEventType;
use App\Enums\LocationKind;
use App\Enums\PlaceCategory;
use App\Models\Place;
use Tests\TestCase;

/**
 * A place has one classification — `category` — and the notification-facing
 * "kind" is derived from it, so there is no second column to keep in step.
 */
class LocationKindTest extends TestCase
{
    public function test_each_category_maps_to_a_kind(): void
    {
        $this->assertSame(LocationKind::Home, PlaceCategory::Home->kind());
        $this->assertSame(LocationKind::Office, PlaceCategory::Office->kind());
        $this->assertSame(LocationKind::Store, PlaceCategory::Grocery->kind());
        $this->assertSame(LocationKind::Store, PlaceCategory::Pharmacy->kind());
        $this->assertSame(LocationKind::Other, PlaceCategory::School->kind());
        $this->assertSame(LocationKind::Other, PlaceCategory::Other->kind());
    }

    public function test_a_place_derives_its_kind_from_its_category(): void
    {
        $this->assertSame(LocationKind::Home, $this->place('home')->kind());
        $this->assertSame(LocationKind::Office, $this->place('office')->kind());
        $this->assertSame(LocationKind::Store, $this->place('pharmacy')->kind());
    }

    public function test_a_place_with_no_or_unknown_category_is_other(): void
    {
        // Older rows predate the enum, and `category` is a plain nullable varchar
        // with no database constraint — neither may blow up.
        $this->assertSame(LocationKind::Other, $this->place(null)->kind());
        $this->assertSame(LocationKind::Other, $this->place('somewhere-else')->kind());
    }

    public function test_the_cue_message_matches_the_kind_the_direction_and_the_count(): void
    {
        $enter = GeofenceEventType::Enter;
        $exit = GeofenceEventType::Exit;

        // Entering.
        $this->assertSame("You're home — 1 item still on the list.", LocationKind::Home->triggerMessage($enter, 1, 'Home'));
        $this->assertSame("You're home — 2 items still on the list.", LocationKind::Home->triggerMessage($enter, 2, 'Home'));
        $this->assertSame('At work: 1 task for you.', LocationKind::Office->triggerMessage($enter, 1, 'Office'));
        $this->assertSame('At work: 3 tasks for you.', LocationKind::Office->triggerMessage($enter, 3, 'Office'));
        $this->assertSame("You're at Whole Foods: 3 items.", LocationKind::Store->triggerMessage($enter, 3, 'Whole Foods'));
        $this->assertSame('At School Gate: 1 task.', LocationKind::Other->triggerMessage($enter, 1, 'School Gate'));

        // Leaving — the moment the product is really about.
        $this->assertSame('Leaving work: 2 items to pick up.', LocationKind::Office->triggerMessage($exit, 2, 'Office'));
        $this->assertSame('Leaving Whole Foods — 3 items still on the list.', LocationKind::Store->triggerMessage($exit, 3, 'Whole Foods'));
        $this->assertSame('You left home: 1 task still open.', LocationKind::Home->triggerMessage($exit, 1, 'Home'));
    }

    public function test_every_kind_has_a_label(): void
    {
        foreach (LocationKind::cases() as $kind) {
            $this->assertNotSame('', $kind->label());
        }

        $this->assertSame('Office', LocationKind::Office->label());
    }

    private function place(?string $category): Place
    {
        $place = new Place(['name' => 'Somewhere', 'category' => $category]);

        return $place;
    }
}
