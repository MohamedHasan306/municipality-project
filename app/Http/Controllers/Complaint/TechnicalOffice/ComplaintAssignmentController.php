<?php

namespace App\Http\Controllers\Complaint\TechnicalOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicalOffice\AssignComplaintWorkUnitsRequest;
use App\Http\Resources\Complaints\TechnicalComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\Complaint;
use App\Services\Complaints\ComplaintAssignmentService;
use Illuminate\Http\JsonResponse;

class ComplaintAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ComplaintAssignmentService $service)
    {
    }

    public function store(AssignComplaintWorkUnitsRequest $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validated();

        $complaint = $this->service->assign(
            $request->user(),
            $complaint,
            $validated['work_unit_ids'],
            $validated['note'] ?? null
        );

        return $this->successResponse(
            (new TechnicalComplaintResource($complaint))->resolve($request),
            'The complaint was assigned to the work units successfully.'
        );
    }
}
