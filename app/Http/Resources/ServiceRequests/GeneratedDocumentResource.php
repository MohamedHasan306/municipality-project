<?php

namespace App\Http\Resources\ServiceRequests;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'verification_code' => $this->verification_code,
            'issued_at' => $this->issued_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_expired' => $this->isExpired(),
            'is_signed' => $this->relationLoaded('signature')
                ? $this->signature !== null
                : null,
        ];
    }
}
