<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'partner_id' => [
                'nullable',
                'integer',
                'exists:partners,id',
            ],

            'role' => [
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

            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',

            'partner_id.exists' => 'Selected partner does not exist.',

            'role.required' => 'User role is required.',
            'role.in' => 'Invalid user role.',
        ];
    }
}