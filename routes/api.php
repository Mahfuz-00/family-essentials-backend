<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\InvitationAcceptController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TenantMembershipController;
use App\Http\Controllers\Api\V1\Notifications\DeviceTokenController;
use App\Http\Controllers\Api\V1\Notifications\NotificationLogController;
use App\Http\Controllers\Api\V1\Pantry\EssentialController;
use App\Http\Controllers\Api\V1\Places\GeofenceController;
use App\Http\Controllers\Api\V1\Places\PlaceController;
use App\Http\Controllers\Api\V1\Platform\AuditLogController as PlatformAuditLogController;
use App\Http\Controllers\Api\V1\Platform\MetricsController as PlatformMetricsController;
use App\Http\Controllers\Api\V1\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Requests\ItemController;
use App\Http\Controllers\Api\V1\Requests\RequestActionController;
use App\Http\Controllers\Api\V1\Requests\RequestBoardController;
use App\Http\Controllers\Api\V1\Requests\RequestController;
use App\Http\Controllers\Api\V1\Tenant\InvitationController as TenantInvitationController;
use App\Http\Controllers\Api\V1\Tenant\MemberController as TenantMemberController;
use App\Http\Controllers\Api\V1\Tenant\TenantController as CurrentTenantController;
use App\Http\Controllers\Api\V1\Voice\VoiceCallController;
use App\Http\Controllers\Api\V1\Voice\VoiceConsentController;
use App\Http\Controllers\Api\V1\Voice\VoiceWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Family Essentials (web dashboard surface)
|--------------------------------------------------------------------------
| Served at /api and consumed by the React app in `Frontend/`.
| Phase 1: authentication foundation.
| Phase 2: multi-tenant core - tenant context, membership and isolation.
| Phase 3: request & assignment workflow.
| Phase 4-6: reminders, geofencing, voice escalation.
| Platform: cross-tenant landlord console (platform.admin only).
|
| Every authenticated route requires the `dashboard` token ability. The mobile
| app talks to the mirror surface at /mobile (routes/mobile.php) instead.
*/

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1');

        // Signed link proves ownership, so this endpoint is intentionally unauthenticated.
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware(['auth:sanctum', 'abilities:dashboard'])->group(function () {
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

    // ---- Platform Admin (landlord) -------------------------------------
    Route::middleware(['auth:sanctum', 'abilities:dashboard', 'platform.admin'])
        ->prefix('platform')
        ->group(function () {
            Route::get('metrics', [PlatformMetricsController::class, 'index']);

            // Declared before the model-bound route so "export" is not swallowed.
            Route::get('audit-logs/export', [PlatformAuditLogController::class, 'export']);
            Route::get('audit-logs', [PlatformAuditLogController::class, 'index']);
            Route::get('audit-logs/{auditEvent}', [PlatformAuditLogController::class, 'show']);

            Route::get('tenants/export', [PlatformTenantController::class, 'export']);
            Route::post('tenants/bulk/suspend', [PlatformTenantController::class, 'bulkSuspend']);
            Route::post('tenants/bulk/activate', [PlatformTenantController::class, 'bulkActivate']);

            Route::get('tenants', [PlatformTenantController::class, 'index']);
            Route::post('tenants', [PlatformTenantController::class, 'store']);
            Route::get('tenants/{tenant}', [PlatformTenantController::class, 'show']);
            Route::patch('tenants/{tenant}', [PlatformTenantController::class, 'update']);
            Route::post('tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend']);
            Route::post('tenants/{tenant}/activate', [PlatformTenantController::class, 'activate']);
        });

    // ---- Tenant workspace (household members) --------------------------
    Route::middleware(['auth:sanctum', 'abilities:dashboard', 'tenant'])->group(function () {
        Route::get('tenant', [CurrentTenantController::class, 'show']);
        Route::patch('tenant', [CurrentTenantController::class, 'update']);

        Route::get('tenant/members', [TenantMemberController::class, 'index']);
        Route::patch('tenant/members/{user}', [TenantMemberController::class, 'update']);
        Route::delete('tenant/members/{user}', [TenantMemberController::class, 'destroy']);

        Route::get('tenant/invitations', [TenantInvitationController::class, 'index']);
        Route::post('tenant/invitations', [TenantInvitationController::class, 'store']);
        Route::delete('tenant/invitations/{invitation}', [TenantInvitationController::class, 'destroy']);

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
