<?php

namespace App\Http\Requests\DepartmentManager;

use Illuminate\Foundation\Http\FormRequest;

class RejectComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject complaints') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required.',
            'reason.string' => 'The rejection reason must be a string.',
            'reason.min' => 'The rejection reason must contain at least 5 characters.',
            'reason.max' => 'The rejection reason may not be greater than 2000 characters.',
        ];
    }
}
