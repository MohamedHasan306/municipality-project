<?php

namespace App\Http\Resources\Complaints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentManagerComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'canonical_description' => $this->canonical_description,
            'text_location' => $this->text_location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'municipality' => $this->whenLoaded('municipality', fn () => [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
            ]),

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'parent_id' => $this->category->parent_id,
                'name' => $this->category->name,
            ]),

            'current_status' => $this->whenLoaded('currentStatus', fn () => [
                'id' => $this->currentStatus->id,
                'key' => $this->currentStatus->key,
                'name' => $this->currentStatus->name,
                'is_terminal' => (bool) $this->currentStatus->is_terminal,
            ]),

            'work_units' => $this->whenLoaded('workUnits', fn () => $this->workUnits->map(fn ($workUnit) => [
                'id' => $workUnit->id,
                'name' => $workUnit->name,
                'department_manager_id' => $workUnit->department_manager_id,

                'department_manager' =>
                    $workUnit->relationLoaded('departmentManager')
                    && $workUnit->departmentManager
                        ? [
                        'id' => $workUnit->departmentManager->id,
                        'full_name' => $workUnit->departmentManager->full_name,
                        'email' => $workUnit->departmentManager->email,
                    ]
                        : null,

                'assigned_by' => $workUnit->pivot?->assigned_by,
                'assigned_at' => $workUnit->pivot?->assigned_at?->toISOString(),
            ])->values()),

            'reports' => $this->whenLoaded('reports', fn () => $this->reports->map(function ($report) use ($request) {
                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'text_location' => $report->text_location,
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,

                    'current_status' =>
                        $report->relationLoaded('currentStatus')
                        && $report->currentStatus
                            ? [
                            'id' => $report->currentStatus->id,
                            'key' => $report->currentStatus->key,
                            'name' => $report->currentStatus->name,
                        ]
                            : null,

                    'images' => $report->relationLoaded('images')
                        ? ComplaintReportImageResource::collection($report->images)->resolve($request)
                        : [],

                    'status_history' => $report->relationLoaded('statusHistories')
                        ? ComplaintStatusHistoryResource::collection($report->statusHistories)->resolve($request)
                        : [],

                    'submitted_at' => $report->submitted_at?->toISOString(),
                    'linked_at' => $report->linked_at?->toISOString(),
                ];
            })->values()),

            'reports_count' => $this->whenCounted('reports'),

            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
