<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'super_admin',
            'partner_admin',
        ]) ?? false;
    }

    public function rules(): array
    {
        $partnerId = $this->route('partner')?->id ?? $this->route('partner');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('partners', 'slug')->ignore($partnerId),
            ],

            'logo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'industry' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'website' => [
                'sometimes',
                'nullable',
                'url',
                'max:500',
            ],

            'about' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'max_active_jobs' => [
                'sometimes',
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