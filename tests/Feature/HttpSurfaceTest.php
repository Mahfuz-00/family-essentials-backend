<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The backend serves two API surfaces and nothing else: the Blade screen
 * mockups are gone, so their URLs must not resolve any more.
 */
class HttpSurfaceTest extends TestCase
{
    public function test_the_root_describes_both_client_surfaces(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('application', 'Family Essentials API')
            ->assertJsonPath('surfaces.frontend.prefix', '/api/v1')
            ->assertJsonPath('surfaces.frontend.token_ability', 'dashboard')
            ->assertJsonPath('surfaces.mobile.prefix', '/mobile/v1')
            ->assertJsonPath('surfaces.mobile.token_ability', 'mobile');
    }

    public function test_the_removed_blade_mockup_routes_no_longer_resolve(): void
    {
        foreach ([
            '/login',
            '/register',
            '/admin/login',
            '/admin/dashboard',
            '/admin/tenants',
            '/admin/audit-logs',
            '/app/dashboard',
            '/app/members',
            '/app/places',
            '/app/billing',
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_the_backend_renders_no_views(): void
    {
        $views = glob(resource_path('views/**/*.blade.php')) ?: [];
        $top = glob(resource_path('views/*.blade.php')) ?: [];

        $this->assertSame([], array_merge($views, $top), 'No Blade views should ship with the API-only backend.');
    }
}
