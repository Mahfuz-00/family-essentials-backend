<?php

namespace App\Voice;

use App\Models\User;

/**
 * Layer 3 delivery. Phase 6 ships a log driver (dev) and a fake driver (test
 * mode); a real Talkify/Twilio driver implements this same contract.
 */
interface VoiceCaller
{
    public function place(User $recipient, string $message, string $idempotencyKey): VoiceCallResult;
}
