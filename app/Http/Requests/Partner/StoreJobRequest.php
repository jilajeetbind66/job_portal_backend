<?php
namespace App\Http\Requests\Partner;
use Illuminate\Foundation\Http\FormRequest;
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

    public function rules(): array
    {
        $partnerId = $this->user()?->partner_id;

        return [
            'title' => ['required', 'string', 'max:255'],

            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('job_postings', 'slug')
                    ->where('partner_id', $partnerId),
            ],

            'description' => ['required', 'string'],

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
                'after:today',
            ],
        ];
    }
}
