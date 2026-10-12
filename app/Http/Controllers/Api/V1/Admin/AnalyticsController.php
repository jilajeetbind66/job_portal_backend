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
}
