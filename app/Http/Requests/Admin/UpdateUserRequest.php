<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'partner_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:partners,id',
            ],

            'role' => [
                'sometimes',
                'required',
                Rule::in([
                    'candidate',
                    'partner_recruiter',
                    'partner_admin',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'User name is required.',

            'email.required' => 'User email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',

            'partner_id.exists' => 'Selected partner does not exist.',

            'role.required' => 'User role is required.',
            'role.in' => 'Invalid user role.',
        ];
    }
}