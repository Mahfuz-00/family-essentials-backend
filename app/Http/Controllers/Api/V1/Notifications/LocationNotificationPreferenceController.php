<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Arrival notifications are opt-in, per member.
 *
 * Mirrors the voice-consent endpoint deliberately: one preference, one route,
 * both client surfaces. Nothing is notified to somebody who has not asked for it.
 */
class LocationNotificationPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opt_in' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill(['location_notifications_opt_in' => $validated['opt_in']])->save();

        return response()->json([
            'message' => $validated['opt_in']
                ? 'You will be told when you arrive somewhere with work waiting.'
                : 'Arrival notifications are off.',
            'user' => new UserResource($user->refresh()),
        ]);
    }
}
