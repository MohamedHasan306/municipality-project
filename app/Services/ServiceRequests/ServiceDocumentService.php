<?php

namespace App\Services\ServiceRequests;

use App\Models\DocumentSignature;
use App\Models\GeneratedDocument;
use App\Models\ServiceRequest;
use App\Models\ServiceStatus;
use App\Models\User;

use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ServiceDocumentService
{
    public function __construct(
        private readonly ServiceRequestWorkflowService $workflowService
    ) {
    }

    public function approveAndIssue(
        User $actor,
        ServiceRequest $serviceRequest,
        string $expiresAtValue
    ): ServiceRequest {
        $this->assertMayorPermissions($actor);

        $expiresAt = CarbonImmutable::parse($expiresAtValue);

        if ($expiresAt->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw new ConflictHttpException('The document expiration date must be in the future.');
        }

        $storedPath = null;
        $disk = (string) config('document_signing.disk', 'local');

        try {
            return DB::transaction(function () use (
                $actor,
                $serviceRequest,
                $expiresAt,
                $disk,
                &$storedPath
            ): ServiceRequest {
                $lockedRequest = ServiceRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($serviceRequest->id);

                $lockedRequest->load([
                    'currentStatus',
                    'citizenProfile.user',
                    'citizenProfile.municipality',
                    'serviceTypeVersion.serviceType.municipality',
                    'serviceTypeVersion.fields',
                    'attachments',
                    'generatedDocument',
                ]);

                $this->workflowService->assertEmployeeCanAccess(
                    $actor,
                    $lockedRequest,
                    'mayor',
                    'mayor approve service requests'
                );

                if ($lockedRequest->currentStatus?->code !== ServiceStatus::PENDING_MAYOR_APPROVAL) {
                    throw new ConflictHttpException('Only requests awaiting mayor approval can be issued.');
                }

                if ($lockedRequest->generatedDocument !== null
                    || GeneratedDocument::query()->where('service_request_id', $lockedRequest->id)->exists()) {
                    throw new ConflictHttpException('A document has already been issued for this service request.');
                }

                $documentNumber = $this->generateUniqueDocumentNumber($disk, $lockedRequest->id);
                $verificationCode = $this->generateUniqueVerificationCode();
                $issuedAt = CarbonImmutable::now();
                $signedAt = $issuedAt;
                $verificationPath = route(
                    (string) config('document_signing.verification_route_name'),
                    ['verificationCode' => $verificationCode],
                    false
                );
                $verificationUrl = rtrim((string) config('app.url'), '/')
                    .'/'.ltrim($verificationPath, '/');

                $verificationQrCodeDataUri = $this->generateQrCodeDataUri($verificationUrl);

                $filePath = route(
                    'field-inspector.documents.file',
                    ['verificationCode' => $verificationCode],
                    false
                );

                $fileUrl = rtrim((string) config('app.url'), '/')
                    .'/'.ltrim($filePath, '/');

                $fileQrCodeDataUri = $this->generateQrCodeDataUri($fileUrl);

                $pdfBytes = $this->renderPdf(
                    $lockedRequest,
                    $actor,
                    $documentNumber,
                    $verificationCode,
                    $verificationUrl,
                    $verificationQrCodeDataUri,
                    $fileUrl,
                    $fileQrCodeDataUri,
                    $issuedAt,
                    $expiresAt,
                    $signedAt
                );

                $documentHash = hash('sha256', $pdfBytes);
                $signatureValue = $this->signHash($documentHash);

                if (! $this->verifySignature($documentHash, $signatureValue)) {
                    throw new RuntimeException('The generated document signature could not be verified.');
                }

                $directory = trim(
                    (string) config('document_signing.directory', 'generated-documents/service-requests'),
                    '/'
                );
                $storedPath = "{$directory}/{$documentNumber}.pdf";

                if (! Storage::disk($disk)->put($storedPath, $pdfBytes)) {
                    throw new RuntimeException('The generated PDF could not be stored.');
                }

                $storedHash = hash('sha256', Storage::disk($disk)->get($storedPath));

                if (! hash_equals($documentHash, $storedHash)
                    || ! $this->verifySignature($storedHash, $signatureValue)) {
                    throw new RuntimeException('The stored PDF failed integrity verification.');
                }

                $generatedDocument = GeneratedDocument::query()->create([
                    'service_request_id' => $lockedRequest->id,
                    'document_number' => $documentNumber,
                    'file_path' => $storedPath,
                    'document_hash' => $documentHash,
                    'verification_code' => $verificationCode,
                    'generated_by' => $actor->id,
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                ]);

                DocumentSignature::query()->create([
                    'generated_document_id' => $generatedDocument->id,
                    'signature_value' => $signatureValue,
                    'signed_document_hash' => $documentHash,
                    'algorithm' => (string) config('document_signing.algorithm', 'RSA-SHA256'),
                    'signed_by' => $actor->id,
                    'signed_at' => $signedAt,
                    'certificate_serial' => config('document_signing.certificate_serial'),
                ]);

                return $this->workflowService->transitionLocked(
                    $lockedRequest,
                    ServiceStatus::APPROVED_AND_DOCUMENT_ISSUED,
                    $actor
                );
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)
                && Storage::disk($disk)->exists($storedPath)
                && ! Storage::disk($disk)->delete($storedPath)) {
                Log::warning('A failed service document file could not be deleted.', [
                    'file_path' => $storedPath,
                ]);
            }

            throw $exception;
        }
    }

    public function downloadForCitizen(User $actor, ServiceRequest $serviceRequest): StreamedResponse
    {
        abort_unless(
            $actor->hasRole('citizen') && $actor->can('download own issued documents'),
            403,
            'You do not have permission to download issued documents.'
        );
        abort_unless(
            (int) $actor->citizenProfile?->id === (int) $serviceRequest->citizen_profile_id,
            403,
            'You do not own this service request.'
        );

        $serviceRequest->load(['currentStatus', 'generatedDocument.signature']);

        abort_unless(
            $serviceRequest->currentStatus?->code === ServiceStatus::APPROVED_AND_DOCUMENT_ISSUED,
            404,
            'An issued document is not available for this service request.'
        );

        $document = $serviceRequest->generatedDocument;
        abort_if($document === null, 404, 'The issued document was not found.');

        $verification = $this->verifyDocument($document);

        if (! $verification['file_exists']
            || ! $verification['hash_matches']
            || ! $verification['signature_valid']) {
            throw new ConflictHttpException('The issued document failed integrity verification.');
        }

        return $this->downloadDocument($document);
    }

    public function verifyByCode(User $actor, string $verificationCode): array
    {
        $this->assertFieldInspector($actor);

        $document = GeneratedDocument::query()
            ->with([
                'signature',
                'serviceRequest.citizenProfile.user',
                'serviceRequest.serviceTypeVersion.serviceType.municipality',
            ])
            ->where('verification_code', $verificationCode)
            ->firstOrFail();

        $verification = $this->verifyDocument($document);
        $serviceRequest = $document->serviceRequest;

        return [
            'document_number' => $document->document_number,
            'service_name' => $serviceRequest->serviceTypeVersion->serviceType->name,
            'municipality' => $serviceRequest->serviceTypeVersion->serviceType->municipality->name,
            'citizen_name' => $serviceRequest->citizenProfile->user->full_name,
            'issued_at' => $document->issued_at?->toISOString(),
            'expires_at' => $document->expires_at?->toISOString(),
            'file_exists' => $verification['file_exists'],
            'is_expired' => $verification['is_expired'],
            'is_signed' => $verification['signature_exists'],
            'is_hash_valid' => $verification['hash_matches'],
            'is_signature_valid' => $verification['signature_valid'],
            'is_valid' => $verification['is_valid'],
        ];
    }

    public function streamForInspector(User $actor, string $verificationCode): StreamedResponse
    {
        $this->assertFieldInspector($actor);

        $document = GeneratedDocument::query()
            ->with('signature')
            ->where('verification_code', $verificationCode)
            ->firstOrFail();

        $verification = $this->verifyDocument($document);

        abort_unless(
            $verification['file_exists'],
            404,
            'The original PDF file was not found.'
        );

        if (! $verification['hash_matches'] || ! $verification['signature_valid']) {
            throw new ConflictHttpException('The original PDF failed integrity verification.');
        }

        $disk = (string) config('document_signing.disk', 'local');

        return Storage::disk($disk)->response(
            $document->file_path,
            "{$document->document_number}.pdf",
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->document_number.'.pdf"',
            ]
        );
    }

    public function verifyDocument(GeneratedDocument $document): array
    {
        $document->loadMissing('signature');

        $disk = (string) config('document_signing.disk', 'local');
        $fileExists = Storage::disk($disk)->exists($document->file_path);
        $currentHash = $fileExists
            ? hash('sha256', Storage::disk($disk)->get($document->file_path))
            : null;
        $hashMatches = $currentHash !== null
            && hash_equals($document->document_hash, $currentHash);
        $signatureExists = $document->signature !== null;
        $signatureValid = $signatureExists
            && $currentHash !== null
            && hash_equals($document->signature->signed_document_hash, $currentHash)
            && $this->verifySignature($currentHash, $document->signature->signature_value);
        $isExpired = $document->isExpired();

        return [
            'file_exists' => $fileExists,
            'hash_matches' => $hashMatches,
            'signature_exists' => $signatureExists,
            'signature_valid' => $signatureValid,
            'is_expired' => $isExpired,
            'is_valid' => $fileExists && $hashMatches && $signatureValid && ! $isExpired,
        ];
    }

    private function renderPdf(
        ServiceRequest $serviceRequest,
        User $mayor,
        string $documentNumber,
        string $verificationCode,
        string $verificationUrl,
        string $verificationQrCodeDataUri,
        string $fileUrl,
        string $fileQrCodeDataUri,
        CarbonImmutable $issuedAt,
        CarbonImmutable $expiresAt,
        CarbonImmutable $signedAt
    ): string {
        $serviceType = $serviceRequest->serviceTypeVersion->serviceType;

        $html = view(ServiceDocumentTemplate::VIEW, [
            'serviceRequest' => $serviceRequest,
            'serviceType' => $serviceType,
            'version' => $serviceRequest->serviceTypeVersion,
            'fields' => $serviceRequest->serviceTypeVersion->fields,
            'values' => $serviceRequest->data_json ?? [],
            'attachments' => $serviceRequest->attachments->keyBy('field_key'),
            'municipality' => $serviceType->municipality,
            'citizenProfile' => $serviceRequest->citizenProfile,
            'citizenUser' => $serviceRequest->citizenProfile->user,
            'mayor' => $mayor,
            'documentNumber' => $documentNumber,
            'verificationCode' => $verificationCode,
            'verificationUrl' => $verificationUrl,
            'qrCodeDataUri' => $verificationQrCodeDataUri,
            'fileUrl' => $fileUrl,
            'fileQrCodeDataUri' => $fileQrCodeDataUri,
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
            'signedAt' => $signedAt,
            'signatureAlgorithm' => (string) config('document_signing.algorithm', 'RSA-SHA256'),
        ])->render();

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 34,
            'margin_right' => 34,
            'margin_top' => 28,
            'margin_bottom' => 28,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $pdf->SetTitle("{$serviceType->name} - {$documentNumber}");
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }



    private function generateQrCodeDataUri(string $verificationUrl): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ],
            validateResult: false,
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 260,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        return $builder->build()->getDataUri();
    }

    private function signHash(string $documentHash): string
    {
        $privateKeyContents = $this->readKeyFile(
            (string) config('document_signing.private_key_path'),
            'private'
        );
        $passphrase = config('document_signing.private_key_passphrase');
        $privateKey = openssl_pkey_get_private(
            $privateKeyContents,
            is_string($passphrase) ? $passphrase : ''
        );

        if ($privateKey === false) {
            throw new RuntimeException('The document signing private key could not be loaded.');
        }

        $signature = '';
        $signed = openssl_sign($documentHash, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('The document hash could not be signed.');
        }

        return base64_encode($signature);
    }

    private function verifySignature(string $documentHash, string $signatureValue): bool
    {
        $decodedSignature = base64_decode($signatureValue, true);

        if ($decodedSignature === false) {
            return false;
        }

        $publicKeyContents = $this->readKeyFile(
            (string) config('document_signing.public_key_path'),
            'public'
        );
        $publicKey = openssl_pkey_get_public($publicKeyContents);

        if ($publicKey === false) {
            throw new RuntimeException('The document signing public key could not be loaded.');
        }

        return openssl_verify(
            $documentHash,
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    private function readKeyFile(string $path, string $type): string
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("The document signing {$type} key file is missing or unreadable.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("The document signing {$type} key file is empty.");
        }

        return $contents;
    }

    private function generateUniqueDocumentNumber(string $disk, int $serviceRequestId): string
    {
        $directory = trim(
            (string) config('document_signing.directory', 'generated-documents/service-requests'),
            '/'
        );

        do {
            $number = 'MSR-'.now()->format('Ymd').'-'.$serviceRequestId.'-'.strtoupper(bin2hex(random_bytes(6)));
            $databaseCollision = GeneratedDocument::query()
                ->where('document_number', $number)
                ->exists();
            $fileCollision = Storage::disk($disk)->exists("{$directory}/{$number}.pdf");
        } while ($databaseCollision || $fileCollision);

        return $number;
    }

    private function generateUniqueVerificationCode(): string
    {
        do {
            $code = bin2hex(random_bytes(32));
        } while (GeneratedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

    private function downloadDocument(GeneratedDocument $document): StreamedResponse
    {
        $disk = (string) config('document_signing.disk', 'local');

        return Storage::disk($disk)->download(
            $document->file_path,
            "{$document->document_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    private function assertMayorPermissions(User $actor): void
    {
        abort_unless(
            $actor->hasRole('mayor')
                && $actor->can('mayor approve service requests')
                && $actor->can('issue documents')
                && $actor->can('sign documents'),
            403,
            'You do not have permission to approve and issue service documents.'
        );
    }

    private function assertFieldInspector(User $actor): void
    {
        abort_unless(
            $actor->hasRole('field_inspector') && $actor->can('verify documents'),
            403,
            'Only an authorized field inspector may verify documents.'
        );

        $employeeProfile = $actor->employeeProfile;

        abort_if($employeeProfile === null, 403, 'An employee profile is required.');
        abort_unless(
            $employeeProfile->status === 'active',
            403,
            'Only active field inspectors may verify documents.'
        );
    }
}
