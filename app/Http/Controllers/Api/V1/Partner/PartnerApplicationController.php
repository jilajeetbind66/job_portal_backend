<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartnerApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('applications.view'), 403);

        $filters = $request->validate([
            'job_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(['applied', 'shortlisted', 'interview', 'offered', 'hired', 'rejected', 'withdrawn'])],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Application::query()
            ->where('partner_id', $user->partner_id)
            ->with(['candidate', 'job.partner'])
            ->latest();

        if (! empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('candidate', fn ($candidate) => $candidate
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $applications = $query->paginate($filters['per_page'] ?? 15)->withQueryString();
        $counts = Application::query()
            ->where('partner_id', $user->partner_id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $response = ApplicationResource::collection($applications)->response()->getData(true);
        $response['meta']['counts'] = [
            'all' => array_sum($counts->all()),
            ...$counts->map(fn ($total) => (int) $total)->all(),
        ];

        return response()->json($response);
    }

    public function show(Request $request, Application $application): ApplicationResource
    {
        abort_unless(
            $request->user()->can('applications.view')
                && (int) $application->partner_id === (int) $request->user()->partner_id,
            404,
            'Application not found.'
        );

        return new ApplicationResource($application->load(['candidate', 'job.partner']));
    }

    public function updateStatus(
        Request $request,
        Application $application,
        AuditLogService $auditLogService
    ): ApplicationResource|JsonResponse {
        $user = $request->user();
        abort_unless(
            $user->can('applications.update-status')
                && (int) $application->partner_id === (int) $user->partner_id,
            404,
            'Application not found.'
        );

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['shortlisted', 'interview', 'offered', 'hired', 'rejected'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $transitions = [
            'applied' => ['shortlisted', 'rejected'],
            'shortlisted' => ['interview', 'rejected'],
            'interview' => ['offered', 'rejected'],
            'offered' => ['hired', 'rejected'],
            'rejected' => ['shortlisted'],
        ];

        $oldStatus = $application->status;
        if (! in_array($validated['status'], $transitions[$oldStatus] ?? [], true)) {
            return response()->json(['message' => 'This application status transition is not allowed.'], 422);
        }

        DB::transaction(function () use ($application, $validated, $auditLogService, $user, $oldStatus): void {
            $history = $application->status_history ?? [];
            $history[] = [
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
                'timestamp' => now()->toISOString(),
                'actor_id' => $user->id,
            ];

            $application->update([
                'status' => $validated['status'],
                'status_note' => $validated['note'] ?? null,
                'status_history' => $history,
            ]);

            $auditLogService->log(
                action: 'application.status_changed',
                subject: $application,
                meta: [
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                    'note' => $validated['note'] ?? null,
                ],
                partnerId: $application->partner_id,
            );
        });

        return new ApplicationResource($application->fresh()->load(['candidate', 'job.partner']));
    }
}