<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('partners', 'slug'),
            ],

            'logo_path' => [
                'nullable',
                'string',
                'max:500',
            ],

            'industry' => [
                'nullable',
                'string',
                'max:255',
            ],

            'website' => [
                'nullable',
                'url',
                'max:500',
            ],

            'about' => [
                'nullable',
                'string',
            ],

            'max_active_jobs' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Partner name is required.',

            'slug.required' => 'Partner slug is required.',
            'slug.alpha_dash' => 'Partner slug may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'This partner slug is already in use.',

            'website.url' => 'Please provide a valid website URL.',

            'max_active_jobs.required' => 'Maximum active jobs limit is required.',
            'max_active_jobs.integer' => 'Maximum active jobs must be a number.',
            'max_active_jobs.min' => 'Maximum active jobs must be at least 1.',
        ];
    }
}