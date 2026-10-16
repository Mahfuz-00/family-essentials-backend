<?php

namespace App\Services\Phone;

use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;

class PhoneVerificationService
{
    public function __construct(private readonly SmsSender $sms) {}

    public function send(User $user): void
    {
        $length = (int) config('sms.code_length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $user->phoneVerificationCodes()->delete();
        $user->phoneVerificationCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('sms.code_ttl_minutes')),
        ]);

        $this->sms->send(
            (string) $user->phone,
            "Your Family Essentials verification code is {$code}."
        );
    }

    public function verify(User $user, string $code): bool
    {
        $record = $user->phoneVerificationCodes()->latest('id')->first();

        if (! $record || $record->isExpired()) {
            return false;
        }

        if ($record->attempts >= (int) config('sms.max_attempts')) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        $record->delete();
        $user->forceFill(['phone_verified_at' => now()])->save();

        return true;
    }
}
