<?php

namespace App\Http\Controllers\Complaint;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\StoreComplaintDraftRequest;
use App\Http\Requests\Complaint\SubmitComplaintReportRequest;
use App\Http\Requests\Complaint\UpdateComplaintDraftRequest;
use App\Http\Requests\Complaint\UploadComplaintReportImagesRequest;
use App\Http\Resources\Complaints\ComplaintReportImageResource;
use App\Http\Resources\Complaints\ComplaintReportResource;
use App\Http\Traits\ApiResponse;
use App\Models\ComplaintReport;
use App\Models\ComplaintReportImage;
use App\Services\Complaints\ComplaintReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ComplaintReportService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ComplaintReport::class);

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            50
        );

        $reports = $this->service->paginateForCitizen(
            $request->user(),
            $perPage
        );

        return $this->successResponse(
            [
                'items' => ComplaintReportResource::collection(
                    $reports->getCollection()
                )->resolve($request),

                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
            ],
            'تم جلب شكاوى المواطن بنجاح.'
        );
    }

    public function store(StoreComplaintDraftRequest $request): JsonResponse {

        Gate::authorize('create', ComplaintReport::class);

        $report = $this->service->createDraft(
            $request->user(),
            $request->validated()
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))
                ->resolve($request),
            'تم إنشاء مسودة الشكوى بنجاح.',
            201
        );
    }

    public function show(
        Request $request,
        ComplaintReport $report
    ): JsonResponse {
        Gate::authorize('view', $report);

        $report = $this->service->showForCitizen(
            $request->user(),
            $report
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))
                ->resolve($request),
            'تم جلب الشكوى بنجاح.'
        );
    }

    public function update(
        UpdateComplaintDraftRequest $request,
        ComplaintReport $report
    ): JsonResponse {
        Gate::authorize('update', $report);
        $report = $this->service->updateDraft(
            $request->user(),
            $report,
            $request->validated()
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))
                ->resolve($request),
            'تم تحديث مسودة الشكوى بنجاح.'
        );
    }

    public function destroy(Request $request, ComplaintReport $report): JsonResponse {
        Gate::authorize('delete', $report);

        $this->service->deleteDraft(
            $request->user(),
            $report
        );

        return $this->successResponse(
            null,
            'تم حذف مسودة الشكوى بنجاح.'
        );
    }

    public function uploadImages(UploadComplaintReportImagesRequest $request, ComplaintReport $report): JsonResponse {
        $images = $this->service->uploadImages(
            $request->user(),
            $report,
            $request->file('images', [])
        );

        return $this->successResponse(
            ComplaintReportImageResource::collection(
                $images
            )->resolve($request),
            'تم رفع صور الشكوى بنجاح.',
            201
        );
    }

    public function destroyImage(
        Request $request,
        ComplaintReport $report,
        ComplaintReportImage $image
    ): JsonResponse {
        Gate::authorize('deleteImage', $report);

        $this->service->deleteImage(
            $request->user(),
            $report,
            $image
        );

        return $this->successResponse(
            null,
            'تم حذف صورة الشكوى بنجاح.'
        );
    }


    public function submit(
        SubmitComplaintReportRequest $request,
        ComplaintReport $report
    ): JsonResponse {
        $report = $this->service->submit(
            $request->user(),
            $report
        );

        return $this->successResponse(
            (new ComplaintReportResource($report))
                ->resolve($request),
            'تم إرسال الشكوى بنجاح.'
        );
    }
}
