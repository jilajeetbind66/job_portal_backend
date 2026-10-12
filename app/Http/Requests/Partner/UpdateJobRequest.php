<?php
namespace App\Http\Requests\Partner;
use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $job = $this->route('job');

        return $user !== null
            && $job instanceof Job
            && $user->partner_id !== null
            && (int) $user->partner_id === (int) $job->partner_id
            && $user->hasAnyRole(['partner_admin', 'partner_recruiter'])
            && $user->can('jobs.update');
    }

    public function rules(): array
    {
        $user = $this->user();
        $job = $this->route('job');

        if (! $job instanceof Job || $user === null) {
            return [];
        }

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('job_postings', 'slug')
                    ->where('partner_id', $user->partner_id)
                    ->ignore($job->id),
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
            ],

            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requirements' => ['sometimes', 'nullable', 'string'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['INR', 'USD', 'EUR', 'GBP'])],

            'employment_type' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                Rule::in(['onsite', 'remote', 'hybrid']),
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'min_exp' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'max_exp' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'gte:min_exp',
            ],

            'salary_min' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'salary_max' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'gte:salary_min',
            ],

            'openings' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:today',
            ],
        ];
    }
}
