<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage municipality employees') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],

            'hire_date' => ['required', 'date'],
            'national_id' => ['required', 'string', 'max:255', 'unique:employee_profiles,national_id'],
            'status' => ['nullable', 'in:active,suspended,retired'],

            'role' => [
                'required',
                'in:mayor,technical_office,engineering_office,department_manager,field_inspector',
            ],
        ];
    }
}
