<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_a_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'OldPassword!2345',
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword!2345',
            'password_confirmation' => 'NewPassword!2345',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword!2345', $user->fresh()->password));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-valid-token',
            'email' => 'reset@example.com',
            'password' => 'NewPassword!2345',
            'password_confirmation' => 'NewPassword!2345',
        ])->assertStatus(422);
    }
}
