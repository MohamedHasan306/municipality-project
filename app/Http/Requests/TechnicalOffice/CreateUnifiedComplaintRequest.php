<?php

namespace App\Http\Requests\TechnicalOffice;

use Illuminate\Foundation\Http\FormRequest;

class CreateUnifiedComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'review complaints'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'canonical_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'text_location' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'note' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
