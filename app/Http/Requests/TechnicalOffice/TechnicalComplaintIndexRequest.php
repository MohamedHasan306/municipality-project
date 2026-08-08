<?php

namespace App\Http\Requests\TechnicalOffice;

use Illuminate\Foundation\Http\FormRequest;

class TechnicalComplaintIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view municipality complaints') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                'exists:complaint_statuses,key',
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:complaint_categories,id',
            ],

            'work_unit_id' => [
                'nullable',
                'integer',
                'exists:work_units,id',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.exists' => 'The selected complaint status does not exist.',
            'category_id.exists' => 'The selected complaint category does not exist.',
            'work_unit_id.exists' => 'The selected work unit does not exist.',
            'date_from.date' => 'The start date must be a valid date.',
            'date_to.date' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be greater than or equal to the start date.',
            'search.max' => 'The search value may not be greater than 255 characters.',
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value may not be greater than 50.',
        ];
    }
}
