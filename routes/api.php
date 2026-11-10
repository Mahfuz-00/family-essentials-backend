<?php

use App\Http\Controllers\Api\V1\AlertStyleController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\InvitationAcceptController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TenantMembershipController;
use App\Http\Controllers\Api\V1\Notifications\DeviceTokenController;
use App\Http\Controllers\Api\V1\Notifications\LocationNotificationPreferenceController;
use App\Http\Controllers\Api\V1\Notifications\NotificationLogController;
use App\Http\Controllers\Api\V1\Feedback\FeedbackController;
use App\Http\Controllers\Api\V1\Ledger\LedgerController;
use App\Http\Controllers\Api\V1\Ledger\LedgerEntryController;
use App\Http\Controllers\Api\V1\Ledger\LedgerMemberController;
use App\Http\Controllers\Api\V1\Ledger\LedgerSummaryController;
use App\Http\Controllers\Api\V1\Pantry\EssentialController;
use App\Http\Controllers\Api\V1\Places\GeofenceController;
use App\Http\Controllers\Api\V1\Places\PlaceController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Calendar\CalendarFeedController;
use App\Http\Controllers\Api\V1\Ledger\ReceiptScanController;
use App\Http\Controllers\Api\V1\Requests\NlpDispatcherController;
use App\Http\Controllers\Api\V1\Platform\AnalyticsController as PlatformAnalyticsController;
use App\Http\Controllers\Api\V1\Platform\AuditLogController as PlatformAuditLogController;
use App\Http\Controllers\Api\V1\Platform\BackupController as PlatformBackupController;
use App\Http\Controllers\Api\V1\Platform\BillingController as PlatformBillingController;
use App\Http\Controllers\Api\V1\Platform\FeedbackController as PlatformFeedbackController;
use App\Http\Controllers\Api\V1\Platform\HealthController as PlatformHealthController;
use App\Http\Controllers\Api\V1\Platform\IncidentController as PlatformIncidentController;
use App\Http\Controllers\Api\V1\Platform\MarketingController as PlatformMarketingController;
use App\Http\Controllers\Api\V1\Platform\MetricsController as PlatformMetricsController;
use App\Http\Controllers\Api\V1\Platform\RefundController as PlatformRefundController;
use App\Http\Controllers\Api\V1\Platform\SettingsController as PlatformSettingsController;
use App\Http\Controllers\Api\V1\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Api\V1\MarketingPublicController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Recurring\RecurringRuleController;
use App\Http\Controllers\Api\V1\Requests\ItemController;
use App\Http\Controllers\Api\V1\Requests\RequestActionController;
use App\Http\Controllers\Api\V1\Requests\RequestBoardController;
use App\Http\Controllers\Api\V1\Requests\RequestController;
use App\Http\Controllers\Api\V1\Tenant\InvitationController as TenantInvitationController;
use App\Http\Controllers\Api\V1\Tenant\MemberController as TenantMemberController;
use App\Http\Controllers\Api\V1\Tenant\RoleController as TenantRoleController;
use App\Http\Controllers\Api\V1\Tenant\QuotaController as TenantQuotaController;
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

        // Publicly viewable refund policy
        Route::get('refund-policy', [PlatformRefundController::class, 'publicPolicy']);

        Route::middleware(['auth:sanctum', 'abilities:dashboard'])->group(function () {
            Route::get('me', [ProfileController::class, 'show']);
            Route::patch('me', [ProfileController::class, 'update']);
            Route::post('me/password', [ProfileController::class, 'updatePassword']);
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);

            Route::patch('voice-consent', [VoiceConsentController::class, 'update']);

            // Arrival notifications, opt-in per member — mirrors voice consent.
            Route::patch('location-consent', [LocationNotificationPreferenceController::class, 'update']);
            Route::patch('alert-style', [AlertStyleController::class, 'update']);

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

    // Public marketing endpoints (leads & active announcement banners)
    Route::post('marketing/subscribe', [MarketingPublicController::class, 'subscribe'])->middleware('throttle:10,1');
    Route::get('marketing/announcements/active', [MarketingPublicController::class, 'activeAnnouncements']);

    // Public RFC 5545 iCalendar feed (Gap A)
    Route::get('calendar/feed/{token}', [CalendarFeedController::class, 'feed']);

    // ---- Platform Admin (landlord) -------------------------------------
    Route::middleware(['auth:sanctum', 'abilities:dashboard', 'platform.admin'])
        ->prefix('platform')
        ->group(function () {
            Route::get('metrics', [PlatformMetricsController::class, 'index']);
            Route::get('analytics', [PlatformAnalyticsController::class, 'index']);

            // ---- Operations: system health and the incident log -------------
            Route::get('health', [PlatformHealthController::class, 'index']);
            Route::post('health/failed-jobs/{id}/retry', [PlatformHealthController::class, 'retryFailedJob']);
            Route::post('health/failed-jobs/retry-all', [PlatformHealthController::class, 'retryAllFailedJobs']);
            Route::post('health/failed-jobs/flush', [PlatformHealthController::class, 'flushFailedJobs']);
            Route::post('health/cache/clear', [PlatformHealthController::class, 'clearCache']);
            Route::get('health/maintenance', [PlatformHealthController::class, 'maintenance']);
            Route::post('health/maintenance', [PlatformHealthController::class, 'toggleMaintenance']);

            Route::get('incidents', [PlatformIncidentController::class, 'index']);
            Route::post('incidents', [PlatformIncidentController::class, 'store']);
            Route::patch('incidents/{incident}', [PlatformIncidentController::class, 'update']);

            // ---- Platform Settings: System, Email & Payment Gateways -------
            Route::get('settings/system', [PlatformSettingsController::class, 'getSystemSettings']);
            Route::post('settings/system', [PlatformSettingsController::class, 'updateSystemSettings']);

            Route::get('settings/payment', [PlatformSettingsController::class, 'getPaymentGateway']);
            Route::post('settings/payment', [PlatformSettingsController::class, 'updatePaymentGateway']);
            Route::post('settings/payment/test', [PlatformSettingsController::class, 'testPaymentGateway']);

            Route::get('settings/email', [PlatformSettingsController::class, 'getEmailConfig']);
            Route::post('settings/email', [PlatformSettingsController::class, 'updateEmailConfig']);
            Route::post('settings/email/test', [PlatformSettingsController::class, 'testEmail']);

            // ---- Refund Policy & Requests -----------------------------------
            Route::get('refunds', [PlatformRefundController::class, 'index']);
            Route::post('refunds', [PlatformRefundController::class, 'store']);
            Route::post('refunds/policy', [PlatformRefundController::class, 'updatePolicy']);
            Route::post('refunds/eligibility', [PlatformRefundController::class, 'checkEligibility']);
            Route::post('refunds/{id}/approve', [PlatformRefundController::class, 'approve']);
            Route::post('refunds/{id}/reject', [PlatformRefundController::class, 'reject']);

            // ---- Billing, Invoices & Cash Settlement Queue ------------------
            Route::get('billing/invoices', [PlatformBillingController::class, 'index']);
            Route::get('billing/invoices/{invoice}/download', [PlatformBillingController::class, 'downloadInvoice']);
            Route::get('billing/pending-cash', [PlatformBillingController::class, 'pendingCash']);
            Route::post('billing/invoices/{invoice}/approve-cash', [PlatformBillingController::class, 'approveCash']);
            Route::post('billing/invoices/{invoice}/reject-cash', [PlatformBillingController::class, 'rejectCash']);
            Route::get('notifications', [PlatformBillingController::class, 'notifications']);

            // ---- Marketing & Growth Console ---------------------------------
            Route::get('marketing/overview', [PlatformMarketingController::class, 'overview']);

            Route::get('marketing/promotions', [PlatformMarketingController::class, 'promotions']);
            Route::post('marketing/promotions', [PlatformMarketingController::class, 'storePromotion']);
            Route::post('marketing/promotions/{promotion}/toggle', [PlatformMarketingController::class, 'togglePromotion']);
            Route::delete('marketing/promotions/{promotion}', [PlatformMarketingController::class, 'deletePromotion']);

            Route::get('marketing/announcements', [PlatformMarketingController::class, 'announcements']);
            Route::post('marketing/announcements', [PlatformMarketingController::class, 'storeAnnouncement']);
            Route::post('marketing/announcements/{announcement}/toggle', [PlatformMarketingController::class, 'toggleAnnouncement']);
            Route::delete('marketing/announcements/{announcement}', [PlatformMarketingController::class, 'deleteAnnouncement']);

            Route::get('marketing/leads/export', [PlatformMarketingController::class, 'exportLeads']);
            Route::get('marketing/leads', [PlatformMarketingController::class, 'leads']);
            Route::patch('marketing/leads/{lead}', [PlatformMarketingController::class, 'updateLead']);
            Route::delete('marketing/leads/{lead}', [PlatformMarketingController::class, 'deleteLead']);

            Route::get('marketing/referrals', [PlatformMarketingController::class, 'getReferralSettings']);
            Route::post('marketing/referrals', [PlatformMarketingController::class, 'updateReferralSettings']);

            // ---- Feedback inbox: what households are telling us -------------
            Route::get('feedback', [PlatformFeedbackController::class, 'index']);
            Route::patch('feedback/{feedback}', [PlatformFeedbackController::class, 'update']);

            // ---- Backups and disaster recovery ------------------------------
            Route::get('backups', [PlatformBackupController::class, 'index']);
            Route::post('backups', [PlatformBackupController::class, 'store']);
            Route::post('backups/{backup}/verify', [PlatformBackupController::class, 'verify']);
            Route::get('backups/{backup}/download', [PlatformBackupController::class, 'download']);
            Route::delete('backups/{backup}', [PlatformBackupController::class, 'destroy']);

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
        Route::get('tenant/export', [CurrentTenantController::class, 'export']);

        Route::get('tenant/members', [TenantMemberController::class, 'index']);
        Route::patch('tenant/members/{user}', [TenantMemberController::class, 'update']);
        Route::delete('tenant/members/{user}', [TenantMemberController::class, 'destroy']);

        // Roles & permissions: the capability matrix and role catalogue.
        Route::get('tenant/roles', [TenantRoleController::class, 'index']);

        // Plan limits and live usage — the caps the API actually enforces.
        Route::get('tenant/quotas', [TenantQuotaController::class, 'index']);

        // Feedback and bug reports: any household member may write here.
        Route::get('feedback', [FeedbackController::class, 'index']);
        Route::post('feedback', [FeedbackController::class, 'store']);

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
        Route::post('requests/{serviceRequest}/thank', [RequestActionController::class, 'thank']);

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
        Route::post('essentials/{essential}/create-errand', [EssentialController::class, 'createErrand']);

        // ---- Voice escalation -------------------------------------------
        Route::get('voice-calls/quota', [VoiceCallController::class, 'quota']);
        Route::get('voice-calls', [VoiceCallController::class, 'index']);
        Route::post('requests/{serviceRequest}/voice-escalation', [VoiceCallController::class, 'store']);

        // ---- Recurring rules --------------------------------------------
        // A rule is turned into a fresh request each cycle by
        // `essentials:spawn-recurring`; nothing here touches a request.
        Route::get('recurring-rules', [RecurringRuleController::class, 'index']);
        Route::post('recurring-rules', [RecurringRuleController::class, 'store']);
        Route::patch('recurring-rules/{recurringRule}', [RecurringRuleController::class, 'update']);
        Route::delete('recurring-rules/{recurringRule}', [RecurringRuleController::class, 'destroy']);

        // ---- Household ledger (money) ------------------------------------
        // Which ledgers a caller can reach is enforced by PostgreSQL (they must
        // have a membership row); how much of one they see is enforced by the
        // policy. A ledger they are not in is a 404, never a 403.
        Route::get('ledgers', [LedgerController::class, 'index']);
        Route::post('ledgers', [LedgerController::class, 'store']);

        Route::post('ledgers/{ledger}/members', [LedgerMemberController::class, 'store']);
        Route::delete('ledgers/{ledger}/members/{user}', [LedgerMemberController::class, 'destroy']);

        Route::get('ledgers/{ledger}/summary', [LedgerSummaryController::class, 'show']);
        Route::get('ledgers/{ledger}/entries', [LedgerEntryController::class, 'index']);
        Route::post('ledgers/{ledger}/entries', [LedgerEntryController::class, 'store']);
        Route::delete('ledgers/{ledger}/entries/{entry}', [LedgerEntryController::class, 'destroy']);

        // Optical Receipt Scanning & Expense Categorization (Gap B)
        Route::post('ledger/receipts/scan', [ReceiptScanController::class, 'scan']);

        // ---- Customer Self-Service Billing & Invoicing (Gap F) -----------
        Route::get('billing/payment-methods', [InvoiceController::class, 'paymentMethods']);
        Route::get('billing/invoices', [InvoiceController::class, 'index']);
        Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('billing/invoices/{invoice}/download', [InvoiceController::class, 'downloadHtml']);
        Route::post('billing/portal', [InvoiceController::class, 'createPortalSession']);
        Route::post('billing/change-plan-preview', [InvoiceController::class, 'changePlanPreview']);
        Route::post('billing/upgrade', [InvoiceController::class, 'simulateUpgrade']);
        Route::get('billing/refund-policy', [PlatformRefundController::class, 'publicPolicy']);
        Route::post('billing/refund-request', [PlatformRefundController::class, 'submitTenantRefundRequest']);

        // ---- External Calendar Sync (Gap A) ----------------------------
        Route::get('calendar/feed-url', [CalendarFeedController::class, 'getFeedUrl']);

        // ---- AI Family Dispatcher (Gap G) -------------------------------
        Route::post('requests/parse-nlp', [NlpDispatcherController::class, 'parse']);
    });

    // ---- Provider webhooks (no tenant context; signature verified) ------
    Route::post('webhooks/voice/{provider}', [VoiceWebhookController::class, 'handle']);
});
