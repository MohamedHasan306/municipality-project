<?php

namespace App\Http\Requests\TechnicalOffice;

use Illuminate\Foundation\Http\FormRequest;

class MergeComplaintReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'merge complaint reports'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'complaint_id' => [
                'required',
                'integer',
                'exists:complaints,id',
            ],
        ];
    }
}
