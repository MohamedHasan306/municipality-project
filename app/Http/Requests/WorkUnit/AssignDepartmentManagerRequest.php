<?php

namespace App\Http\Requests\WorkUnit;

use Illuminate\Foundation\Http\FormRequest;

class AssignDepartmentManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage work units') ?? false;
    }

    public function rules(): array
    {
        return [
            'department_manager_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'department_manager_id.required' => 'The department manager is required.',
            'department_manager_id.integer' => 'The department manager ID must be an integer.',
            'department_manager_id.exists' => 'The selected department manager does not exist.',
        ];
    }
}
