<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    public function test_email_can_be_verified_via_a_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_rejects_a_tampered_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url.'tampered')->assertStatus(403);
    }

    public function test_a_user_can_request_a_new_verification_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resending_when_already_verified_conflicts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(409);
    }
}
