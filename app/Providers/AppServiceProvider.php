<?php
namespace App\Providers;
use App\Models\Partner;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Partner::observe(AuditLogObserver::class);
        User::observe(AuditLogObserver::class);

        // Password reset link opens in React frontend.
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return 'http://localhost:5173/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        // Email verification link opens in React frontend.
        VerifyEmail::createUrlUsing(function ($user): string {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            return 'http://localhost:5173/verify-email?' . http_build_query([
                'url' => $verificationUrl,
            ]);
        });
    }
}
