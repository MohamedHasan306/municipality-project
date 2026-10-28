<?php

namespace App\Http\Controllers\Complaint\TechnicalOffice;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkUnits\WorkUnitResource;
use App\Http\Traits\ApiResponse;
use App\Services\WorkUnit\TechnicalOfficeWorkUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicalOfficeWorkUnitController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TechnicalOfficeWorkUnitService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $workUnits = $this->service->getAssignableWorkUnits(
            $request->user()
        );

        return $this->successResponse(
            WorkUnitResource::collection($workUnits)->resolve($request),
            'The available work units were retrieved successfully.'
        );
    }
}
