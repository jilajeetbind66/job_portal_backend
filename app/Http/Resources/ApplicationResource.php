<?php

namespace App\Http\Resources;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $candidate = $this->candidate;
        $profile = $candidate
            ? CandidateProfile::query()->firstWhere('user_id', $candidate->id)
            : null;
        $resumeUrl = $profile?->resume_path
            ? URL::temporarySignedRoute(
                'candidate.resume.download',
                now()->addMinutes(15),
                ['user' => $candidate->id]
            )
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'cover_letter' => $this->cover_letter,
            'status_note' => $this->status_note,
            'applied_at' => $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'timeline' => collect($this->status_history ?? [])->map(fn ($entry) => [
                'status' => $entry['status'] ?? null,
                'note' => $entry['note'] ?? null,
                'timestamp' => $entry['timestamp'] ?? null,
            ])->values(),
            'job' => $this->whenLoaded('job', fn () => [
                'id' => $this->job->id,
                'title' => $this->job->title,
                'slug' => $this->job->slug,
                'location' => $this->job->location,
                'work_mode' => $this->job->work_mode,
                'workplace_type' => $this->job->work_mode === 'onsite' ? 'on_site' : $this->job->work_mode,
                'company_name' => $this->job->partner?->name,
                'company_slug' => $this->job->partner?->slug,
                'company_logo' => $this->job->partner?->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.partner_logo_disk'))->url($this->job->partner->logo_path)
                    : null,
            ]),
            'candidate' => $candidate ? [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'location' => $profile?->location,
                'current_title' => $profile?->headline,
                'experience_years' => count($profile?->experience ?? []),
                'skills' => $profile?->skills ?? [],
                'resume_url' => $resumeUrl,
                'linkedin' => null,
                'github' => null,
            ] : null,
        ];
    }
}