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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path
                ? Storage::disk(config('filesystems.partner_logo_disk'))->url($this->logo_path)
                : null,
            'industry' => $this->industry,
            'website' => $this->website,
            'about' => $this->about,
            'status' => $this->status,
            'max_active_jobs' => $this->max_active_jobs,
            'created_by' => $this->created_by,

            'users_count' => $this->whenCounted('users'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}