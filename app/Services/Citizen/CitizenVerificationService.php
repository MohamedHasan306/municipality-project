<?php

namespace App\Services\Citizen;

use App\Models\CitizenProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CitizenVerificationService
{
    public function verify(CitizenProfile $citizenProfile): CitizenProfile
    {
        if ($citizenProfile->is_verified) {
            throw ValidationException::withMessages([
                'citizen' => ['هذا المواطن موثق بالفعل.'],
            ]);
        }

        if (! $citizenProfile->front_id_photo || ! $citizenProfile->back_id_photo) {
            throw ValidationException::withMessages([
                'identity_photos' => ['لا يمكن توثيق المواطن قبل رفع صور الهوية.'],
            ]);
        }

        $citizenProfile->update([
            'is_verified' => true,
        ]);

        return $citizenProfile->fresh(['user']);
    }

    public function reject(CitizenProfile $citizenProfile): CitizenProfile
    {
        if ($citizenProfile->is_verified) {
            throw ValidationException::withMessages([
                'citizen' => ['A citizen who is already verified cannot be rejected.'],
            ]);
        }

        if ($citizenProfile->front_id_photo) {
            Storage::disk('public')->delete($citizenProfile->front_id_photo);
        }

        if ($citizenProfile->back_id_photo) {
            Storage::disk('public')->delete($citizenProfile->back_id_photo);
        }

        $citizenProfile->update([
            'front_id_photo' => null,
            'back_id_photo' => null,
            'is_verified' => false,
        ]);

        return $citizenProfile->fresh(['user']);
    }
}
