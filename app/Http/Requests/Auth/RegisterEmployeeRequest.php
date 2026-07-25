<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage municipality employees') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'municipality_id' => ['required', 'exists:municipalities,id'],
            'hire_date' => ['required', 'date'],
            'national_id' => ['required', 'string', 'max:255', 'unique:employee_profiles,national_id'],
            'status' => ['nullable', 'in:active,suspended,retired'],

            'role' => [
                'required',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                Rule::notIn(['system_admin', 'citizen'])
            ],
        ];
    }
}
