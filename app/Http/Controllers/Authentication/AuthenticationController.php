<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeTemporaryPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCitizenRequest;
use App\Http\Requests\Auth\RegisterEmployeeRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());

        $message = 'You have successfully logged in.';

        if ($result['requires_password_change']) {
            $message = 'The password must be changed before using the system.';

            if ($result['temporary_password_reused'] ?? false) {
                $message = 'The temporary password has already been used. A new token has been issued solely for changing the password. ';
            }
        }

        return $this->successResponse([
            'token' => $result['token'],
            'requires_password_change' => $result['requires_password_change'],
            'user' => new UserResource($result['user']),
        ], $message,200);
    }
    public function registerCitizen(RegisterCitizenRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('front_id_photo')) {
            $data['front_id_photo_path'] = $request
                ->file('front_id_photo')
                ->store('citizens/id_photos', 'public');
        }

        if ($request->hasFile('back_id_photo')) {
            $data['back_id_photo_path'] = $request
                ->file('back_id_photo')
                ->store('citizens/id_photos', 'public');
        }

        $result = $this->authService->registerCitizen($data);

        return $this->successResponse([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 'The Citizen Account has been successfully created.', 201);
    }

    public function registerEmployee(RegisterEmployeeRequest $request)
    {
        $user = $this->authService->registerEmployee($request->validated());

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'The Employee Account has been successfully created.', 201);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['citizenProfile', 'employeeProfile']);

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'User data has been successfully retrieved.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'You have successfully logged out.');
    }

    public function changeTemporaryPassword(ChangeTemporaryPasswordRequest $request)
    {
        $this->authService->changeTemporaryPassword(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            null,
            'The password has been changed successfully. Please log in again.'
        );
    }

    public function allEmployees(Request $request)
    {

        $query = User::whereHas('employeeProfile')
            ->with(['employeeProfile', 'roles'])->get();

        return $this->successResponse($query,"All Employee Profiles");

    }


}
