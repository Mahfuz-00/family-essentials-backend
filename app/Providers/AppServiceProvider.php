<?php

namespace App\Providers;

use App\Geo\GeofenceProvider;
use App\Geo\LogGeofenceProvider;
use App\Notifications\Channels\LogPushSender;
use App\Notifications\Channels\PushSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Tenancy\TenantContext;
use App\Voice\FakeVoiceCaller;
use App\Voice\LogVoiceCaller;
use App\Voice\VoiceCaller;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(SmsSender::class, function (): SmsSender {
            return match (config('sms.driver')) {
                default => new LogSmsSender,
            };
        });

        $this->app->bind(PushSender::class, function (): PushSender {
            return match (config('push.driver')) {
                default => new LogPushSender,
            };
        });

        $this->app->bind(GeofenceProvider::class, function (): GeofenceProvider {
            return match (config('geofence.driver')) {
                default => new LogGeofenceProvider,
            };
        });

        $this->app->singleton(VoiceCaller::class, function (): VoiceCaller {
            return match (config('voice.driver')) {
                'fake' => new FakeVoiceCaller,
                default => new LogVoiceCaller,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            $base = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
