<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com', 'password' => 'Password!2345']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password!2345',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.email', 'login@example.com');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($user->fresh()->last_active_at);
    }

    public function test_login_fails_with_an_invalid_password(): void
    {
        User::factory()->create(['email' => 'login@example.com', 'password' => 'Password!2345']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_an_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'me@example.com');
    }
}
