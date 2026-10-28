<?php

namespace App\Http\Requests\ServiceRequests;

use App\Models\ServiceFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTypeVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole('system_admin')
            && $user->can('manage service forms');
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.field_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => [
                'required',
                'string',
                Rule::in(ServiceFormField::SUPPORTED_TYPES),
            ],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.options_json' => ['nullable', 'array', 'max:100'],
            'fields.*.validation_json' => ['nullable', 'array', 'max:20'],
            'fields.*.condition_json' => ['nullable', 'array', 'max:100'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.field_key.regex' =>
                'Each field key may contain only lowercase letters, numbers, and underscores.',
            'fields.*.field_key.distinct' => 'Field keys must be unique within the version.',
            'fields.*.options_json.*.distinct' => 'Options must be unique within each field.',
            'fields.*.validation_json.*.distinct' =>
                'Additional validation rules must be unique within each field.',
        ];
    }
}
