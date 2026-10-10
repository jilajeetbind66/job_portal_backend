<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'published',
                    'rejected',
                    'closed',
                ]),
            ],

            'note' => [
                'required_if:status,rejected',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Job moderation status is required.',
            'status.in' => 'Invalid moderation status. Allowed values are published, rejected, or closed.',
            'note.required_if' => 'A rejection reason is required when rejecting a job.',
            'note.string' => 'Moderation note must be valid text.',
            'note.max' => 'Moderation note may not exceed 1000 characters.',
        ];
    }
}
