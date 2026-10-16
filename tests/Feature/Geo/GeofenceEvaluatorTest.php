<?php

namespace Tests\Feature\Geo;

use App\Enums\GeofenceEventType;
use App\Geo\GeoPoint;
use App\Geo\GeofenceEvaluator;
use Tests\TestCase;

class GeofenceEvaluatorTest extends TestCase
{
    private GeofenceEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new GeofenceEvaluator;
    }

    public function test_it_measures_the_distance_between_two_points(): void
    {
        $dhaka = new GeoPoint(23.8103, 90.4125);
        $north = new GeoPoint(23.8203, 90.4125); // 0.01 degrees of latitude

        // 0.01 degrees of latitude is ~1112 m at any longitude.
        $this->assertEqualsWithDelta(1112, $this->evaluator->distanceMeters($dhaka, $north), 15);
    }

    public function test_it_detects_being_within_a_radius(): void
    {
        $center = new GeoPoint(23.8103, 90.4125);

        $this->assertTrue($this->evaluator->isWithin($center, 300, new GeoPoint(23.8110, 90.4130)));
        $this->assertFalse($this->evaluator->isWithin($center, 300, new GeoPoint(23.8300, 90.4125)));
    }

    public function test_it_emits_enter_when_crossing_into_a_fence(): void
    {
        $transitions = $this->evaluator->evaluate(
            [$this->placeDefinition(7)],
            new GeoPoint(23.8105, 90.4125),
            [],
        );

        $this->assertCount(1, $transitions);
        $this->assertSame(7, $transitions[0]->placeId);
        $this->assertSame(GeofenceEventType::Enter, $transitions[0]->type);
    }

    public function test_it_emits_exit_when_leaving_a_fence(): void
    {
        $transitions = $this->evaluator->evaluate(
            [$this->placeDefinition(7)],
            new GeoPoint(23.8300, 90.4125),
            [7 => true],
        );

        $this->assertCount(1, $transitions);
        $this->assertSame(GeofenceEventType::Exit, $transitions[0]->type);
    }

    public function test_it_emits_nothing_while_staying_inside_or_outside(): void
    {
        $place = [$this->placeDefinition(7)];

        $this->assertCount(0, $this->evaluator->evaluate($place, new GeoPoint(23.8105, 90.4125), [7 => true]));
        $this->assertCount(0, $this->evaluator->evaluate($place, new GeoPoint(23.8300, 90.4125), [7 => false]));
    }

    /**
     * @return array{place_id: int, point: GeoPoint, radius: int}
     */
    private function placeDefinition(int $id): array
    {
        return ['place_id' => $id, 'point' => new GeoPoint(23.8103, 90.4125), 'radius' => 300];
    }
}
