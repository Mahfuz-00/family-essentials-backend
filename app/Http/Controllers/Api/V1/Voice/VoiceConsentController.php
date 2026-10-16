<?php

namespace App\Http\Controllers\Api\V1\Voice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Voice\UpdateVoiceConsentRequest;
use Illuminate\Http\JsonResponse;

class VoiceConsentController extends Controller
{
    public function update(UpdateVoiceConsentRequest $request): JsonResponse
    {
        $user = $request->user();
        $optIn = $request->boolean('voice_opt_in');

        if ($optIn && ($user->phone_verified_at === null || blank($user->phone))) {
            return response()->json([
                'message' => 'Verify your phone number before enabling voice calls.',
            ], 422);
        }

        $user->forceFill(['voice_opt_in' => $optIn])->save();

        return response()->json([
            'message' => $optIn ? 'Voice escalation enabled.' : 'Voice escalation disabled.',
            'voice_opt_in' => (bool) $user->voice_opt_in,
        ]);
    }
}
