<?php

namespace App\Voice;

use App\Enums\VoiceCallStatus;

final readonly class VoiceCallResult
{
    public function __construct(
        public string $provider,
        public ?string $providerCallId,
        public VoiceCallStatus $status,
    ) {}
}
