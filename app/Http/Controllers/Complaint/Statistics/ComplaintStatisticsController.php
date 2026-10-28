<?php

namespace App\Http\Controllers\Complaint\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\ComplaintStatisticsRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Complaints\ComplaintStatisticsService;
use Illuminate\Http\JsonResponse;

class ComplaintStatisticsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ComplaintStatisticsService $service)
    {
    }

    public function index(ComplaintStatisticsRequest $request): JsonResponse
    {
        $statistics = $this->service->overview(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            $statistics,
            'The complaint statistics were retrieved successfully.'
        );
    }
}
