<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
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
        return [
            'role' => [
                'required',
                Rule::in([
                    'candidate',
                    'partner_recruiter',
                    'partner_admin',
                    'super_admin',
                ]),
            ],
            'partner_id' => ['sometimes', 'nullable', 'integer', 'exists:partners,id'],
        ];
    }
}