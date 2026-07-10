<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitizenProfileResource;
use App\Http\Traits\ApiResponse;
use App\Models\CitizenProfile;
use App\Services\Citizen\CitizenVerificationService;
use Illuminate\Http\Request;

class CitizenVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CitizenVerificationService $citizenVerificationService
    ) {}

    public function pending(Request $request)
    {
        $citizens = CitizenProfile::query()
            ->with('user')
            ->where('is_verified', false)
            ->whereNotNull('front_id_photo')
            ->whereNotNull('back_id_photo')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return $this->successResponse(
            CitizenProfileResource::collection($citizens),
            'The citizens awaiting Verification have been successfully brought in.'
        );
    }

    public function verify(CitizenProfile $citizenProfile)
    {
        $citizenProfile = $this->citizenVerificationService->verify($citizenProfile);

        return $this->successResponse(
            new CitizenProfileResource($citizenProfile),
            'The citizen has been successfully verified.'
        );
    }

    public function reject(CitizenProfile $citizenProfile)
    {
        $citizenProfile = $this->citizenVerificationService->reject($citizenProfile);

        return $this->successResponse(
            new CitizenProfileResource($citizenProfile),
            'The ID photos have been rejected, and the citizen must upload new photos.'
        );
    }
}
