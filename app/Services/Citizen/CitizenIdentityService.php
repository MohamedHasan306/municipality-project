<?php

namespace App\Services\Citizen;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CitizenIdentityService
{
    public function uploadIdentityPhotos(User $user, array $files): void
    {
        $citizenProfile = $user->citizenProfile;

        if (! $citizenProfile) {
            throw ValidationException::withMessages([
                'user' => ['This user does not have a citizen profile.
'],
            ]);
        }

        if ($citizenProfile->is_verified) {
            throw ValidationException::withMessages([
                'identity' => ['This account is already verified.'],
            ]);
        }

        if ($citizenProfile->front_id_photo) {
            Storage::disk('public')->delete($citizenProfile->front_id_photo);
        }

        if ($citizenProfile->back_id_photo) {
            Storage::disk('public')->delete($citizenProfile->back_id_photo);
        }

        $frontPath = $files['front_id_photo']->store('citizens/id_photos', 'public');
        $backPath = $files['back_id_photo']->store('citizens/id_photos', 'public');

        $citizenProfile->update([
            'front_id_photo' => $frontPath,
            'back_id_photo' => $backPath,
            'is_verified' => false,
        ]);
    }
}
