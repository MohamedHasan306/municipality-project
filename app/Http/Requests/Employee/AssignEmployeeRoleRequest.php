<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEmployeeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign roles to employees') ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                Rule::notIn(['system_admin', 'citizen']),
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'role.required' => 'The role is required.',
            'role.exists' => 'The selected role does not exist.',
            'role.not_in' => 'This role cannot be assigned.',
        ];
    }
}
