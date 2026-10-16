<?php

namespace App\Http\Controllers\Api\V1\Voice;

use App\Enums\VoiceCallStatus;
use App\Http\Controllers\Controller;
use App\Models\VoiceCallLog;
use App\Models\VoiceWebhookEvent;
use App\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider callback. A provider has no tenant context, so this runs outside the
 * `auth:sanctum` + `tenant` stack: the signature is verified against the shared
 * secret, the event is recorded once (idempotency), and the status is applied
 * under a tenant-scoped context.
 */
class VoiceWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Voice-Signature', '');
        $expected = hash_hmac('sha256', $payload, (string) config('voice.webhook_secret'));

        abort_unless($signature !== '' && hash_equals($expected, $signature), 403, 'Invalid signature.');

        $data = json_decode($payload, true);

        abort_unless(is_array($data), 422, 'Invalid payload.');

        $eventId = (string) ($data['event_id'] ?? '');
        $callId = (string) ($data['provider_call_id'] ?? '');
        $status = VoiceCallStatus::tryFrom((string) ($data['status'] ?? ''));

        abort_if($eventId === '' || $callId === '' || $status === null, 422, 'Missing event_id, provider_call_id or status.');

        $call = Tenancy::bypass(fn () => VoiceCallLog::withoutGlobalScope('tenant')
            ->where('provider_call_id', $callId)
            ->first());

        if ($call === null) {
            return response()->json(['message' => 'Unknown call; ignored.', 'applied' => false], 202);
        }

        $applied = Tenancy::bypass(function () use ($call, $provider, $eventId, $data, $status): bool {
            $alreadyProcessed = VoiceWebhookEvent::withoutGlobalScope('tenant')
                ->where('provider_event_id', $eventId)
                ->exists();

            if ($alreadyProcessed) {
                return false;
            }

            VoiceWebhookEvent::create([
                'tenant_id' => $call->tenant_id,
                'provider' => $provider,
                'provider_event_id' => $eventId,
                'payload' => $data,
                'processed_at' => now(),
            ]);

            Tenancy::forTenant((int) $call->tenant_id, function () use ($call, $status, $data): void {
                $fresh = VoiceCallLog::query()->find($call->getKey());

                if ($fresh === null || $fresh->status->isTerminal()) {
                    return;
                }

                $attributes = ['status' => $status];

                if (isset($data['duration_seconds'])) {
                    $attributes['duration_seconds'] = (int) $data['duration_seconds'];
                }

                if ($status === VoiceCallStatus::Answered) {
                    $attributes['answered_at'] = now();
                }

                if (in_array($status, [VoiceCallStatus::Completed, VoiceCallStatus::NoAnswer, VoiceCallStatus::Busy], true)) {
                    $attributes['ended_at'] = now();
                }

                $fresh->forceFill($attributes)->save();
            });

            return true;
        });

        return response()->json([
            'message' => $applied ? 'Event applied.' : 'Duplicate event ignored.',
            'applied' => $applied,
        ]);
    }
}
