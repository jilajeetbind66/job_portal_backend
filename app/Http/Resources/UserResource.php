<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'email' => $this->email,

            'phone' => $this->phone,

            'status' => $this->status,

            'partner_id' => $this->partner_id,

            'partner' => $this->whenLoaded('partner', function () {
                return [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'slug' => $this->partner->slug,
                    'status' => $this->partner->status,
                ];
            }),

            'role' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->roles->first()?->name
            ),

            'last_login_at' => $this->last_login_at?->toISOString(),

            'email_verified_at' => $this->email_verified_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}