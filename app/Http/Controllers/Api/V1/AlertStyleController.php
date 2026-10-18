<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AlertStyle;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * How this member wants to be alerted. Per member, because it is about their
 * phone and their tolerance for interruption.
 */
class AlertStyleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_style' => ['required', Rule::enum(AlertStyle::class)],
        ]);

        $user = $request->user();
        $user->forceFill(['alert_style' => $validated['alert_style']])->save();

        return response()->json([
            'message' => 'Alert style saved.',
            'user' => new UserResource($user->refresh()),
        ]);
    }
}
