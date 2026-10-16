<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyPhoneRequest;
use App\Http\Resources\UserResource;
use App\Services\Phone\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, PhoneVerificationService $service): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return response()->json(['message' => 'Phone already verified.'], 409);
        }

        if (blank($user->phone)) {
            return response()->json(['message' => 'No phone number on file.'], 422);
        }

        $service->send($user);

        return response()->json(['message' => 'Verification code sent.'], 202);
    }

    public function verify(VerifyPhoneRequest $request, PhoneVerificationService $service): JsonResponse
    {
        $user = $request->user();

        if (! $service->verify($user, (string) $request->string('code'))) {
            return response()->json(['message' => 'The verification code is invalid or expired.'], 422);
        }

        return response()->json([
            'message' => 'Phone verified.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
