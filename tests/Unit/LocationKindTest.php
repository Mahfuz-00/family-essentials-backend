<?php

namespace Tests\Unit;

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

    public function test_the_arrival_message_matches_the_kind_and_the_count(): void
    {
        $this->assertSame('You have 1 household task to take care of.', LocationKind::Home->arrivalMessage(1));
        $this->assertSame('You have 2 household tasks to take care of.', LocationKind::Home->arrivalMessage(2));

        $this->assertSame('You have 1 task associated with work.', LocationKind::Office->arrivalMessage(1));
        $this->assertSame('You have 3 tasks associated with work.', LocationKind::Office->arrivalMessage(3));

        $this->assertSame('You have 1 item to buy here.', LocationKind::Store->arrivalMessage(1));
        $this->assertSame('You have 3 items to buy here.', LocationKind::Store->arrivalMessage(3));

        $this->assertSame('You have 1 task here.', LocationKind::Other->arrivalMessage(1));
        $this->assertSame('You have 4 tasks here.', LocationKind::Other->arrivalMessage(4));
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
