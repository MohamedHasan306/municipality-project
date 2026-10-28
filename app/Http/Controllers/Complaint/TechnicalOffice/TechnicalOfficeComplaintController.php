<?php

namespace App\Http\Controllers\Complaint\TechnicalOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicalOffice\TechnicalComplaintIndexRequest;
use App\Http\Resources\Complaints\TechnicalComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\Complaint;
use App\Services\Complaints\TechnicalOfficeComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicalOfficeComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TechnicalOfficeComplaintService $service)
    {
    }

    public function index(TechnicalComplaintIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $complaints = $this->service->paginate(
            $request->user(),
            $validated,
            $perPage
        );

        return $this->successResponse([
            'items' => TechnicalComplaintResource::collection(
                $complaints->getCollection()
            )->resolve($request),

            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
        ], 'The unified complaints were retrieved successfully.');
    }

    public function reject(Request $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->service->reject(
            $request->user(),
            $complaint
        );

        return $this->successResponse(
            (new TechnicalComplaintResource($complaint))->resolve($request),
            'The unified complaint was rejected successfully.'
        );
    }
}
