<?php

namespace App\Services\Auth;

use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Models\CitizenProfile;
use App\Models\EmployeeProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    use ApiResponse;
    public function login(array $data): array
    {
        $user = User::with(['citizenProfile', 'employeeProfile'])
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'loginFail' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->ensureUserCanLogin($user);


        if ($user->must_change_password && $user->tokens()->exists()) {
            $user->tokens()->delete();

            $token = $user->createToken(
                $data['device_name'] ?? 'change-password-token',
                ['change-password']
            )->plainTextToken;

            return [
                'token' => $token,
                'user' => $user->fresh(['citizenProfile', 'employeeProfile']),
                'requires_password_change' => true,
                'temporary_password_reused' => true,
            ];
        }

        $abilities = $user->must_change_password
            ? ['change-password']
            : ['*'];

        $token = $user->createToken(
            $data['device_name'] ?? 'api-token',
            $abilities
        )->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->fresh(['citizenProfile', 'employeeProfile']),
            'requires_password_change' => (bool) $user->must_change_password,
        ];
    }

    public function registerCitizen(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
            ]);

            CitizenProfile::create([
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'],
                'national_id' => $data['national_id'],
                'address' => $data['address'],
                'front_id_photo' => $data['front_id_photo_path'] ?? null,
                'back_id_photo' => $data['back_id_photo_path'] ?? null,
                'is_verified' => false,

            ]);

            $user->assignRole('citizen');

            $user->load(['citizenProfile', 'employeeProfile']);

            $token = $user->createToken('citizen-token')->plainTextToken;

            return [
                'token' => $token,
                'user' => $user,
            ];
        });
    }

    public function registerEmployee(array $data): User
    {
        return DB::transaction(function () use ($data) {

            if ($data['role'] === 'mayor') {
                $municipalityAlreadyHasAdmin = User::query()
                    ->whereHas('employeeProfile', function ($query) use ($data) {
                        $query->where('municipality_id', $data['municipality_id']);
                    })
                    ->role('mayor')
                    ->exists();

                if ($municipalityAlreadyHasAdmin) {
                    throw ValidationException::withMessages([
                        'OneMayorError' => ['This municipality already has a municipal manager.'],
                    ]);
                }
            }

            $user = User::create([
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'must_change_password' => true,
            ]);

            EmployeeProfile::create([
                'user_id' => $user->id,
                'municipality_id' => $data['municipality_id'],
                'hire_date' => $data['hire_date'],
                'national_id' => $data['national_id'],
                'status' => $data['status'] ?? 'active',
            ]);

            $user->assignRole($data['role']);

            return $user->load(['citizenProfile', 'employeeProfile']);
        });
    }

    private function ensureUserCanLogin(User $user): void
    {
        if ($user->employeeProfile && $user->employeeProfile->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }
    }

    public function changeTemporaryPassword(User $user, array $data): void
    {
        if (! $user->must_change_password) {
            throw ValidationException::withMessages([
                'password' => ['This account does not require a password change.'],
            ]);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
//            'password_changed_at' => now(),
        ]);


        $user->tokens()->delete();
    }
}
