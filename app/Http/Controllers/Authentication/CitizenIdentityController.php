<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\UploadIdentityPhotosRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Citizen\CitizenIdentityService;

class CitizenIdentityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CitizenIdentityService $citizenIdentityService
    ) {}

    public function upload(UploadIdentityPhotosRequest $request)
    {
        $this->citizenIdentityService->uploadIdentityPhotos(
            $request->user(),
            $request->only(['front_id_photo', 'back_id_photo'])
        );

        return $this->successResponse(
            null,
            'The ID photos have been successfully uploaded and will be reviewed by the municipality.'
        );
    }
}
