<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicJobResource;
use App\Models\Job;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicJobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'work_mode' => ['sometimes', 'string', 'in:onsite,on_site,remote,hybrid'],
            'workplace_type' => ['sometimes', 'string', 'in:onsite,on_site,remote,hybrid'],
            'employment_type' => ['sometimes', 'string', 'in:full_time,part_time,contract,internship,temporary'],
            'experience_level' => ['sometimes', 'string', 'in:entry,mid,senior,lead,executive'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Job::withoutGlobalScope('partner')
            ->where('status', 'published')
            ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->whereHas('partner', fn ($builder) => $builder->where('status', 'active'))
            ->with('partner')
            ->latest('published_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%"));
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        $workMode = $filters['work_mode'] ?? $filters['workplace_type'] ?? null;
        if ($workMode !== null) {
            $query->where('work_mode', $workMode === 'on_site' ? 'onsite' : $workMode);
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (! empty($filters['experience_level'])) {
            [$minimum, $maximum] = match ($filters['experience_level']) {
                'entry' => [0, 2],
                'mid' => [3, 5],
                'senior' => [6, 8],
                default => [9, null],
            };

            $query->where('min_exp', '>=', $minimum);
            if ($maximum !== null) {
                $query->where('min_exp', '<=', $maximum);
            }
        }

        return PublicJobResource::collection(
            $query->paginate($filters['per_page'] ?? $filters['perPage'] ?? 15)->withQueryString()
        );
    }

    public function show(string $slug): PublicJobResource
    {
        $job = Job::withoutGlobalScope('partner')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->whereHas('partner', fn ($builder) => $builder->where('status', 'active'))
            ->with('partner')
            ->firstOrFail();

        return new PublicJobResource($job);
    }

    public function company(string $slug): JsonResponse
    {
        $partner = Partner::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $jobs = Job::withoutGlobalScope('partner')
            ->where('partner_id', $partner->id)
            ->where('status', 'published')
            ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->with('partner')
            ->latest('published_at')
            ->get();

        return response()->json([
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'logo' => $partner->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.partner_logo_disk'))->url($partner->logo_path)
                    : null,
                'industry' => $partner->industry,
                'website' => $partner->website,
                'description' => $partner->about,
                'jobs' => $jobs->map(fn (Job $job) => (new PublicJobResource($job))->resolve()),
            ],
        ]);
    }
}