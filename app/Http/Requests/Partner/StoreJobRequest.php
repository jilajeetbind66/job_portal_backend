<?php
namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->partner_id !== null
            && $user->hasAnyRole([
                'partner_admin',
                'partner_recruiter',
            ])
            && $user->can('jobs.create');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (!$this->filled('slug') && $this->filled('title')) {
            $baseSlug = Str::slug($this->string('title')->toString());
            $merge['slug'] = $baseSlug . '-' . Str::lower(Str::random(5));
        }

        if (!$this->filled('work_mode') && $this->filled('workplace_type')) {
            $wpt = $this->string('workplace_type')->toString();
            $merge['work_mode'] = ($wpt === 'on_site' || $wpt === 'onsite') ? 'onsite' : $wpt;
        }

        if (!$this->filled('openings')) {
            $merge['openings'] = 1;
        }

        if (!$this->filled('expires_at') && $this->filled('deadline')) {
            $merge['expires_at'] = $this->string('deadline')->toString();
        }

        if ($this->filled('skills') && is_string($this->skills)) {
            $merge['skills'] = array_values(array_filter(array_map('trim', explode(',', $this->skills))));
        }

        if ($this->filled('experience_level') && (!$this->filled('min_exp') || $this->min_exp === null)) {
            $level = $this->string('experience_level')->toString();
            [$min, $max] = match ($level) {
                'entry' => [0, 2],
                'mid' => [3, 5],
                'senior' => [6, 8],
                default => [8, null],
            };
            $merge['min_exp'] = $min;
            if ($max !== null) {
                $merge['max_exp'] = $max;
            }
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $partnerId = $this->user()?->partner_id;

        return [
            'title' => ['required', 'string', 'max:255'],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('job_postings', 'slug')
                    ->where('partner_id', $partnerId),
            ],

            'description' => ['required', 'string'],

            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requirements' => ['sometimes', 'nullable', 'string'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['INR', 'USD', 'EUR', 'GBP'])],

            'employment_type' => [
                'required',
                'string',
                Rule::in([
                    'full_time',
                    'part_time',
                    'contract',
                    'internship',
                    'temporary',
                ]),
            ],

            'work_mode' => [
                'required',
                'string',
                Rule::in(['onsite', 'remote', 'hybrid']),
            ],

            'location' => ['nullable', 'string', 'max:255'],

            'min_exp' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'max_exp' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'gte:min_exp',
            ],

            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'salary_max' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'gte:salary_min',
            ],

            'openings' => ['required', 'integer', 'min:1'],

            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
