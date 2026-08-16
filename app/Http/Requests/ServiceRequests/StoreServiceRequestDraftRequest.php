<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole('citizen')
            && $user->can('submit service request');
    }

    public function rules(): array
    {
        return [
            'service_type_version_id' => [
                'required',
                'integer',
                'exists:service_type_versions,id',
            ],
            'data' => ['sometimes', 'array'],
        ];
    }
}
