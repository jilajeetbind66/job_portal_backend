<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateJobRequest;
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

    public function moderate(
        ModerateJobRequest $request,
        Job $job,
        AuditLogService $auditLogService
    ): JobResource {
        $this->authorize('moderate', $job);

        $validated = $request->validated();

        $oldStatus = $job->status;
        $newStatus = $validated['status'];

        $job->status = $newStatus;

        if ($newStatus === 'published') {
            $job->published_at = now();
        }

        if (
            $newStatus !== 'published' &&
            $oldStatus === 'published'
        ) {
            $job->published_at = null;
        }

        $job->save();

        $auditLogService->log(
            action: 'job.moderated',
            subject: $job,
            meta: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            partnerId: $job->partner_id,
        );

        $job->load(['partner', 'postedBy']);

        return new JobResource($job);
    }
}
