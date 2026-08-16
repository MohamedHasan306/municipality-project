<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'roles' => $this->getRoleNames(),


            'account_type' => $this->getAccountType(),

            'citizen_profile' => $this->whenLoaded('citizenProfile'),
            'employee_profile' => $this->whenLoaded('employeeProfile'),
        ];
    }

    private function getAccountType(): string
    {
        if ($this->hasRole('citizen')) {
            return 'citizen';
        }

        return 'employee';
    }
}
