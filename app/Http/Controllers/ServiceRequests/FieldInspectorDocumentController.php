<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\ServiceRequests\ServiceDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldInspectorDocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceDocumentService $documentService
    ) {
    }

    public function verify(Request $request, string $verificationCode): JsonResponse
    {
        return $this->successResponse(
            $this->documentService->verifyByCode($request->user(), $verificationCode),
            'Document verification completed successfully.'
        );
    }

    public function file(Request $request, string $verificationCode): StreamedResponse
    {
        return $this->documentService->streamForInspector(
            $request->user(),
            $verificationCode
        );
    }
}
