<?php

namespace App\Http\Resources\Complaints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ComplaintReportImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'original_name' => $this->original_name,

            'mime_type' => $this->mime_type,

            'file_size' => $this->file_size,

            'view_url' => Storage::disk('public')->url(
                $this->file_path
            ),
        ];
    }
}
