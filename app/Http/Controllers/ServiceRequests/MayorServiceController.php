<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequests\ServiceRequestResource;
use App\Http\Traits\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Services\ServiceRequests\ServiceDocumentService;
use App\Services\ServiceRequests\ServiceRequestService;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MayorServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceRequestWorkflowService $workflowService,
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceDocumentService $documentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $serviceRequests = $this->workflowService->paginateForMayor(
            $request->user(),
            min(max((int) $request->input('per_page', 15), 1), 50)
        );

        return $this->successResponse([
            'items' => ServiceRequestResource::collection($serviceRequests->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $serviceRequests->currentPage(),
                'last_page' => $serviceRequests->lastPage(),
                'per_page' => $serviceRequests->perPage(),
                'total' => $serviceRequests->total(),
            ],
        ], 'Service requests retrieved successfully.');
    }

    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->showForEmployee(
            $request->user(),
            $serviceRequest,
            'mayor'
        );

        return $this->resourceResponse($request, $serviceRequest, 'Service request retrieved successfully.');
    }

    public function reject(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->workflowService->rejectByMayor(
            $request->user(),
            $serviceRequest
        );

        return $this->resourceResponse($request, $serviceRequest, 'Service request rejected successfully.');
    }

    public function approveAndIssue(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $serviceRequest = $this->documentService->approveAndIssue(
            $request->user(),
            $serviceRequest,
            $validated['expires_at']
        );

        return $this->resourceResponse(
            $request,
            $serviceRequest,
            'Service request approved and the signed document was issued successfully.'
        );
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
            'mayor'
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
}
