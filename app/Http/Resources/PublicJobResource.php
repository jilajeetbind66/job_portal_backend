<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $experience = match (true) {
            ($this->max_exp ?? $this->min_exp) <= 2 => 'entry',
            ($this->max_exp ?? $this->min_exp) <= 5 => 'mid',
            ($this->max_exp ?? $this->min_exp) <= 8 => 'senior',
            default => 'lead',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'department' => $this->department,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'skills' => $this->skills ?? [],
            'employment_type' => $this->employment_type,
            'workplace_type' => $this->work_mode === 'onsite' ? 'on_site' : $this->work_mode,
            'work_mode' => $this->work_mode,
            'location' => $this->location,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'currency' => $this->currency,
            'experience_level' => $experience,
            'min_exp' => $this->min_exp,
            'max_exp' => $this->max_exp,
            'openings' => $this->openings,
            'status' => $this->status,
            'is_saved' => false,
            'applicants_count' => 0,
            'company_name' => $this->partner?->name,
            'company_slug' => $this->partner?->slug,
            'company_logo' => $this->partner?->logo_path
                ? Storage::disk(config('filesystems.partner_logo_disk'))->url($this->partner->logo_path)
                : null,
            'partner' => $this->whenLoaded('partner', fn () => [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
                'slug' => $this->partner->slug,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}