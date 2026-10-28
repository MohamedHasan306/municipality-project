<?php

namespace App\Http\Resources\Complaints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'complaint_report_id' => $this->complaint_report_id,

            'from_status' => $this->whenLoaded('fromStatus', fn () => $this->fromStatus ? [
                'id' => $this->fromStatus->id,
                'key' => $this->fromStatus->key,
                'name' => $this->fromStatus->name,
            ] : null),

            'to_status' => $this->whenLoaded('toStatus', fn () => $this->toStatus ? [
                'id' => $this->toStatus->id,
                'key' => $this->toStatus->key,
                'name' => $this->toStatus->name,
            ] : null),

            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'full_name' => $this->changedBy->full_name,
            ] : null),

            'note' => $this->note,
            'is_public' => (bool) $this->is_public,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
