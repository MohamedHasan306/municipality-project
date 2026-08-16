<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequests\ServiceRequestResource;
use App\Http\Traits\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Services\ServiceRequests\ServiceRequestService;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalOfficeServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceRequestWorkflowService $workflowService,
        private readonly ServiceRequestService $serviceRequestService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string'],
        ]);

        $serviceRequests = $this->workflowService->paginateForTechnicalOffice(
            $request->user(),
            $request->string('status')->toString() ?: null,
            min(max((int) $request->input('per_page', 15), 1), 50)
        );

        return $this->paginatedResponse($request, $serviceRequests);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->showForEmployee(
            $request->user(),
            $serviceRequest,
            'technical_office'
        );

        return $this->resourceResponse($request, $serviceRequest, 'Service request retrieved successfully.');
    }

    public function startReview(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->startReview($request->user(), $serviceRequest);

        return $this->resourceResponse($request, $serviceRequest, 'Service request review started successfully.');
    }

    public function forwardToEngineering(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->forwardToEngineering(
            $request->user(),
            $serviceRequest
        );

        return $this->resourceResponse(
            $request,
            $serviceRequest,
            'Service request forwarded to the engineering office successfully.'
        );
    }

    public function reject(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->rejectByTechnicalOffice(
            $request->user(),
            $serviceRequest
        );

        return $this->resourceResponse($request, $serviceRequest, 'Service request rejected successfully.');
    }

    public function downloadAttachment(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): StreamedResponse {
        return $this->serviceRequestService->downloadAttachmentForEmployee(
            $request->user(),
            $serviceRequest,
            $attachment,
            'technical_office'
        );
    }

    private function resourceResponse(
        Request $request,
        ServiceRequest $serviceRequest,
        string $message
    ): JsonResponse {
        return $this->successResponse(
            (new ServiceRequestResource($serviceRequest))->resolve($request),
            $message
        );
    }

    private function paginatedResponse(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->successResponse([
            'items' => ServiceRequestResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Service requests retrieved successfully.');
    }
}
