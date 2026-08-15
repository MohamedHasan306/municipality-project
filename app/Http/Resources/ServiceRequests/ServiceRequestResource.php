<?php

namespace App\Http\Resources\ServiceRequests;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->relationLoaded('serviceTypeVersion')
            ? $this->serviceTypeVersion
            : null;
        $serviceType = $version !== null && $version->relationLoaded('serviceType')
            ? $version->serviceType
            : null;

        return [
            'id' => $this->id,
            'citizen' => $this->when(
                $this->relationLoaded('citizenProfile')
                    && $this->citizenProfile->relationLoaded('user'),
                fn (): array => [
                    'id' => $this->citizenProfile->id,
                    'full_name' => $this->citizenProfile->user->full_name,
                    'national_id' => $this->citizenProfile->national_id,
                ]
            ),
            'service_type' => $serviceType === null ? null : [
                'id' => $serviceType->id,
                'name' => $serviceType->name,
                'municipality' => $serviceType->relationLoaded('municipality')
                    ? [
                        'name' => $serviceType->municipality->name,
                    ]
                    : null,
            ],
//            'version' => $version === null ? null : [
//                'id' => $version->id,
//                'version_number' => $version->version_number,
//                'is_active' => $version->is_active,
//                'fields' => $version->relationLoaded('fields')
//                    ? $version->fields->map(
//                        fn ($field): array => [
//                            'id' => $field->id,
//                            'field_key' => $field->field_key,
//                            'label' => $field->label,
//                            'field_type' => $field->field_type,
//                            'is_required' => $field->is_required,
//                            'options_json' => $field->options_json,
//                            'validation_json' => $field->validation_json,
//                            'condition_json' => $field->condition_json,
//                            'sort_order' => $field->sort_order,
//                        ]
//                    )->values()->all()
//                    : [],
//            ],
            'data' => $this->data_json,
            'current_status' => $this->when(
                $this->relationLoaded('currentStatus'),
                fn (): array => [
                    'id' => $this->currentStatus->id,
                    'code' => $this->currentStatus->code,
                    'name' => $this->currentStatus->name,
                    'name_ar' => $this->currentStatus->name_ar,
                    'is_terminal' => $this->currentStatus->is_terminal,
                ]
            ),
            'attachments' => $this->when(
                $this->relationLoaded('attachments'),
                fn (): array => $this->attachments->map(
                    fn ($attachment): array => [
                        'id' => $attachment->id,
                        'field_key' => $attachment->field_key,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'file_size' => $attachment->file_size,
                        'created_at' => $attachment->created_at?->toISOString(),
                    ]
                )->values()->all()
            ),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'document' => $this->when(
                $this->relationLoaded('generatedDocument'),
                fn () => $this->generatedDocument === null
                    ? null
                    : (new GeneratedDocumentResource($this->generatedDocument))->resolve($request)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
