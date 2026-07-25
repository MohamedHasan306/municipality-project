<?php

namespace App\Http\Requests\Municipality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage municipalities') ?? false;
    }

    public function rules(): array
    {
        $municipalityId = $this->route('municipality')?->id ?? $this->route('municipality');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255',
                Rule::unique('municipalities', 'name')->ignore($municipalityId),
            ],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email',
                Rule::unique('municipalities', 'email')->ignore($municipalityId),
            ],
            'status' => ['sometimes', 'boolean'],
            'governorate_id' => ['required', 'exists:governorates,id'],
        ];
    }
}
