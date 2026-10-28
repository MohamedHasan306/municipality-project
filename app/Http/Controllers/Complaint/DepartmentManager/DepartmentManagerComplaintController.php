<?php

namespace App\Http\Controllers\Complaint\DepartmentManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentManager\ResolveComplaintRequest;
use App\Http\Requests\DepartmentManager\StartComplaintRequest;
use App\Http\Resources\Complaints\DepartmentManagerComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\Complaint;
use App\Services\Complaints\DepartmentManagerComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentManagerComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DepartmentManagerComplaintService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $complaints = $this->service->paginate(
            $request->user(),
            $perPage
        );

        return $this->successResponse([
            'items' => DepartmentManagerComplaintResource::collection(
                $complaints->getCollection()
            )->resolve($request),

            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
        ], 'The department complaints were retrieved successfully.');
    }

    public function show(Request $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->service->show(
            $request->user(),
            $complaint
        );

        return $this->successResponse(
            (new DepartmentManagerComplaintResource($complaint))->resolve($request),
            'The complaint was retrieved successfully.'
        );
    }

    public function start(StartComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->service->start(
            $request->user(),
            $complaint,
            $request->validated('note')
        );

        return $this->successResponse(
            (new DepartmentManagerComplaintResource($complaint))->resolve($request),
            'Work on the complaint has started successfully.'
        );
    }

    public function resolve(ResolveComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->service->resolve(
            $request->user(),
            $complaint,
            $request->validated('note')
        );

        return $this->successResponse(
            (new DepartmentManagerComplaintResource($complaint))->resolve($request),
            'The complaint was resolved successfully.'
        );
    }

    public function reject(Request $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->service->reject(
            $request->user(),
            $complaint
        );

        return $this->successResponse(
            (new DepartmentManagerComplaintResource($complaint))->resolve($request),
            'The complaint was rejected successfully.'
        );
    }
}
