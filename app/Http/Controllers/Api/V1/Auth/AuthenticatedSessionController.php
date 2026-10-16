<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $this->audit->record(
                'auth.login_failed',
                ['email' => (string) $request->string('email')],
                'warning',
                httpStatus: 422,
                actorEmail: (string) $request->string('email'),
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken(
            $request->input('device_name', 'web'),
            [self::abilityFor($request)],
        )->plainTextToken;

        $user->forceFill(['last_active_at' => now()])->save();

        $this->audit->record('auth.login', [
            'device' => $request->input('device_name', 'web'),
            'client' => self::abilityFor($request),
            'platform_admin' => (bool) $user->is_platform_admin,
        ], actor: $user);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * The token's ability is derived from the surface it was minted on, so a
     * dashboard token cannot be replayed against /mobile and vice versa.
     */
    public static function abilityFor(Request $request): string
    {
        return $request->is('mobile/*') ? 'mobile' : 'dashboard';
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->audit->record('auth.logout');

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
