<?php
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\JobModerationController;
use App\Http\Controllers\Api\V1\Admin\PartnerController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('web')->group(function () {

        // Registration
        Route::post('/auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('auth.register');

        // Login
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('auth.login');

        // Forgot Password
        Route::post(
            '/auth/forgot-password',
            [PasswordResetController::class, 'forgotPassword']
        )
            ->middleware('throttle:5,1')
            ->name('auth.password.email');

        // Reset Password
        Route::post(
            '/auth/reset-password',
            [PasswordResetController::class, 'resetPassword']
        )
            ->middleware('throttle:5,1')
            ->name('auth.password.reset');

        // Verify Email
        Route::get(
            '/auth/email/verify/{id}/{hash}',
            [EmailVerificationController::class, 'verify']
        )
            ->middleware('signed')
            ->name('verification.verify');

        // Authenticated Routes
        Route::middleware([
            'auth:sanctum',
            'user.active',
            'partner.active',
        ])->group(function () {

            // Current User
            Route::get('/auth/me', [AuthController::class, 'me'])
                ->name('auth.me');

            // Logout
            Route::post('/auth/logout', [AuthController::class, 'logout'])
                ->name('auth.logout');

            // Resend Verification Email
            Route::post(
                '/auth/email/verification-notification',
                [EmailVerificationController::class, 'resend']
            )
                ->middleware('throttle:6,1')
                ->name('verification.send');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'user.active',
        'partner.active',
    ])->prefix('admin')->group(function () {

        // Partner Management
        Route::get('/partners', [PartnerController::class, 'index'])
            ->name('admin.partners.index');

        Route::post('/partners', [PartnerController::class, 'store'])
            ->name('admin.partners.store');

        Route::get('/partners/{partner}', [PartnerController::class, 'show'])
            ->name('admin.partners.show');

        Route::put('/partners/{partner}', [PartnerController::class, 'update'])
            ->name('admin.partners.update');

        Route::patch('/partners/{partner}/status', [PartnerController::class, 'changeStatus'])
            ->name('admin.partners.status');

        // Job Moderation
        Route::get('/jobs', [JobModerationController::class, 'index'])
            ->name('admin.jobs.index');

        Route::get('/jobs/{job}', [JobModerationController::class, 'show'])
            ->name('admin.jobs.show');

        Route::patch('/jobs/{job}/moderate', [JobModerationController::class, 'moderate'])
            ->name('admin.jobs.moderate');

        // User Management
        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->name('admin.users.store');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('admin.users.show');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('admin.users.update');

        Route::patch('/users/{user}/status', [UserController::class, 'changeStatus'])
            ->name('admin.users.status');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->name('admin.analytics.index');

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('admin.audit-logs.index');
    });
});
