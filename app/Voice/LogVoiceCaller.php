<?php

namespace App\Voice;

use App\Enums\VoiceCallStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogVoiceCaller implements VoiceCaller
{
    public function place(User $recipient, string $message, string $idempotencyKey): VoiceCallResult
    {
        $callId = 'log-call-'.Str::uuid()->toString();

        Log::info('Voice call dispatched', [
            'to' => $recipient->phone,
            'message' => $message,
            'idempotency_key' => $idempotencyKey,
            'provider_call_id' => $callId,
        ]);

        return new VoiceCallResult('log', $callId, VoiceCallStatus::Ringing);
    }
}
