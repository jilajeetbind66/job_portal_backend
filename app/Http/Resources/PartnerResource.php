<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PartnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $logoUrl = $this->logo_path
            ? Storage::disk(config('filesystems.partner_logo_disk'))->url($this->logo_path)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name ?: $this->name,
            'tagline' => $this->tagline,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'logo_url' => $logoUrl,
            'logo' => $logoUrl,
            'logoUrl' => $logoUrl,
            'industry' => $this->industry,
            'company_size' => $this->company_size,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'address' => $this->address,
            'about' => $this->about,
            'description' => $this->about,
            'founded_year' => $this->founded_year,
            'benefits' => $this->benefits ?? [],
            'social_links' => $this->social_links ?? [],
            'status' => $this->status,
            'max_active_jobs' => $this->max_active_jobs,
            'created_by' => $this->created_by,

            'users_count' => $this->users_count ?? $this->users()->count(),
            'jobs_count' => $this->jobs_count ?? $this->jobs()->count(),
            'recruitersCount' => $this->users_count ?? $this->users()->count(),
            'jobsCount' => $this->jobs_count ?? $this->jobs()->count(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}