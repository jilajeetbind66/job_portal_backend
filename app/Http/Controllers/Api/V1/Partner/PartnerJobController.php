<?php
namespace App\Http\Controllers\Api\V1\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StoreJobRequest;
use App\Http\Requests\Partner\UpdateJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PartnerJobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasAnyRole(['partner_admin', 'partner_recruiter'])
                && $user->can('jobs.view-own'),
            403,
            'You are not allowed to view partner jobs.'
        );

        $validated = $request->validate([
            'status' => [
                'sometimes',
                'string',
                'in:draft,pending_review,published,closed,rejected',
            ],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Job::query()
            ->where('job_postings.partner_id', $user->partner_id)
            ->with(['partner', 'postedBy'])
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $jobs = $query
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return JobResource::collection($jobs);
    }

    public function store(
        StoreJobRequest $request,
        AuditLogService $auditLogService
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        $job = Job::create([
            'partner_id' => $user->partner_id,
            'posted_by' => $user->id,
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'employment_type' => $validated['employment_type'],
            'work_mode' => $validated['work_mode'],
            'location' => $validated['location'] ?? null,
            'min_exp' => $validated['min_exp'] ?? 0,
            'max_exp' => $validated['max_exp'] ?? null,
            'salary_min' => $validated['salary_min'] ?? null,
            'salary_max' => $validated['salary_max'] ?? null,
            'openings' => $validated['openings'],
            'expires_at' => $validated['expires_at'] ?? null,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $auditLogService->log(
            action: 'job.created',
            subject: $job,
            meta: ['status' => 'draft'],
            partnerId: $user->partner_id
        );

        $job->load(['partner', 'postedBy']);

        return (new JobResource($job))
            ->additional([
                'message' => 'Job created successfully as a draft.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateJobRequest $request,
        Job $job,
        AuditLogService $auditLogService
    ): JsonResponse {
        $validated = $request->validated();

        // Only fields defined in UpdateJobRequest can be changed.
        $job->fill($validated);
        $job->save();

        $auditLogService->log(
            action: 'job.updated',
            subject: $job,
            meta: [
                'updated_fields' => array_keys($validated),
            ],
            partnerId: $job->partner_id
        );

        $job->load(['partner', 'postedBy']);

        return (new JobResource($job))
            ->additional([
                'message' => 'Job updated successfully.',
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(
        Request $request,
        Job $job,
        AuditLogService $auditLogService
    ): JsonResponse {
        $user = $request->user();

        // Verify role, permission, and tenant ownership.
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && (int) $user->partner_id === (int) $job->partner_id
                && $user->hasAnyRole(['partner_admin', 'partner_recruiter'])
                && $user->can('jobs.delete'),
            403,
            'You are not allowed to delete this job.'
        );

        DB::transaction(function () use (
            $job,
            $user,
            $auditLogService
        ): void {
            // Save the audit record before deleting the job.
            $auditLogService->log(
                action: 'job.deleted',
                subject: $job,
                meta: [
                    'title' => $job->title,
                    'slug' => $job->slug,
                    'status' => $job->status,
                ],
                partnerId: $user->partner_id
            );

            $job->delete();
        });

        return response()->json([
            'message' => 'Job deleted successfully.',
        ], 200);
    }


    
public function submitForReview(
    Request $request,
    Job $job,
    AuditLogService $auditLogService
): JsonResponse {
    $user = $request->user();

    abort_unless(
        $user !== null
            && $user->partner_id !== null
            && (int) $user->partner_id === (int) $job->partner_id
            && $user->hasAnyRole(['partner_admin', 'partner_recruiter'])
            && $user->can('jobs.submit-review'),
        403,
        'You are not allowed to submit this job for review.'
    );

    return DB::transaction(function () use (
        $job,
        $user,
        $auditLogService
    ): JsonResponse {
        $lockedJob = Job::query()
            ->whereKey($job->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        
        if (! in_array($lockedJob->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'message' => 'Only draft or rejected jobs can be submitted for review.',
            ], 422);
        }


        $oldStatus = $lockedJob->status;

        $lockedJob->status = 'pending_review';
        $lockedJob->rejection_note = null;
        $lockedJob->published_at = null;
        $lockedJob->save();

        $auditLogService->log(
            action: 'job.submitted_for_review',
            subject: $lockedJob,
            meta: [
                'old_status' => $oldStatus,
                'new_status' => 'pending_review',
            ],
            partnerId: $user->partner_id
        );

        $lockedJob->load(['partner', 'postedBy']);

        return (new JobResource($lockedJob))
            ->additional([
                'message' => 'Job submitted for review successfully.',
            ])
            ->response()
            ->setStatusCode(200);
    });
}

}
