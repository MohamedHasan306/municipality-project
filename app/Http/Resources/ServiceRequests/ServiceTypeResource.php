<?php

namespace App\Http\Resources\ServiceRequests;

use App\Services\ServiceRequests\ServiceDocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipality' => $this->when(
                $this->relationLoaded('municipality'),
                fn (): array => [
                    'id' => $this->municipality->id,
                    'name' => $this->municipality->name,
                ]
            ),
            'name' => $this->name,
            'description' => $this->description,
            'document_template_key' => ServiceDocumentTemplate::KEY,
            'is_active' => $this->is_active,
            'active_version' => $this->when(
                $this->relationLoaded('activeVersion'),
                function (): ?array {
                    if ($this->activeVersion === null) {
                        return null;
                    }

                    return [
                        'id' => $this->activeVersion->id,
                        'version_number' => $this->activeVersion->version_number,
                        'is_active' => $this->activeVersion->is_active,
                        'fields' => $this->activeVersion->relationLoaded('fields')
                            ? $this->activeVersion->fields->map(
                                fn ($field): array => [
                                    'id' => $field->id,
                                    'field_key' => $field->field_key,
                                    'label' => $field->label,
                                    'field_type' => $field->field_type,
                                    'is_required' => $field->is_required,
                                    'options_json' => $field->options_json,
                                    'validation_json' => $field->validation_json,
                                    'condition_json' => $field->condition_json,
                                    'sort_order' => $field->sort_order,
                                ]
                            )->values()->all()
                            : [],
                    ];
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
