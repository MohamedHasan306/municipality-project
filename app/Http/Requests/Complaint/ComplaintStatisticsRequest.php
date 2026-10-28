<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class ComplaintStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view complaint statistics') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'nullable',
                'required_with:date_to',
                'date',
            ],

            'date_to' => [
                'nullable',
                'required_with:date_from',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_with' => 'The start date is required when an end date is provided.',
            'date_from.date' => 'The start date must be a valid date.',
            'date_to.required_with' => 'The end date is required when a start date is provided.',
            'date_to.date' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be greater than or equal to the start date.',
        ];
    }
}
