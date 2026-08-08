<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->citizenProfile !== null
            && $this->user()->can('create complaint');
    }

    public function rules(): array
    {
        return [
            'municipality_id' => [
                'nullable',
                'integer',
                Rule::exists('municipalities', 'id')
                    ->where(
                        fn ($query) => $query->where('status', true)
                    ),
            ],

            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'text_location' => [
                'nullable',
                'string',
                'max:500',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}
