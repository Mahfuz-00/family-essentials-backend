<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Sms\SmsSender;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    private function fakeSms(): object
    {
        $sender = new class implements SmsSender
        {
            /** @var array<int, array{to: string, message: string}> */
            public array $messages = [];

            public function send(string $to, string $message): void
            {
                $this->messages[] = ['to' => $to, 'message' => $message];
            }
        };

        $this->app->instance(SmsSender::class, $sender);

        return $sender;
    }

    public function test_a_user_can_verify_their_phone_with_the_sent_code(): void
    {
        $sender = $this->fakeSms();

        $user = User::factory()->unverified()->create(['phone' => '+15550001111']);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/phone/send-code')
            ->assertStatus(202);

        $this->assertDatabaseCount('phone_verification_codes', 1);
        $this->assertCount(1, $sender->messages);

        preg_match('/(\d{6})/', $sender->messages[0]['message'], $matches);
        $this->assertNotEmpty($matches, 'The SMS should contain a 6-digit code.');

        $this->withToken($token)
            ->postJson('/api/v1/auth/phone/verify', ['code' => $matches[1]])
            ->assertOk()
            ->assertJsonPath('user.phone_verified', true);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_an_incorrect_code_is_rejected(): void
    {
        $this->fakeSms();

        $user = User::factory()->unverified()->create(['phone' => '+15550002222']);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/phone/send-code')->assertStatus(202);

        $this->withToken($token)
            ->postJson('/api/v1/auth/phone/verify', ['code' => '000000'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/phone/send-code')->assertUnauthorized();
        $this->postJson('/api/v1/auth/phone/verify', ['code' => '123456'])->assertUnauthorized();
    }
}
