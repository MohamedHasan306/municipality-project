<?php

namespace App\Http\Controllers\Complaint\TechnicalOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicalOffice\CreateUnifiedComplaintRequest;
use App\Http\Requests\TechnicalOffice\MergeComplaintReportRequest;
use App\Http\Resources\Complaints\ComplaintReportResource;
use App\Http\Resources\Complaints\TechnicalComplaintResource;
use App\Http\Traits\ApiResponse;
use App\Models\ComplaintReport;
use App\Services\Complaints\ComplaintReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintReviewController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ComplaintReviewService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            50
        );

        $reports = $this->service->paginatePendingReports(
            $request->user(),
            $perPage
        );

        return $this->successResponse([
            'items' => ComplaintReportResource::collection(
                $reports->getCollection()
            )->resolve($request),

            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ], 'New complaint reports were retrieved successfully.');
    }

    public function show(Request $request, ComplaintReport $report): JsonResponse
    {
        $report = $this->service->showPendingReport(
            $request->user(),
            $report
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))->resolve($request),
            'The complaint report was retrieved successfully.'
        );
    }

    public function similar(Request $request, ComplaintReport $report): JsonResponse
    {
        $complaints = $this->service->findSimilarComplaints(
            $request->user(),
            $report
        );

        return $this->successResponse(
            TechnicalComplaintResource::collection($complaints)->resolve($request),
            'Similar complaints were retrieved successfully.'
        );
    }

    public function createUnified(CreateUnifiedComplaintRequest $request, ComplaintReport $report): JsonResponse
    {
        $complaint = $this->service->createUnifiedComplaint(
            $request->user(),
            $report,
            $request->validated()
        );

        return $this->successResponse(
            (new TechnicalComplaintResource($complaint))->resolve($request),
            'The unified complaint was created successfully.',
            201
        );
    }

    public function merge(MergeComplaintReportRequest $request, ComplaintReport $report): JsonResponse
    {
        $complaint = $this->service->mergeWithComplaint(
            $request->user(),
            $report,
            (int) $request->validated('complaint_id')
        );

        return $this->successResponse(
            (new TechnicalComplaintResource($complaint))->resolve($request),
            'The report was merged with the complaint successfully.'
        );
    }

    public function reject(Request $request, ComplaintReport $report): JsonResponse
    {
        $report = $this->service->rejectReport(
            $request->user(),
            $report
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))->resolve($request),
            'The complaint report was rejected successfully.'
        );
    }
}
