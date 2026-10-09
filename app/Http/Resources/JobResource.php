<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'partner_id' => $this->partner_id,

            'posted_by' => $this->posted_by,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            'employment_type' => $this->employment_type,

            'work_mode' => $this->work_mode,

            'location' => $this->location,

            'min_exp' => $this->min_exp,

            'max_exp' => $this->max_exp,

            'salary_min' => $this->salary_min,

            'salary_max' => $this->salary_max,

            'openings' => $this->openings,

            'status' => $this->status,

            'published_at' => $this->published_at?->toISOString(),

            'expires_at' => $this->expires_at?->toISOString(),

            'partner' => $this->whenLoaded('partner', function () {
                return [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'slug' => $this->partner->slug,
                ];
            }),

            'posted_by_user' => $this->whenLoaded('postedBy', function () {
                return [
                    'id' => $this->postedBy->id,
                    'name' => $this->postedBy->name,
                    'email' => $this->postedBy->email,
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}