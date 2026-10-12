<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'tagline',
        'slug',
        'logo_path',
        'industry',
        'company_size',
        'website',
        'location',
        'address',
        'email',
        'phone',
        'founded_year',
        'about',
        'benefits',
        'social_links',
        'status',
        'max_active_jobs',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'max_active_jobs' => 'integer',
            'founded_year' => 'integer',
            'benefits' => 'array',
            'social_links' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'partner_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'partner_id');
    }
}