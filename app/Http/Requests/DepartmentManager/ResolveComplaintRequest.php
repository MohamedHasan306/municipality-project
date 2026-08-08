<?php

namespace App\Http\Requests\DepartmentManager;

use Illuminate\Foundation\Http\FormRequest;

class ResolveComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resolve complaints') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => [
                'sometimes',
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'note.string' => 'The resolution note must be a string.',
            'note.min' => 'The resolution note must contain at least 5 characters.',
            'note.max' => 'The resolution note may not be greater than 2000 characters.',
        ];
    }
}
