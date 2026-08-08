<?php

namespace App\Http\Resources\Complaints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $complaint = $this->relationLoaded('complaint')
            ? $this->complaint
            : null;

        return [
            'id' => $this->id,
            'complaint_id' => $this->complaint_id,

            'title' => $this->title,
            'description' => $this->description,
            'text_location' => $this->text_location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'municipality' => $this->whenLoaded('municipality', fn () => [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
            ]),

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'parent_id' => $this->category->parent_id,
            ]),

            'status' => $this->whenLoaded('currentStatus', fn () => $this->currentStatus ? [
                'id' => $this->currentStatus->id,
                'key' => $this->currentStatus->key,
                'name' => $this->currentStatus->name,
                'is_terminal' => (bool) $this->currentStatus->is_terminal,
            ] : null),

            'images' => ComplaintReportImageResource::collection(
                $this->whenLoaded('images')
            ),

            'status_history' => ComplaintStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),

            'reporters_count' => $complaint?->reports_count,

            'is_draft' => $this->currentStatus?->key === 'draft',

            'is_linked' => $this->complaint_id !== null,

            'can_edit' =>
                $this->currentStatus?->key === 'draft'
                && $this->complaint_id === null,

            'submitted_at' => $this->submitted_at?->toISOString(),
            'linked_at' => $this->linked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
