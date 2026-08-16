<?php

namespace App\Http\Requests\DepartmentManager;

use Illuminate\Foundation\Http\FormRequest;

class StartComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('execute complaints') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'note.string' => 'The note must be a string.',
            'note.max' => 'The note may not be greater than 1000 characters.',
        ];
    }
}
