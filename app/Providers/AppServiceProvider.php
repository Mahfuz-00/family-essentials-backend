<?php

namespace App\Providers;

use App\Geo\GeofenceProvider;
use App\Geo\LogGeofenceProvider;
use App\Notifications\Channels\ApnsPushTransport;
use App\Notifications\Channels\DevicePushSender;
use App\Notifications\Channels\FcmPushTransport;
use App\Notifications\Channels\LogPushSender;
use App\Notifications\Channels\PushSender;
use App\Notifications\Channels\PushTransport;
use App\Ops\Backups\BackupRunner;
use App\Ops\Backups\PostgresDumpRunner;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\GoogleServiceAccountToken;
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
            // One sender, one transport per token platform: Android through FCM,
            // iOS through APNs. Either can be `log`, so a clone without
            // credentials still runs end to end.
            $android = (string) config('push.android');
            $ios = (string) config('push.ios');

            return new DevicePushSender(
                [
                    'android' => $this->pushTransport($android),
                    'ios' => $this->pushTransport($ios),
                ],
                // While both are log drivers there is nothing to deliver to, so a
                // member without a registered device is not an error yet.
                requiresDevice: $android !== 'log' || $ios !== 'log',
            );
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

        // The dump tool self-reports whether it is usable; the service refuses to
        // record a backup when it is not, rather than writing a placeholder.
        $this->app->bind(BackupRunner::class, fn (): BackupRunner => new PostgresDumpRunner);
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

    private function pushTransport(string $driver): PushTransport
    {
        return match ($driver) {
            'fcm' => new FcmPushTransport(
                (string) config('push.fcm.project_id'),
                new GoogleServiceAccountToken((string) config('push.fcm.credentials')),
            ),
            'apns' => new ApnsPushTransport(
                (string) config('push.apns.key_path'),
                (string) config('push.apns.key_id'),
                (string) config('push.apns.team_id'),
                (string) config('push.apns.bundle_id'),
                (bool) config('push.apns.production'),
            ),
            default => new LogPushSender,
        };
    }
}
