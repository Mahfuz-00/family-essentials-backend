<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_a_user_can_register_and_receives_a_token_and_household(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Tanvir Rahman',
            'email' => 'tanvir@example.com',
            'phone' => '+15550192849',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
            'household_name' => 'The Rahman Household',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'email_verified', 'phone_verified'],
                'tenant' => ['id', 'name', 'slug', 'status'],
                'token',
            ])
            ->assertJsonPath('user.email', 'tanvir@example.com')
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.phone_verified', false)
            ->assertJsonPath('tenant.name', 'The Rahman Household');

        $this->assertDatabaseHas('users', ['email' => 'tanvir@example.com', 'phone' => '+15550192849']);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user = User::where('email', 'tanvir@example.com')->firstOrFail();
        $this->assertNotNull($user->active_tenant_id);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_email_and_phone(): void
    {
        User::factory()->create(['email' => 'dup@example.com', 'phone' => '+10000000000']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'dup@example.com',
            'phone' => '+10000000000',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'No Confirm',
            'email' => 'nc@example.com',
            'phone' => '+10000000001',
            'password' => 'Password!2345',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
