<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\InvitationAcceptController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TenantMembershipController;
use App\Http\Controllers\Api\V1\Feedback\FeedbackController;
use App\Http\Controllers\Api\V1\Notifications\DeviceTokenController;
use App\Http\Controllers\Api\V1\Notifications\NotificationLogController;
use App\Http\Controllers\Api\V1\Pantry\EssentialController;
use App\Http\Controllers\Api\V1\Places\GeofenceController;
use App\Http\Controllers\Api\V1\Places\PlaceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Requests\ItemController;
use App\Http\Controllers\Api\V1\Requests\RequestActionController;
use App\Http\Controllers\Api\V1\Requests\RequestBoardController;
use App\Http\Controllers\Api\V1\Requests\RequestController;
use App\Http\Controllers\Api\V1\Tenant\MemberController as TenantMemberController;
use App\Http\Controllers\Api\V1\Tenant\QuotaController as TenantQuotaController;
use App\Http\Controllers\Api\V1\Tenant\TenantController as CurrentTenantController;
use App\Http\Controllers\Api\V1\Voice\VoiceCallController;
use App\Http\Controllers\Api\V1\Voice\VoiceConsentController;
use App\Http\Controllers\Api\V1\Voice\VoiceWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — Family Essentials
|--------------------------------------------------------------------------
| Mounted at /mobile (see bootstrap/app.php) and consumed by the Flutter app
| in `Mobile app/family_essentials_mobile`.
|
| Every authenticated route requires the `mobile` token ability, so a token
| minted by the web dashboard login is rejected here (and vice versa).
|
| The web dashboard mirror of this surface lives in routes/api.php at /api.
| Platform-admin (landlord) endpoints are deliberately absent: they are not
| part of the mobile product.
*/

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1');

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('mobile.verification.verify');

        Route::middleware(['auth:sanctum', 'abilities:mobile'])->group(function () {
            Route::get('me', [ProfileController::class, 'show']);
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);

            Route::patch('voice-consent', [VoiceConsentController::class, 'update']);

            Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1');
            Route::post('phone/send-code', [PhoneVerificationController::class, 'send'])->middleware('throttle:6,1');
            Route::post('phone/verify', [PhoneVerificationController::class, 'verify'])->middleware('throttle:6,1');

            // Identity-level tenancy: read memberships before a tenant is chosen.
            Route::middleware('tenancy.bootstrap')->group(function () {
                Route::get('tenants', [TenantMembershipController::class, 'index']);
                Route::post('tenant/switch', [TenantMembershipController::class, 'switch']);
                Route::post('invitations/accept', [InvitationAcceptController::class, 'store']);
            });
        });
    });

    // ---- Household workspace -------------------------------------------
    Route::middleware(['auth:sanctum', 'abilities:mobile', 'tenant'])->group(function () {
        Route::get('tenant', [CurrentTenantController::class, 'show']);
        Route::patch('tenant', [CurrentTenantController::class, 'update']);

        Route::get('tenant/members', [TenantMemberController::class, 'index']);

        // Plan limits and live usage.
        Route::get('tenant/quotas', [TenantQuotaController::class, 'index']);

        // Feedback and bug reports from the app.
        Route::get('feedback', [FeedbackController::class, 'index']);
        Route::post('feedback', [FeedbackController::class, 'store']);

        // ---- Catalog & requests ----------------------------------------
        Route::get('items', [ItemController::class, 'index']);
        Route::post('items', [ItemController::class, 'store']);

        // Declared before the model-bound route so "board" is not swallowed.
        Route::get('requests/board', [RequestBoardController::class, 'index']);

        Route::get('requests', [RequestController::class, 'index']);
        Route::post('requests', [RequestController::class, 'store']);
        Route::get('requests/{serviceRequest}', [RequestController::class, 'show']);
        Route::patch('requests/{serviceRequest}', [RequestController::class, 'update']);

        Route::post('requests/{serviceRequest}/assign', [RequestActionController::class, 'assign']);
        Route::post('requests/{serviceRequest}/accept', [RequestActionController::class, 'accept']);
        Route::post('requests/{serviceRequest}/decline', [RequestActionController::class, 'decline']);
        Route::post('requests/{serviceRequest}/start', [RequestActionController::class, 'start']);
        Route::post('requests/{serviceRequest}/complete', [RequestActionController::class, 'complete']);
        Route::post('requests/{serviceRequest}/cancel', [RequestActionController::class, 'cancel']);
        Route::post('requests/{serviceRequest}/unavailable', [RequestActionController::class, 'unavailable']);
        Route::post('requests/{serviceRequest}/snooze', [RequestActionController::class, 'snooze']);

        // ---- Reminders & notifications ---------------------------------
        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);
        Route::get('notifications', [NotificationLogController::class, 'index']);

        // ---- Saved places & geofencing ---------------------------------
        Route::get('places', [PlaceController::class, 'index']);
        Route::post('places', [PlaceController::class, 'store']);
        Route::get('places/{place}/requests', [PlaceController::class, 'requests']);
        Route::patch('places/{place}', [PlaceController::class, 'update']);
        Route::delete('places/{place}', [PlaceController::class, 'destroy']);

        Route::post('geofences/evaluate', [GeofenceController::class, 'evaluate']);
        Route::get('geofence-events', [GeofenceController::class, 'events']);

        // ---- Pantry / recurring essentials ------------------------------
        Route::get('essentials', [EssentialController::class, 'index']);
        Route::post('essentials', [EssentialController::class, 'store']);
        Route::patch('essentials/{essential}', [EssentialController::class, 'update']);
        Route::delete('essentials/{essential}', [EssentialController::class, 'destroy']);
        Route::post('essentials/{essential}/restock', [EssentialController::class, 'restock']);

        // ---- Voice escalation -------------------------------------------
        Route::get('voice-calls/quota', [VoiceCallController::class, 'quota']);
        Route::get('voice-calls', [VoiceCallController::class, 'index']);
        Route::post('requests/{serviceRequest}/voice-escalation', [VoiceCallController::class, 'store']);
    });

    // ---- Provider webhooks (no tenant context; signature verified) ------
    Route::post('webhooks/voice/{provider}', [VoiceWebhookController::class, 'handle']);
});
