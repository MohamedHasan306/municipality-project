<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\StoreServiceTypeRequest;
use App\Http\Requests\ServiceRequests\StoreServiceTypeVersionRequest;
use App\Http\Resources\ServiceRequests\ServiceTypeResource;
use App\Http\Traits\ApiResponse;
use App\Models\ServiceType;
use App\Services\ServiceRequests\ServiceDocumentTemplate;
use App\Services\ServiceRequests\ServiceTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SystemAdminServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceTypeService $serviceTypeService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'municipality_id' => ['sometimes', 'integer', 'exists:municipalities,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_active', $validated)) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $serviceTypes = $this->serviceTypeService->paginate(
            $request->user(),
            $validated,
            min(max((int) $request->input('per_page', 15), 1), 50)
        );

        return $this->successResponse([
            'items' => ServiceTypeResource::collection($serviceTypes->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $serviceTypes->currentPage(),
                'last_page' => $serviceTypes->lastPage(),
                'per_page' => $serviceTypes->perPage(),
                'total' => $serviceTypes->total(),
            ],
        ], 'Service types retrieved successfully.');
    }

    public function store(StoreServiceTypeRequest $request): JsonResponse
    {
        $serviceType = $this->serviceTypeService->create(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            (new ServiceTypeResource($serviceType))->resolve($request),
            'Service type created successfully.',
            201
        );
    }

    public function show(Request $request, ServiceType $serviceType): JsonResponse
    {
        $serviceType = $this->serviceTypeService->show($request->user(), $serviceType);

        return $this->successResponse(
            (new ServiceTypeResource($serviceType))->resolve($request),
            'Service type retrieved successfully.'
        );
    }

    public function update(Request $request, ServiceType $serviceType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'document_template_key' => [
                'sometimes',
                'string',
                Rule::in([ServiceDocumentTemplate::KEY]),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'request' => ['At least one service type metadata field must be supplied.'],
            ]);
        }

        $serviceType = $this->serviceTypeService->update(
            $request->user(),
            $serviceType,
            $validated
        );

        return $this->successResponse(
            (new ServiceTypeResource($serviceType))->resolve($request),
            'Service type updated successfully.'
        );
    }

    public function storeVersion(
        StoreServiceTypeVersionRequest $request,
        ServiceType $serviceType
    ): JsonResponse {
        $version = $this->serviceTypeService->createVersion(
            $request->user(),
            $serviceType,
            $request->validated('fields')
        );

        $serviceType = $version->serviceType->load(['municipality', 'activeVersion.fields']);

        return $this->successResponse(
            (new ServiceTypeResource($serviceType))->resolve($request),
            'Service type version created and activated successfully.',
            201
        );
    }
}
