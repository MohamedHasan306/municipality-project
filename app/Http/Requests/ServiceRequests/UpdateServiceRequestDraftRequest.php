<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole('citizen')
            && $user->can('manage own service request drafts');
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
        ];
    }
}
