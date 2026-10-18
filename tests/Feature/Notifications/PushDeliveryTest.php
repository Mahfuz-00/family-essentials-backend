<?php

namespace Tests\Feature\Notifications;

use App\Enums\AlertStyle;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Channels\DevicePushSender;
use App\Notifications\Channels\FcmPushTransport;
use App\Notifications\Channels\PushTransport;
use App\Notifications\PushMessage;
use App\Support\GoogleServiceAccountToken;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Delivery: which device gets the message, and what the payload says.
 *
 * The provider calls are faked here, so this proves the routing and the shape —
 * not that Google or Apple accepted anything.
 */
class PushDeliveryTest extends TestCase
{
    use CreatesTenants;

    public function test_the_alert_style_is_settable_on_both_surfaces(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->patchJson('/api/v1/auth/alert-style', ['alert_style' => 'vibrate'])
            ->assertOk()
            ->assertJsonPath('user.alert_style', 'vibrate');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.alert_style', 'vibrate');

        $this->withToken($token)
            ->patchJson('/mobile/v1/auth/alert-style', ['alert_style' => 'voice'])
            ->assertOk()
            ->assertJsonPath('user.alert_style', 'voice');
    }

    public function test_a_new_member_defaults_to_sound(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.alert_style', 'sound');
    }

    public function test_an_unknown_style_is_rejected(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->patchJson('/api/v1/auth/alert-style', ['alert_style' => 'airhorn'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alert_style']);
    }

    public function test_each_registered_device_is_handed_to_its_platform_transport(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->device($member, 'android', 'android-token');
        $this->device($member, 'ios', 'ios-token');

        $android = new RecordingTransport('fcm');
        $ios = new RecordingTransport('apns');

        $result = (new DevicePushSender(['android' => $android, 'ios' => $ios]))
            ->send($member, new PushMessage('Title', 'Body', AlertStyle::Voice));

        $this->assertSame('fcm', $result['provider']);
        $this->assertCount(1, $android->sent);
        $this->assertCount(1, $ios->sent);
        $this->assertSame('ios-token', $ios->sent[0]['token']);
        $this->assertSame(AlertStyle::Voice, $ios->sent[0]['message']->style);
    }

    public function test_a_failing_transport_does_not_stop_the_other(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->device($member, 'android', 'android-token');
        $this->device($member, 'ios', 'ios-token');

        $android = new RecordingTransport('fcm', fails: true);
        $ios = new RecordingTransport('apns');

        $result = (new DevicePushSender(['android' => $android, 'ios' => $ios]))
            ->send($member, new PushMessage('Title', 'Body'));

        // One delivery succeeded, so the send succeeded.
        $this->assertSame('apns', $result['provider']);
    }

    public function test_a_real_transport_requires_a_registered_device(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No registered device');

        (new DevicePushSender(['android' => new RecordingTransport('fcm')]))
            ->send($member, new PushMessage('Title', 'Body'));
    }

    public function test_log_transports_do_not_require_a_device(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        // Development: there is nowhere to deliver, and that is not an error.
        $result = (new DevicePushSender(
            ['android' => new RecordingTransport('log'), 'ios' => new RecordingTransport('log')],
            requiresDevice: false,
        ))->send($member, new PushMessage('Title', 'Body'));

        $this->assertSame('log', $result['provider']);
    }

    public function test_fcm_shapes_the_payload_from_the_alert_style(): void
    {
        $token = Mockery::mock(GoogleServiceAccountToken::class);
        $token->shouldReceive('accessToken')->once()->andReturn('access-token');

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo/messages/1'], 200),
        ]);

        $device = new DeviceToken(['platform' => 'android', 'token' => 'device-abc']);

        $messageId = (new FcmPushTransport('demo', $token))->send(
            $device,
            new PushMessage('Whole Foods', 'You are at Whole Foods: 2 items.', AlertStyle::Voice, ['place_id' => '7'], 'place:7'),
        );

        $this->assertSame('projects/demo/messages/1', $messageId);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/demo/messages:send'
            && $request['message']['token'] === 'device-abc'
            && $request['message']['notification']['body'] === 'You are at Whole Foods: 2 items.'
            && $request['message']['android']['notification']['channel_id'] === 'arrivals_voice'
            && $request['message']['data']['route'] === 'place:7'
            && $request['message']['data']['place_id'] === '7');
    }

    public function test_a_rejected_fcm_message_raises_instead_of_pretending(): void
    {
        $token = Mockery::mock(GoogleServiceAccountToken::class);
        $token->shouldReceive('accessToken')->andReturn('access-token');

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['error' => 'UNREGISTERED'], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FCM rejected');

        (new FcmPushTransport('demo', $token))->send(
            new DeviceToken(['platform' => 'android', 'token' => 'gone']),
            new PushMessage('Title', 'Body'),
        );
    }

    private function device(User $user, string $platform, string $token): DeviceToken
    {
        return $this->withBypass(fn (): DeviceToken => DeviceToken::create([
            'tenant_id' => $user->active_tenant_id,
            'user_id' => $user->getKey(),
            'platform' => $platform,
            'token' => $token,
            'last_used_at' => now(),
        ]));
    }
}

/**
 * Records what a transport was asked to deliver.
 */
class RecordingTransport implements PushTransport
{
    /** @var array<int, array{token: string, message: PushMessage}> */
    public array $sent = [];

    public function __construct(
        private readonly string $name,
        private readonly bool $fails = false,
    ) {}

    public function provider(): string
    {
        return $this->name;
    }

    public function send(DeviceToken $token, PushMessage $message): ?string
    {
        if ($this->fails) {
            throw new RuntimeException("{$this->name} is down");
        }

        $this->sent[] = ['token' => (string) $token->token, 'message' => $message];

        return $this->name.'-message-id';
    }
}
