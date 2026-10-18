<?php

namespace Tests\Feature\Places;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The location classification the dashboard and the notifications share. Adding
 * `office` must not disturb the categories that were already there.
 */
class PlaceKindTest extends TestCase
{
    use CreatesTenants;

    private function token(): string
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'tenant_admin');

        return $this->tokenFor($member);
    }

    public function test_every_category_is_accepted_and_reports_its_kind(): void
    {
        $token = $this->token();
        $expected = [
            'home' => ['home', 'Home'],
            'office' => ['office', 'Office'],
            'grocery' => ['store', 'Store'],
            'pharmacy' => ['store', 'Store'],
            'school' => ['other', 'Other'],
            'other' => ['other', 'Other'],
        ];

        foreach ($expected as $category => [$kind, $label]) {
            $this->withToken($token)
                ->postJson('/api/v1/places', [
                    'name' => ucfirst($category).' place',
                    'latitude' => 23.81,
                    'longitude' => 90.41,
                    // One school and one home at the same point is fine; this is
                    // only about classification.
                    'category' => $category,
                ])
                ->assertCreated()
                ->assertJsonPath('data.category', $category)
                ->assertJsonPath('data.kind', $kind)
                ->assertJsonPath('data.kind_label', $label);
        }
    }

    public function test_a_place_without_a_category_still_works(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/places', [
                'name' => 'Unclassified',
                'latitude' => 23.81,
                'longitude' => 90.41,
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.kind', 'other');
    }

    public function test_an_unknown_category_is_rejected(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/places', [
                'name' => 'Nowhere',
                'latitude' => 23.81,
                'longitude' => 90.41,
                'category' => 'nightclub',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_the_category_can_be_filtered_as_before(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Office', 'latitude' => 23.81, 'longitude' => 90.41, 'category' => 'office',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'CVS', 'latitude' => 23.82, 'longitude' => 90.42, 'category' => 'pharmacy',
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/places?category=office')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Office');
    }
}
