
<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\JobModerationController;
use App\Http\Controllers\Api\V1\Admin\PartnerController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Partner\PartnerJobController;
use App\Http\Controllers\Api\V1\Partner\PartnerApplicationController;
use App\Http\Controllers\Api\V1\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\V1\Partner\PartnerCompanyController;
use App\Http\Controllers\Api\V1\Partner\PartnerRecruiterController;
use App\Http\Controllers\Api\V1\Public\PublicJobController;
use App\Http\Controllers\Api\V1\Candidate\CandidateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('public')->group(function () {
        Route::get('/jobs', [PublicJobController::class, 'index'])
            ->name('public.jobs.index');
        Route::get('/jobs/{slug}', [PublicJobController::class, 'show'])
            ->name('public.jobs.show');
        Route::get('/companies/{slug}', [PublicJobController::class, 'company'])
            ->name('public.companies.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */

    // Stateful SPA requests receive the session and CSRF middleware from Sanctum.
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('auth.register');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post(
        '/auth/forgot-password',
        [PasswordResetController::class, 'forgotPassword']
    )
        ->middleware('throttle:5,1')
        ->name('auth.password.email');

    Route::post(
        '/auth/reset-password',
        [PasswordResetController::class, 'resetPassword']
    )
        ->middleware('throttle:5,1')
        ->name('auth.password.reset');

    Route::get(
        '/auth/email/verify/{id}/{hash}',
        [EmailVerificationController::class, 'verify']
    )
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware([
        'auth:sanctum',
        'user.active',
        'partner.active',
    ])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('auth.me');

        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        Route::post(
            '/auth/email/verification-notification',
            [EmailVerificationController::class, 'resend']
        )
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    Route::middleware(['auth:sanctum', 'user.active', 'partner.active', 'role:candidate'])
        ->prefix('candidate')
        ->group(function () {
            Route::get('/profile', [CandidateController::class, 'profile'])->name('candidate.profile.show');
            Route::put('/profile', [CandidateController::class, 'updateProfile'])->name('candidate.profile.update');
            Route::post('/profile/resume', [CandidateController::class, 'uploadResume'])
                ->middleware('throttle:10,1')->name('candidate.profile.resume.upload');
            Route::get('/profile/resume/{user}', [CandidateController::class, 'downloadResume'])
                ->middleware('signed')->name('candidate.resume.download');
            Route::get('/dashboard', [CandidateController::class, 'dashboard'])->name('candidate.dashboard');
            Route::get('/saved-jobs', [CandidateController::class, 'savedJobs'])->name('candidate.saved-jobs.index');
            Route::post('/saved-jobs', [CandidateController::class, 'saveJob'])->name('candidate.saved-jobs.store');
            Route::delete('/saved-jobs/{job}', [CandidateController::class, 'unsaveJob'])->name('candidate.saved-jobs.destroy');
            Route::post('/jobs/{job}/apply', [CandidateController::class, 'apply'])->middleware('throttle:10,1')->name('candidate.jobs.apply');
            Route::get('/applications', [CandidateController::class, 'applications'])->name('candidate.applications.index');
            Route::patch('/applications/{application}/withdraw', [CandidateController::class, 'withdraw'])->name('candidate.applications.withdraw');
        });

    /*
    |--------------------------------------------------------------------------
    | Partner Job Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'user.active',
        'partner.active',
        'role:partner_admin|partner_recruiter',
    ])->prefix('partner')->group(function () {

        // Partner Dashboard
        Route::get('/dashboard', [PartnerDashboardController::class, 'index'])
            ->name('partner.dashboard');

        // Partner Company Profile
        Route::get('/company', [PartnerCompanyController::class, 'show'])
            ->name('partner.company.show');
        Route::put('/company', [PartnerCompanyController::class, 'update'])
            ->name('partner.company.update');
        Route::post('/company/logo', [PartnerCompanyController::class, 'uploadLogo'])
            ->name('partner.company.logo');

        // Partner Recruiter Management
        Route::get('/recruiters', [PartnerRecruiterController::class, 'index'])
            ->name('partner.recruiters.index');
        Route::post('/recruiters/invite', [PartnerRecruiterController::class, 'invite'])
            ->name('partner.recruiters.invite');
        Route::patch('/recruiters/{id}/role', [PartnerRecruiterController::class, 'updateRole'])
            ->name('partner.recruiters.role');
        Route::patch('/recruiters/{id}/status', [PartnerRecruiterController::class, 'toggleStatus'])
            ->name('partner.recruiters.status');
        Route::delete('/recruiters/{id}', [PartnerRecruiterController::class, 'destroy'])
            ->name('partner.recruiters.destroy');

        // List the authenticated partner's jobs
        Route::get('/jobs', [PartnerJobController::class, 'index'])
            ->name('partner.jobs.index');

        Route::get('/jobs/{job}', [PartnerJobController::class, 'show'])
            ->name('partner.jobs.show');

        // Create a new draft job
        Route::post('/jobs', [PartnerJobController::class, 'store'])
            ->name('partner.jobs.store');

        // Replace/update a job
        Route::put('/jobs/{job}', [PartnerJobController::class, 'update'])
            ->name('partner.jobs.update');

        // Partially update a job
        Route::patch('/jobs/{job}', [PartnerJobController::class, 'update'])
            ->name('partner.jobs.patch');

        // Change status of a job
        Route::patch('/jobs/{job}/status', [PartnerJobController::class, 'updateStatus'])
            ->name('partner.jobs.status');

        Route::patch('/jobs/{job}/close', [PartnerJobController::class, 'close'])
            ->name('partner.jobs.close');

        // Delete a partner's own job
        Route::delete('/jobs/{job}', [PartnerJobController::class, 'destroy'])
            ->name('partner.jobs.destroy');

        // Submit a draft job for Super Admin review
        Route::post(
            '/jobs/{job}/submit-review',
            [PartnerJobController::class, 'submitForReview']
        )->name('partner.jobs.submit-review');

        Route::get('/applications', [PartnerApplicationController::class, 'index'])->name('partner.applications.index');
        Route::get('/applications/{application}', [PartnerApplicationController::class, 'show'])->name('partner.applications.show');
        Route::patch('/applications/{application}/status', [PartnerApplicationController::class, 'updateStatus'])->name('partner.applications.status');
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
            ->middleware('role:super_admin')
            ->name('admin.partners.index');

        Route::post('/partners', [PartnerController::class, 'store'])
            ->middleware('role:super_admin')
            ->name('admin.partners.store');

        Route::get('/partners/{partner}', [PartnerController::class, 'show'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.partners.show');

        Route::put('/partners/{partner}', [PartnerController::class, 'update'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.partners.update');

        Route::post('/partners/{partner}/admin-user', [PartnerController::class, 'createAdminUser'])
            ->middleware('role:super_admin')
            ->name('admin.partners.admin-user');

        Route::post('/partners/{partner}/admins', [PartnerController::class, 'createAdminUser'])
            ->middleware('role:super_admin')
            ->name('admin.partners.admins');

        Route::patch('/partners/{partner}/suspend', [PartnerController::class, 'suspend'])
            ->middleware('role:super_admin')
            ->name('admin.partners.suspend');

        Route::patch('/partners/{partner}/reactivate', [PartnerController::class, 'reactivate'])
            ->middleware('role:super_admin')
            ->name('admin.partners.reactivate');

        Route::patch(
            '/partners/{partner}/status',
            [PartnerController::class, 'changeStatus']
        )->middleware('role:super_admin')->name('admin.partners.status');

        // Job Moderation & Aliases
        Route::get('/jobs', [JobModerationController::class, 'index'])
            ->middleware('role:super_admin')
            ->name('admin.jobs.index');

        Route::get('/jobs/{job}', [JobModerationController::class, 'show'])
            ->middleware('role:super_admin')
            ->name('admin.jobs.show');

        Route::patch('/jobs/{job}/approve', [JobModerationController::class, 'approve'])
            ->middleware('role:super_admin')
            ->name('admin.jobs.approve');

        Route::patch('/jobs/{job}/reject', [JobModerationController::class, 'reject'])
            ->middleware('role:super_admin')
            ->name('admin.jobs.reject');

        Route::patch(
            '/jobs/{job}/moderate',
            [JobModerationController::class, 'moderate']
        )->middleware('role:super_admin')->name('admin.jobs.moderate');

        Route::get('/moderation/jobs', [JobModerationController::class, 'index'])
            ->middleware('role:super_admin')
            ->name('admin.moderation.jobs.index');

        Route::get('/moderation/jobs/{job}', [JobModerationController::class, 'show'])
            ->middleware('role:super_admin')
            ->name('admin.moderation.jobs.show');

        Route::match(['post', 'patch'], '/moderation/jobs/{job}/approve', [JobModerationController::class, 'approve'])
            ->middleware('role:super_admin')
            ->name('admin.moderation.jobs.approve.post');

        Route::match(['post', 'patch'], '/moderation/jobs/{job}/reject', [JobModerationController::class, 'reject'])
            ->middleware('role:super_admin')
            ->name('admin.moderation.jobs.reject.post');

        // User Management
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.store');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.show');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.destroy');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.update');

        Route::put('/users/{user}/roles', [UserController::class, 'updateRole'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.roles');

        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.roles.patch');

        Route::post('/users/{user}/force-password-reset', [UserController::class, 'forcePasswordReset'])
            ->middleware('role:super_admin')
            ->name('admin.users.force-password-reset');

        Route::patch('/users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.suspend');

        Route::patch('/users/{user}/reactivate', [UserController::class, 'reactivate'])
            ->middleware('role:super_admin|partner_admin')
            ->name('admin.users.reactivate');

        Route::patch(
            '/users/{user}/status',
            [UserController::class, 'changeStatus']
        )->middleware('role:super_admin|partner_admin')->name('admin.users.status');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->middleware('role:super_admin')
            ->name('admin.analytics.index');

        Route::get('/analytics/stats', [AnalyticsController::class, 'stats'])
            ->middleware('role:super_admin')
            ->name('admin.analytics.stats');

        Route::get('/analytics/activity', [AnalyticsController::class, 'activity'])
            ->middleware('role:super_admin')
            ->name('admin.analytics.activity');

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('role:super_admin')
            ->name('admin.audit-logs.index');
    });
});
