<?php

namespace App\Services\Auth;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function sendOtp(string $email): void
    {
        $user = User::where('email', $email)->first();


        if (! $user) {
            return;
        }

        PasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $otp = (string) random_int(1000, 9999);

        PasswordResetOtp::create([
            'email' => $email,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new ResetPasswordOtpMail($otp));
    }

    public function verifyOtp(string $email, string $otp): void
    {
        $record = $this->getValidOtpRecord($email);

        if (! Hash::check($otp, $record->otp)) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['رمز التحقق غير صحيح.'],
            ]);
        }

        $record->update([
            'verified_at' => now(),
        ]);
    }

    public function resetPassword(array $data): void
    {
        $record = $this->getValidOtpRecord($data['email']);

        if (! $record->verified_at) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP must be verified before changing the password.'],
            ]);
        }

        if ($record->verified_at->copy()->addMinutes(10)->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['The grace period has expired after code verification. Please request a new code.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect data.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        $record->update([
            'used_at' => now(),
        ]);

        $user->tokens()->delete();
    }

    private function getValidOtpRecord(string $email): PasswordResetOtp
    {
        $record = PasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => ['There is no valid verification code.'],
            ]);
        }

        if ($record->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code has expired.'],
            ]);
        }

        if ($record->attempts >= 15) {
            throw ValidationException::withMessages([
                'otp' => ['The maximum number of attempts has been exceeded. Request a new code.'],
            ]);
        }

        return $record;
    }
}
