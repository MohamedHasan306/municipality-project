<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CitizenProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name,
                'email' => $this->user?->email,
                'phone_number' => $this->user?->phone_number,
            ],


            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'national_id' => $this->national_id,
            'place_of_birth' => $this->place_of_birth,
            'needs_special_care' => (bool) $this->needs_special_care,

            'front_id_photo' => $this->front_id_photo
                ? Storage::disk('public')->url($this->front_id_photo)
                : null,

            'back_id_photo' => $this->back_id_photo
                ? Storage::disk('public')->url($this->back_id_photo)
                : null,

            'is_verified' => (bool) $this->is_verified,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
