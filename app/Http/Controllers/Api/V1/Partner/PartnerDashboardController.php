<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasAnyRole(['partner_admin', 'partner_recruiter']),
            403,
            'You are not authorized to view the partner dashboard.'
        );

        $partner = Partner::findOrFail($user->partner_id);

        // Metrics
        $openJobs = Job::where('partner_id', $partner->id)->where('status', 'published')->count();
        $pendingReviewJobs = Job::where('partner_id', $partner->id)->where('status', 'pending_review')->count();
        $needsRevisionJobs = Job::where('partner_id', $partner->id)->where('status', 'rejected')->count();

        $totalApplicants = Application::where('partner_id', $partner->id)->count();
        $newApplicantsThisWeek = Application::where('partner_id', $partner->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $activeRecruiters = User::where('partner_id', $partner->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['partner_admin', 'partner_recruiter']))
            ->count();

        // Pipeline stages
        $stageCounts = Application::where('partner_id', $partner->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $pipelineStages = [
            'applied' => (int) ($stageCounts['applied'] ?? 0),
            'shortlisted' => (int) ($stageCounts['shortlisted'] ?? 0),
            'interview' => (int) ($stageCounts['interview'] ?? 0),
            'offered' => (int) ($stageCounts['offered'] ?? 0),
            'hired' => (int) ($stageCounts['hired'] ?? 0),
            'rejected' => (int) ($stageCounts['rejected'] ?? 0),
        ];

        // Recent applicants
        $recentApplications = Application::where('partner_id', $partner->id)
            ->with(['candidate', 'job'])
            ->latest()
            ->take(6)
            ->get();

        $recentApplicants = $recentApplications->map(function ($app) {
            return [
                'id' => $app->id,
                'candidate_name' => $app->candidate?->name ?? 'Candidate',
                'candidate_email' => $app->candidate?->email ?? '',
                'job_title' => $app->job?->title ?? 'Untitled Job',
                'status' => $app->status,
                'applied_at' => $app->created_at?->toISOString(),
            ];
        })->values();

        // Alerts (e.g. revision required)
        $rejectedJobs = Job::where('partner_id', $partner->id)
            ->where('status', 'rejected')
            ->latest()
            ->take(3)
            ->get();

        $alerts = $rejectedJobs->map(function ($job) {
            return [
                'id' => $job->id,
                'type' => 'needs_revision',
                'title' => "Action Required: {$job->title} Needs Revision",
                'message' => $job->rejection_note
                    ? "Moderator feedback: {$job->rejection_note}"
                    : 'Job was rejected and requires revision before re-submission.',
                'link' => '/partner/jobs',
            ];
        })->values();

        return response()->json([
            'data' => [
                'company_name' => $partner->display_name ?: $partner->name,
                'metrics' => [
                    'open_jobs' => $openJobs,
                    'total_applicants' => $totalApplicants,
                    'new_applicants_this_week' => $newApplicantsThisWeek,
                    'pending_review_jobs' => $pendingReviewJobs,
                    'needs_revision_jobs' => $needsRevisionJobs,
                    'active_recruiters' => $activeRecruiters,
                ],
                'pipeline_stages' => $pipelineStages,
                'recent_applicants' => $recentApplicants,
                'alerts' => $alerts,
            ],
        ]);
    }
}

