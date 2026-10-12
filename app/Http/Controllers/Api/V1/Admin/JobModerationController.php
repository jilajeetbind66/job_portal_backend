<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateJobRequest;
use App\Http\Requests\Admin\RejectJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobModerationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Job::class);

        $query = Job::query()
            ->with(['partner', 'postedBy'])
            ->latest();

        if ($request->filled('status')) {
            $request->validate([
                'status' => [
                    'string',
                    'in:draft,pending_review,published,closed,rejected',
                ],
            ]);

            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $jobs = $query
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return JobResource::collection($jobs);
    }

    public function show(Job $job): JobResource
    {
        $this->authorize('view', $job);

        $job->load(['partner', 'postedBy']);

        return new JobResource($job);
    }

    public function approve(Job $job, AuditLogService $auditLogService): JobResource
    {
        $this->authorize('moderate', $job);

        abort_unless(
            $job->status === 'pending_review',
            422,
            'Only jobs pending review can be approved.'
        );

        return $this->saveModeration($job, 'published', null, $auditLogService);
    }

    public function reject(
        RejectJobRequest $request,
        Job $job,
        AuditLogService $auditLogService
    ): JobResource {
        $this->authorize('moderate', $job);

        abort_unless(
            $job->status === 'pending_review',
            422,
            'Only jobs pending review can be rejected.'
        );

        return $this->saveModeration(
            $job,
            'rejected',
            $request->validated('reason'),
            $auditLogService
        );
    }

    public function moderate(
        ModerateJobRequest $request,
        Job $job,
        AuditLogService $auditLogService
    ): JobResource {
        $this->authorize('moderate', $job);

        $validated = $request->validated();

        return $this->saveModeration(
            $job,
            $validated['status'],
            $validated['note'] ?? null,
            $auditLogService
        );
    }

    private function saveModeration(
        Job $job,
        string $newStatus,
        ?string $note,
        AuditLogService $auditLogService
    ): JobResource {
        $oldStatus = $job->status;
        $job->status = $newStatus;
        $job->rejection_note = $newStatus === 'rejected' ? $note : null;

        if ($newStatus === 'published') {
            $job->published_at = now();
        } elseif ($oldStatus === 'published') {
            $job->published_at = null;
        }

        $job->save();

        $auditLogService->log(
            action: 'job.moderated',
            subject: $job,
            meta: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $note,
            ],
            partnerId: $job->partner_id,
        );

        $job->load(['partner', 'postedBy']);

        return new JobResource($job);
    }

}
