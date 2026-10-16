<?php

namespace App\Voice;

use App\Enums\VoiceCallStatus;
use App\Models\User;
use Throwable;

/**
 * Test-mode driver: no credentials, deterministic outcomes, and fully
 * inspectable so tests can assert on what was dialled.
 */
class FakeVoiceCaller implements VoiceCaller
{
    /** @var array<int, array{to: string|null, message: string, key: string}> */
    public array $calls = [];

    public VoiceCallStatus $outcome = VoiceCallStatus::Ringing;

    public ?Throwable $failure = null;

    public function place(User $recipient, string $message, string $idempotencyKey): VoiceCallResult
    {
        $this->calls[] = [
            'to' => $recipient->phone,
            'message' => $message,
            'key' => $idempotencyKey,
        ];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new VoiceCallResult('fake', 'fake-call-'.count($this->calls), $this->outcome);
    }
}
