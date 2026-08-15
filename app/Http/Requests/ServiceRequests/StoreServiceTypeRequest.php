<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole('system_admin')
            && $user->can('manage service types');
    }

    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'document_template_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_template_key.regex' =>
                'The document template key may contain only lowercase letters, numbers, and underscores.',
        ];
    }
}
