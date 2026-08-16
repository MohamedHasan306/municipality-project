<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\StoreServiceRequestDraftRequest;
use App\Http\Requests\ServiceRequests\UpdateServiceRequestDraftRequest;
use App\Http\Resources\ServiceRequests\ServiceRequestResource;
use App\Http\Resources\ServiceRequests\ServiceTypeResource;
use App\Http\Traits\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Models\ServiceType;
use App\Services\ServiceRequests\ServiceDocumentService;
use App\Services\ServiceRequests\ServiceRequestService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CitizenServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceDocumentService $documentService
    ) {
    }

    public function indexServices(Request $request): JsonResponse
    {
        $services = $this->serviceRequestService->paginateAvailableServices(
            $request->user(),
            $this->perPage($request)
        );

        return $this->successResponse([
            'items' => ServiceTypeResource::collection($services->getCollection())->resolve($request),
            'pagination' => $this->pagination($services),
        ], 'Municipal services retrieved successfully.');
    }

    public function showService(Request $request, ServiceType $serviceType): JsonResponse
    {
        $serviceType = $this->serviceRequestService->showAvailableService(
            $request->user(),
            $serviceType
        );

        return $this->successResponse(
            (new ServiceTypeResource($serviceType))->resolve($request),
            'Municipal service retrieved successfully.'
        );
    }

    public function indexRequests(Request $request): JsonResponse
    {
        $serviceRequests = $this->serviceRequestService->paginateForCitizen(
            $request->user(),
            $this->perPage($request)
        );

        return $this->successResponse([
            'items' => ServiceRequestResource::collection($serviceRequests->getCollection())->resolve($request),
            'pagination' => $this->pagination($serviceRequests),
        ], 'Service requests retrieved successfully.');
    }

    public function storeDraft(StoreServiceRequestDraftRequest $request): JsonResponse
    {
        $serviceRequest = $this->serviceRequestService->createDraft(
            $request->user(),
            (int) $request->validated('service_type_version_id'),
            $request->validated('data', [])
        );

        return $this->successResponse(
            (new ServiceRequestResource($serviceRequest))->resolve($request),
            'Service request draft created successfully.',
            201
        );
    }

    public function showRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->serviceRequestService->showForCitizen(
            $request->user(),
            $serviceRequest
        );

        return $this->successResponse(
            (new ServiceRequestResource($serviceRequest))->resolve($request),
            'Service request retrieved successfully.'
        );
    }

    public function updateDraft(
        UpdateServiceRequestDraftRequest $request,
        ServiceRequest $serviceRequest
    ): JsonResponse {
        $serviceRequest = $this->serviceRequestService->updateDraft(
            $request->user(),
            $serviceRequest,
            $request->validated('data')
        );

        return $this->successResponse(
            (new ServiceRequestResource($serviceRequest))->resolve($request),
            'Service request draft updated successfully.'
        );
    }

    public function destroyDraft(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->serviceRequestService->deleteDraft($request->user(), $serviceRequest);

        return $this->successResponse(null, 'Service request draft deleted successfully.');
    }

    public function storeAttachment(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', ServiceRequestService::ATTACHMENT_MIMES),
                'max:'.ServiceRequestService::ATTACHMENT_MAX_SIZE_KB,
            ],
        ]);

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded attachment is invalid.'],
            ]);
        }

        $attachment = $this->serviceRequestService->storeAttachment(
            $request->user(),
            $serviceRequest,
            $validated['field_key'],
            $file
        );

        return $this->successResponse([
            'id' => $attachment->id,
            'field_key' => $attachment->field_key,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
        ], 'Attachment uploaded successfully.', 201);
    }

    public function destroyAttachment(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): JsonResponse {
        $this->serviceRequestService->deleteAttachment(
            $request->user(),
            $serviceRequest,
            $attachment
        );

        return $this->successResponse(null, 'Attachment deleted successfully.');
    }

    public function downloadAttachment(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): StreamedResponse {
        return $this->serviceRequestService->downloadAttachmentForCitizen(
            $request->user(),
            $serviceRequest,
            $attachment
        );
    }

    public function submit(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest = $this->serviceRequestService->submit(
            $request->user(),
            $serviceRequest
        );

        return $this->successResponse(
            (new ServiceRequestResource($serviceRequest))->resolve($request),
            'Service request submitted successfully.'
        );
    }

    public function downloadDocument(
        Request $request,
        ServiceRequest $serviceRequest
    ): StreamedResponse {
        return $this->documentService->downloadForCitizen(
            $request->user(),
            $serviceRequest
        );
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 50);
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
