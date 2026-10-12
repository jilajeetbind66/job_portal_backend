<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\PublicJobResource;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\SavedJob;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class CandidateController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = CandidateProfile::query()->firstOrCreate(['user_id' => $user->id]);

        return response()->json(['data' => $this->profileData($user, $profile)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'skills' => ['sometimes', 'array', 'max:100'],
            'skills.*' => ['string', 'max:100'],
            'experience' => ['sometimes', 'array', 'max:50'],
            'experience.*.company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience.*.from' => ['sometimes', 'nullable', 'string', 'max:30'],
            'experience.*.to' => ['sometimes', 'nullable', 'string', 'max:30'],
            'experience.*.current' => ['sometimes', 'boolean'],
            'experience.*.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'education' => ['sometimes', 'array', 'max:50'],
            'education.*.school' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education.*.degree' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education.*.field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education.*.fromYear' => ['sometimes', 'nullable', 'string', 'max:10'],
            'education.*.toYear' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $user->update(array_intersect_key($validated, array_flip(['name', 'phone'])));
        $profile = CandidateProfile::query()->firstOrCreate(['user_id' => $user->id]);
        $profile->fill(array_intersect_key($validated, array_flip([
            'headline', 'location', 'bio', 'skills', 'experience', 'education',
        ])))->save();

        return response()->json(['data' => $this->profileData($user->fresh(), $profile->fresh())]);
    }

    public function uploadResume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $user = $request->user();
        $profile = CandidateProfile::query()->firstOrCreate(['user_id' => $user->id]);
        if ($profile->resume_path) {
            Storage::disk('local')->delete($profile->resume_path);
        }

        $profile->update([
            'resume_path' => $validated['resume']->store('candidate-resumes', 'local'),
        ]);

        return response()->json([
            'message' => 'Resume uploaded successfully.',
            'resume_url' => $this->resumeUrl($user->id),
            'parsed_data' => null,
        ], 201);
    }

    public function downloadResume(Request $request, int $user): mixed
    {
        $profile = CandidateProfile::query()->where('user_id', $user)->firstOrFail();
        abort_unless($profile->resume_path && Storage::disk('local')->exists($profile->resume_path), 404);

        return Storage::disk('local')->download($profile->resume_path);
    }

    public function saveJob(Request $request): JsonResponse
    {
        $validated = $request->validate(['job_id' => ['required', 'integer', 'min:1']]);
        $jobRecord = $this->findVisibleJob($validated['job_id']);
        SavedJob::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'job_id' => $jobRecord->id,
        ]);

        return response()->json(['message' => 'Job saved successfully.'], 201);
    }

    public function unsaveJob(Request $request, int $job): JsonResponse
    {
        SavedJob::query()
            ->where('user_id', $request->user()->id)
            ->where('job_id', $job)
            ->delete();

        return response()->json(['message' => 'Saved job removed.']);
    }

    public function savedJobs(Request $request): AnonymousResourceCollection
    {
        $savedIds = SavedJob::query()->where('user_id', $request->user()->id)->select('job_id');
        $jobs = Job::query()
            ->whereIn('id', $savedIds)
            ->where('status', 'published')
            ->whereHas('partner', fn ($query) => $query->where('status', 'active'))
            ->with('partner')
            ->latest('published_at')
            ->paginate($request->integer('per_page', 15));

        return PublicJobResource::collection($jobs);
    }

    public function apply(Request $request, int $job): JsonResponse
    {
        $jobRecord = $this->findVisibleJob($job);
        $validated = $request->validate([
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'coverLetter' => ['nullable', 'string', 'max:5000'],
        ]);

        $userId = $request->user()->id;
        if (Application::query()->where('user_id', $userId)->where('job_id', $jobRecord->id)->exists()) {
            return response()->json(['message' => 'You have already applied to this job.'], 409);
        }

        $application = Application::create([
            'user_id' => $userId,
            'job_id' => $jobRecord->id,
            'partner_id' => $jobRecord->partner_id,
            'cover_letter' => $validated['cover_letter'] ?? $validated['coverLetter'] ?? null,
            'status' => 'applied',
            'status_history' => [[
                'status' => 'applied',
                'note' => null,
                'timestamp' => now()->toISOString(),
            ]],
        ]);

        return (new ApplicationResource($application->load(['job.partner', 'candidate'])))
            ->additional(['message' => 'Application submitted successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function applications(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['applied', 'shortlisted', 'interview', 'offered', 'hired', 'rejected', 'withdrawn'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Application::query()
            ->where('user_id', $request->user()->id)
            ->with(['job.partner', 'candidate'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return ApplicationResource::collection($query->paginate($filters['per_page'] ?? 15));
    }

    public function withdraw(Request $request, Application $application): JsonResponse
    {
        abort_unless($application->user_id === $request->user()->id, 404);
        abort_unless($application->status === 'applied', 422, 'Only new applications can be withdrawn.');

        $this->recordStatus($application, 'withdrawn', null);
        $application->update(['withdrawn_at' => now()]);

        return response()->json(['message' => 'Application withdrawn successfully.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $applications = Application::query()->where('user_id', $userId);
        $profile = CandidateProfile::query()->firstOrCreate(['user_id' => $userId]);
        $data = $this->profileData($request->user(), $profile);

        $completion = 0;
        if ($data['name']) $completion += 15;
        if ($data['headline']) $completion += 15;
        if ($data['phone'] && $data['location']) $completion += 15;
        if ($data['bio']) $completion += 15;
        if (count($data['skills']) >= 3) $completion += 15;
        if (count($data['experience']) >= 1) $completion += 15;
        if (count($data['education']) >= 1) $completion += 10;
        if ($data['resume_url']) $completion += 15;

        return response()->json([
            'data' => [
                'stats' => [
                    'total_applications' => (clone $applications)->count(),
                    'shortlisted' => (clone $applications)->where('status', 'shortlisted')->count(),
                    'interviews' => (clone $applications)->where('status', 'interview')->count(),
                    'saved_jobs' => SavedJob::query()->where('user_id', $userId)->count(),
                ],
                'recent_applications' => ApplicationResource::collection(
                    (clone $applications)->with(['job.partner', 'candidate'])->latest()->limit(5)->get()
                )->resolve(),
                'profile_completion' => min(100, $completion),
                'recommended_jobs' => PublicJobResource::collection(
                    Job::query()->where('status', 'published')->whereHas('partner', fn ($query) => $query->where('status', 'active'))->with('partner')->latest('published_at')->limit(6)->get()
                )->resolve(),
            ],
        ]);
    }

    private function profileData($user, CandidateProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'headline' => $profile->headline,
            'location' => $profile->location,
            'bio' => $profile->bio,
            'skills' => $profile->skills ?? [],
            'experience' => $profile->experience ?? [],
            'education' => $profile->education ?? [],
            'resume_url' => $profile->resume_path ? $this->resumeUrl($user->id) : null,
        ];
    }

    private function resumeUrl(int $userId): string
    {
        return URL::temporarySignedRoute(
            'candidate.resume.download',
            now()->addMinutes(15),
            ['user' => $userId]
        );
    }

    private function findVisibleJob(int $id): Job
    {
        return Job::query()
            ->whereKey($id)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->whereHas('partner', fn ($query) => $query->where('status', 'active'))
            ->firstOrFail();
    }

    private function recordStatus(Application $application, string $status, ?string $note): void
    {
        $history = $application->status_history ?? [];
        $history[] = ['status' => $status, 'note' => $note, 'timestamp' => now()->toISOString()];
        $application->update(['status' => $status, 'status_note' => $note, 'status_history' => $history]);
    }
}