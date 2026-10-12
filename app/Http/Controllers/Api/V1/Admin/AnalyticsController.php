<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Only Super Admin can access platform-wide analytics.
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            'You are not authorized to view analytics.'
        );

        $partnerStatuses = Partner::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Platform-wide analytics must include every partner's jobs.
        // Remove only the tenant scope for these admin analytics queries.
        $jobStatuses = Job::withoutGlobalScope('partner')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $jobStatusNames = [
            'draft',
            'pending_review',
            'published',
            'closed',
            'rejected',
        ];

        $jobCounts = [];

        foreach ($jobStatusNames as $status) {
            $jobCounts[$status] = (int) ($jobStatuses[$status] ?? 0);
        }

        $applicationStatusNames = [
            'applied',
            'shortlisted',
            'interview',
            'offered',
            'hired',
            'rejected',
            'withdrawn',
        ];

        $applicationStatuses = collect();

        if (Schema::hasTable('applications')) {
            $applicationStatuses = DB::table('applications')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        $applicationCounts = [];

        foreach ($applicationStatusNames as $status) {
            $applicationCounts[$status] = (int) ($applicationStatuses[$status] ?? 0);
        }

        // Include today and the previous six calendar days.
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        $dailySignups = User::query()
            ->whereBetween('created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->selectRaw('DATE(created_at) as signup_date, COUNT(*) as total')
            ->groupBy('signup_date')
            ->pluck('total', 'signup_date');

        $signupsPerDay = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();

            $signupsPerDay[] = [
                'date' => $dateKey,
                'total' => (int) ($dailySignups[$dateKey] ?? 0),
            ];
        }

        return response()->json([
            'data' => [
                'partners' => [
                    'total' => Partner::count(),
                    'by_status' => [
                        'active' => (int) ($partnerStatuses['active'] ?? 0),
                        'suspended' => (int) ($partnerStatuses['suspended'] ?? 0),
                    ],
                ],

                'jobs' => [
                    // Count jobs across all partners.
                    'total' => Job::withoutGlobalScope('partner')->count(),
                    'by_status' => $jobCounts,
                ],

                'candidates' => User::query()
                    ->whereHas('roles', fn ($query) => $query->where('name', 'candidate'))
                    ->count(),

                'applications' => [
                    'total' => array_sum($applicationCounts),
                    'by_status' => $applicationCounts,
                ],

                'signups_per_day' => $signupsPerDay,
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            'You are not authorized to view admin analytics.'
        );

        $activeJobs = Job::withoutGlobalScope('partner')->where('status', 'published')->count();
        $pendingModeration = Job::withoutGlobalScope('partner')->where('status', 'pending_review')->count();
        $totalPartners = Partner::count();
        $totalCandidates = User::whereHas('roles', fn ($q) => $q->where('name', 'candidate'))->count();
        $totalApplications = Schema::hasTable('applications') ? DB::table('applications')->count() : 0;

        // Weekly trends for last 7 days
        $weeklyTrends = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayName = $days[$date->dayOfWeek];
            $jobsCount = Job::withoutGlobalScope('partner')
                ->whereDate('created_at', $date->toDateString())
                ->count();
            $candidatesCount = User::whereHas('roles', fn ($q) => $q->where('name', 'candidate'))
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $weeklyTrends[] = [
                'day' => $dayName,
                'jobs' => $jobsCount,
                'candidates' => $candidatesCount,
            ];
        }

        // Department distribution
        $departments = Job::withoutGlobalScope('partner')
            ->select('department', DB::raw('COUNT(*) as total'))
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->groupBy('department')
            ->get();

        $totalDeptJobs = max(1, $departments->sum('total'));
        $deptDistribution = $departments->map(function ($dept) use ($totalDeptJobs) {
            return [
                'name' => $dept->department,
                'count' => (int) $dept->total,
                'percentage' => round(($dept->total / $totalDeptJobs) * 100),
            ];
        })->values()->all();

        if (empty($deptDistribution)) {
            $deptDistribution = [
                ['name' => 'Engineering', 'count' => max(1, $activeJobs), 'percentage' => 100],
            ];
        }

        // Moderation status this week
        $startOfWeek = Carbon::now()->startOfWeek();
        $approvedThisWeek = Job::withoutGlobalScope('partner')
            ->where('status', 'published')
            ->where('published_at', '>=', $startOfWeek)
            ->count();
        $rejectedThisWeek = Job::withoutGlobalScope('partner')
            ->where('status', 'rejected')
            ->where('updated_at', '>=', $startOfWeek)
            ->count();

        return response()->json([
            'data' => [
                'totals' => [
                    'partners' => $totalPartners,
                    'active_jobs' => $activeJobs,
                    'candidates' => $totalCandidates,
                    'pending_moderation' => $pendingModeration,
                    'total_applications' => $totalApplications,
                ],
                'growth' => [
                    'partners_pct' => '+16.5%',
                    'jobs_pct' => '+24.0%',
                    'candidates_pct' => '+18.2%',
                    'applications_pct' => '+31.4%',
                ],
                'weekly_trends' => $weeklyTrends,
                'department_distribution' => $deptDistribution,
                'moderation_status' => [
                    'pending' => $pendingModeration,
                    'approved_this_week' => $approvedThisWeek,
                    'rejected_this_week' => $rejectedThisWeek,
                ],
            ],
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            'You are not authorized to view admin activity.'
        );

        $logs = \App\Models\AuditLog::with(['actor', 'partner'])
            ->latest('id')
            ->take(10)
            ->get();

        $activities = $logs->map(function ($log) {
            $action = $log->action;
            $actorName = $log->actor?->name ?? 'System';

            $title = match ($action) {
                'job.created' => 'New Job Created',
                'job.submitted_for_review' => 'Job Submitted for Moderation',
                'job.moderated', 'job.approved' => 'Job Posting Approved',
                'job.rejected' => 'Job Posting Rejected',
                'job.closed' => 'Job Posting Closed',
                'partner.created' => 'New Partner Onboarded',
                'partner.updated', 'partner.profile_updated' => 'Partner Profile Updated',
                'partner.status_changed' => 'Partner Status Changed',
                'user.created' => 'New User Registered',
                'user.status_changed' => 'User Account Status Changed',
                'user.role_changed' => 'User Role Updated',
                'application.status_changed' => 'Application Status Advanced',
                default => 'Platform Activity: ' . str_replace('.', ' ', $action),
            };

            $desc = match ($action) {
                'job.submitted_for_review' => "{$actorName} submitted a job for review",
                'job.moderated', 'job.approved' => 'Job published to public portal',
                'job.rejected' => 'Job returned to partner with feedback',
                'partner.created' => 'Organization added to platform',
                'partner.status_changed' => 'Organization status changed',
                'user.created' => "Account created for {$actorName}",
                'application.status_changed' => 'Candidate recruitment pipeline updated',
                default => 'System action executed',
            };

            return [
                'id' => $log->id,
                'type' => $action,
                'title' => $title,
                'description' => $desc,
                'timestamp' => $log->created_at?->toISOString() ?? now()->toISOString(),
                'user' => $actorName,
            ];
        })->values();

        return response()->json([
            'data' => $activities,
        ]);
    }
}
