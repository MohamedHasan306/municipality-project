<?php

namespace App\Http\Resources\WorkUnits;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipality_id' => $this->municipality_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,

            'municipality' => $this->whenLoaded('municipality', fn () => [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
            ]),

            'department_manager' => $this->whenLoaded('departmentManager', fn () => $this->departmentManager ? [
                'id' => $this->departmentManager->id,
                'full_name' => $this->departmentManager->full_name,
                'email' => $this->departmentManager->email,
                'phone' => $this->departmentManager->phone,
            ] : null),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
