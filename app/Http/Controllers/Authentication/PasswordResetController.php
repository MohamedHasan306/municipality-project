<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Auth\PasswordResetService;

class PasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {}

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->passwordResetService->sendOtp(
            $request->validated('email')
        );

        return $this->successResponse(
            null,
            'If the email address is registered with us, a verification code will be sent.'
        );
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();

        $this->passwordResetService->verifyOtp(
            $data['email'],
            $data['otp']
        );

        return $this->successResponse(
            null,
            'The verification code is valid.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $this->passwordResetService->resetPassword(
            $request->validated()
        );

        return $this->successResponse(
            null,
            'The password has been changed successfully.'
        );
    }
}
