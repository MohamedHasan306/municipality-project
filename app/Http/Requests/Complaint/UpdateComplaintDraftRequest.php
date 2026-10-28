<?php

namespace App\Http\Requests\Complaint;

use App\Models\ComplaintReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof ComplaintReport
            && $this->user()?->can('update', $report);
    }

    public function rules(): array
    {
        return [
            'municipality_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('municipalities', 'id')
                    ->where(
                        fn ($query) => $query->where('status', true)
                    ),
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
            ],

            'title' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
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

            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}
