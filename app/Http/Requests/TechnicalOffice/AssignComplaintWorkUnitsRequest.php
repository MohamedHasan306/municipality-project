<?php

namespace App\Http\Requests\TechnicalOffice;

use Illuminate\Foundation\Http\FormRequest;

class AssignComplaintWorkUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign complaints to work units') ?? false;
    }

    public function rules(): array
    {
        return [
            'work_unit_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'work_unit_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:work_units,id',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'work_unit_ids.required' => 'At least one work unit must be selected.',
            'work_unit_ids.array' => 'The work units must be provided as an array.',
            'work_unit_ids.min' => 'At least one work unit must be selected.',
            'work_unit_ids.*.integer' => 'Each work unit ID must be an integer.',
            'work_unit_ids.*.distinct' => 'The same work unit cannot be selected more than once.',
            'work_unit_ids.*.exists' => 'One of the selected work units does not exist.',
            'note.string' => 'The note must be a string.',
            'note.max' => 'The note may not be greater than 1000 characters.',
        ];
    }
}
