<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS dispatched', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
