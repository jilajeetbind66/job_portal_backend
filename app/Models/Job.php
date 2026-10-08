<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model
{
    use HasFactory, BelongsToPartner;

    protected $table = 'job_postings';

    protected $fillable = [
        'partner_id',
        'posted_by',
        'title',
        'slug',
        'description',
        'employment_type',
        'work_mode',
        'location',
        'min_exp',
        'max_exp',
        'salary_min',
        'salary_max',
        'openings',
        'status',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'min_exp' => 'integer',
            'max_exp' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'openings' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}