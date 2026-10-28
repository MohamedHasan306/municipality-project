<?php

namespace App\Services\ServiceRequests;

use App\Models\CitizenProfile;
use App\Models\ServiceFormField;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Models\ServiceStatus;
use App\Models\ServiceType;
use App\Models\ServiceTypeVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ServiceRequestService
{
    public const ATTACHMENT_MAX_SIZE_KB = 10240;

    public const ATTACHMENT_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const DISK = 'local';

    public function __construct(
        private readonly ServiceRequestWorkflowService $workflowService
    ) {
    }

    public function paginateAvailableServices(User $actor, int $perPage): LengthAwarePaginator
    {
        $citizenProfile = $this->citizenProfile($actor);
        $this->assertCitizenPermission($actor, 'submit service request');

        return ServiceType::query()
            ->with(['municipality', 'activeVersion.fields'])
            ->where('municipality_id', $citizenProfile->municipality_id)
            ->where('is_active', true)
            ->whereHas('activeVersion')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function showAvailableService(User $actor, ServiceType $serviceType): ServiceType
    {
        $citizenProfile = $this->citizenProfile($actor);
        $this->assertCitizenPermission($actor, 'submit service request');

        abort_unless(
            (int) $serviceType->municipality_id === (int) $citizenProfile->municipality_id
                && $serviceType->is_active,
            404,
            'The requested municipal service was not found.'
        );

        $serviceType->load(['municipality', 'activeVersion.fields']);

        abort_if(
            $serviceType->activeVersion === null,
            404,
            'The requested municipal service does not have an active version.'
        );

        return $serviceType;
    }

    public function paginateForCitizen(User $actor, int $perPage): LengthAwarePaginator
    {
        $citizenProfile = $this->citizenProfile($actor);
        $this->assertCitizenPermission($actor, 'view own service requests');

        return ServiceRequest::query()
            ->with($this->citizenRelations())
            ->where('citizen_profile_id', $citizenProfile->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function createDraft(User $actor, int $serviceTypeVersionId, array $data): ServiceRequest
    {
        $this->assertCitizenPermission($actor, 'submit service request');
        $citizenProfile = $this->verifiedCitizenProfile($actor);

        $versionReference = ServiceTypeVersion::query()
            ->select(['id', 'service_type_id'])
            ->findOrFail($serviceTypeVersionId);

        return DB::transaction(function () use (
            $actor,
            $citizenProfile,
            $versionReference,
            $serviceTypeVersionId,
            $data
        ): ServiceRequest {
            $serviceType = ServiceType::query()
                ->lockForUpdate()
                ->findOrFail($versionReference->service_type_id);

            $version = ServiceTypeVersion::query()
                ->where('service_type_id', $serviceType->id)
                ->lockForUpdate()
                ->findOrFail($serviceTypeVersionId);

            $version->load('fields');

            if (! $version->is_active
                || ! $serviceType->is_active
                || (int) $serviceType->municipality_id !== (int) $citizenProfile->municipality_id) {
                throw ValidationException::withMessages([
                    'service_type_version_id' => [
                        'The selected service version is not available for new requests.',
                    ],
                ]);
            }

            $validatedData = $this->validateDraftData($version->fields, $data);
            $draftStatus = ServiceStatus::query()
                ->where('code', ServiceStatus::DRAFT)
                ->firstOrFail();

            $serviceRequest = ServiceRequest::query()->create([
                'citizen_profile_id' => $citizenProfile->id,
                'service_type_version_id' => $version->id,
                'current_status_id' => $draftStatus->id,
                'data_json' => $validatedData,
                'submitted_at' => null,
            ]);

            $this->workflowService->recordInitialDraftLocked($serviceRequest, $actor);

            return $serviceRequest->load($this->citizenRelations());
        }, 3);
    }

    public function showForCitizen(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        $this->assertCitizenPermission($actor, 'view own service requests');
        $this->assertCitizenOwnership($actor, $serviceRequest);

        return $serviceRequest->load($this->citizenRelations());
    }

    public function updateDraft(User $actor, ServiceRequest $serviceRequest, array $data): ServiceRequest
    {
        $this->assertCitizenPermission($actor, 'manage own service request drafts');
        $this->verifiedCitizenProfile($actor);

        return DB::transaction(function () use ($actor, $serviceRequest, $data): ServiceRequest {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            $this->assertCitizenOwnership($actor, $lockedRequest);
            $this->assertDraft($lockedRequest);

            $lockedRequest->load('serviceTypeVersion.fields');

            $mergedData = array_replace($lockedRequest->data_json ?? [], $data);
            $lockedRequest->data_json = $this->validateDraftData(
                $lockedRequest->serviceTypeVersion->fields,
                $mergedData
            );
            $lockedRequest->save();

            return $lockedRequest->fresh($this->citizenRelations());
        }, 3);
    }

    public function deleteDraft(User $actor, ServiceRequest $serviceRequest): void
    {
        $this->assertCitizenPermission($actor, 'manage own service request drafts');
        $this->verifiedCitizenProfile($actor);

        $paths = DB::transaction(function () use ($actor, $serviceRequest): array {
            $lockedRequest = ServiceRequest::query()
                ->with('attachments')
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            $this->assertCitizenOwnership($actor, $lockedRequest);
            $this->assertDraft($lockedRequest);

            $paths = $lockedRequest->attachments->pluck('file_path')->all();
            $lockedRequest->delete();

            return $paths;
        }, 3);

        foreach ($paths as $path) {
            if (! Storage::disk(self::DISK)->delete($path)) {
                Log::warning('A service request attachment file could not be deleted.', [
                    'service_request_id' => $serviceRequest->id,
                    'file_path' => $path,
                ]);
            }
        }
    }

    public function storeAttachment(
        User $actor,
        ServiceRequest $serviceRequest,
        string $fieldKey,
        UploadedFile $file
    ): ServiceRequestAttachment {
        $this->assertCitizenPermission($actor, 'manage own service request drafts');
        $this->verifiedCitizenProfile($actor);
        $this->assertCitizenOwnership($actor, $serviceRequest);
        $this->assertDraft($serviceRequest);

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded attachment is invalid.'],
            ]);
        }

        if (mb_strlen($file->getClientOriginalName()) > 255) {
            throw ValidationException::withMessages([
                'file' => ['The original attachment name must not exceed 255 characters.'],
            ]);
        }

        $serviceRequest->load('serviceTypeVersion.fields');
        $this->assertFileField($serviceRequest->serviceTypeVersion->fields, $fieldKey);

        $newPath = $file->store("service-request-attachments/{$serviceRequest->id}", self::DISK);

        if (! is_string($newPath)) {
            throw new RuntimeException('The attachment could not be stored.');
        }

        $oldPath = null;

        try {
            $attachment = DB::transaction(function () use (
                $actor,
                $serviceRequest,
                $fieldKey,
                $file,
                $newPath,
                &$oldPath
            ): ServiceRequestAttachment {
                $lockedRequest = ServiceRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($serviceRequest->id);

                $this->assertCitizenOwnership($actor, $lockedRequest);
                $this->assertDraft($lockedRequest);

                $lockedRequest->load('serviceTypeVersion.fields');
                $this->assertFileField($lockedRequest->serviceTypeVersion->fields, $fieldKey);

                $existingAttachment = $lockedRequest->attachments()
                    ->where('field_key', $fieldKey)
                    ->lockForUpdate()
                    ->first();

                $oldPath = $existingAttachment?->file_path;

                return $lockedRequest->attachments()->updateOrCreate(
                    ['field_key' => $fieldKey],
                    [
                        'file_path' => $newPath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                        'file_size' => (int) $file->getSize(),
                    ]
                );
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPath);
            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            if (! Storage::disk(self::DISK)->delete($oldPath)) {
                Log::warning('A replaced service request attachment file could not be deleted.', [
                    'service_request_id' => $serviceRequest->id,
                    'file_path' => $oldPath,
                ]);
            }
        }

        return $attachment;
    }

    public function deleteAttachment(
        User $actor,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): void {
        $this->assertCitizenPermission($actor, 'manage own service request drafts');
        $this->verifiedCitizenProfile($actor);

        $path = DB::transaction(function () use ($actor, $serviceRequest, $attachment): string {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            $this->assertCitizenOwnership($actor, $lockedRequest);
            $this->assertDraft($lockedRequest);

            $lockedAttachment = ServiceRequestAttachment::query()
                ->where('service_request_id', $lockedRequest->id)
                ->whereKey($attachment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $path = $lockedAttachment->file_path;
            $lockedAttachment->delete();

            return $path;
        }, 3);

        if (! Storage::disk(self::DISK)->delete($path)) {
            Log::warning('A service request attachment file could not be deleted.', [
                'service_request_id' => $serviceRequest->id,
                'attachment_id' => $attachment->id,
                'file_path' => $path,
            ]);
        }
    }

    public function downloadAttachmentForCitizen(
        User $actor,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): StreamedResponse {
        $this->assertCitizenPermission($actor, 'view own service requests');
        $this->assertCitizenOwnership($actor, $serviceRequest);

        return $this->streamAttachment($serviceRequest, $attachment);
    }

    public function downloadAttachmentForEmployee(
        User $actor,
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment,
        string $requiredRole
    ): StreamedResponse {
        $this->workflowService->assertEmployeeCanView($actor, $serviceRequest, $requiredRole);

        return $this->streamAttachment($serviceRequest, $attachment);
    }

    public function submit(User $actor, ServiceRequest $serviceRequest): ServiceRequest
    {
        $this->assertCitizenPermission($actor, 'submit service request');
        $this->verifiedCitizenProfile($actor);

        return DB::transaction(function () use ($actor, $serviceRequest): ServiceRequest {
            $lockedRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            $this->assertCitizenOwnership($actor, $lockedRequest);
            $this->assertDraft($lockedRequest);

            $lockedRequest->load([
                'serviceTypeVersion.fields',
                'attachments',
                'currentStatus',
                'citizenProfile',
                'serviceTypeVersion.serviceType',
            ]);

            if (! $lockedRequest->citizenProfile->is_verified) {
                throw ValidationException::withMessages([
                    'citizen' => ['The citizen profile must be verified before submission.'],
                ]);
            }

            $this->validateForSubmit($lockedRequest);

            return $this->workflowService->transitionLocked(
                $lockedRequest,
                ServiceStatus::SUBMITTED,
                $actor
            );
        }, 3);
    }

    public function assertCitizenOwnership(User $actor, ServiceRequest $serviceRequest): void
    {
        $citizenProfile = $this->citizenProfile($actor);

        abort_unless(
            (int) $citizenProfile->id === (int) $serviceRequest->citizen_profile_id,
            403,
            'You do not own this service request.'
        );
    }

    private function validateDraftData(EloquentCollection $fields, array $data): array
    {
        $this->assertKnownNonFileKeys($fields, $data);

        $rules = [];

        foreach ($fields as $field) {
            if ($field->field_type === ServiceFormField::FILE
                || ! array_key_exists($field->field_key, $data)) {
                continue;
            }

            $rules[$field->field_key] = [
                'sometimes',
                'nullable',
                ...$this->fieldValueRules($field),
            ];
        }

        return Validator::make($data, $rules)->validate();
    }

    private function validateForSubmit(ServiceRequest $serviceRequest): void
    {
        $fields = $serviceRequest->serviceTypeVersion->fields;
        $data = $serviceRequest->data_json ?? [];

        $this->assertKnownNonFileKeys($fields, $data);

        $rules = [];

        foreach ($fields as $field) {
            if ($field->field_type === ServiceFormField::FILE) {
                continue;
            }

            $rules[$field->field_key] = [
                $field->is_required ? 'required' : 'nullable',
                ...$this->fieldValueRules($field),
            ];
        }

        Validator::make($data, $rules)->validate();

        $fileFields = $fields
            ->where('field_type', ServiceFormField::FILE)
            ->keyBy('field_key');
        $attachments = $serviceRequest->attachments->keyBy('field_key');

        $unknownAttachmentKeys = $attachments->keys()->diff($fileFields->keys());

        if ($unknownAttachmentKeys->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attachments' => [
                    'One or more attachments do not belong to a file field in the pinned service version.',
                ],
            ]);
        }

        $missingRequiredFiles = $fileFields
            ->filter(fn (ServiceFormField $field) => $field->is_required)
            ->keys()
            ->diff($attachments->keys())
            ->values()
            ->all();

        if ($missingRequiredFiles !== []) {
            throw ValidationException::withMessages([
                'attachments' => [
                    'Required attachments are missing for: '.implode(', ', $missingRequiredFiles).'.',
                ],
            ]);
        }

        foreach ($attachments as $attachment) {
            if (! Storage::disk(self::DISK)->exists($attachment->file_path)) {
                throw ValidationException::withMessages([
                    "attachments.{$attachment->field_key}" => [
                        'The stored attachment file is missing.',
                    ],
                ]);
            }

            $actualSize = Storage::disk(self::DISK)->size($attachment->file_path);

            if ($actualSize > self::ATTACHMENT_MAX_SIZE_KB * 1024) {
                throw ValidationException::withMessages([
                    "attachments.{$attachment->field_key}" => [
                        'The stored attachment exceeds the maximum allowed size.',
                    ],
                ]);
            }
        }
    }

    private function assertKnownNonFileKeys(EloquentCollection $fields, array $data): void
    {
        $fieldsByKey = $fields->keyBy('field_key');
        $unknownKeys = array_values(array_diff(array_keys($data), $fieldsByKey->keys()->all()));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'data' => ['Unknown service form fields: '.implode(', ', $unknownKeys).'.'],
            ]);
        }

        $fileKeysInData = array_values(array_filter(
            array_keys($data),
            fn (string $key): bool => $fieldsByKey->get($key)?->field_type === ServiceFormField::FILE
        ));

        if ($fileKeysInData !== []) {
            throw ValidationException::withMessages([
                'data' => [
                    'File fields must be uploaded as attachments and cannot be stored in data: '
                    .implode(', ', $fileKeysInData).'.',
                ],
            ]);
        }
    }

    private function fieldValueRules(ServiceFormField $field): array
    {
        $rules = [match ($field->field_type) {
            ServiceFormField::TEXT,
            ServiceFormField::TEXTAREA,
            ServiceFormField::SELECT,
            ServiceFormField::RADIO => 'string',
            ServiceFormField::NUMBER => 'numeric',
            ServiceFormField::DATE => 'date',
            ServiceFormField::CHECKBOX => 'boolean',
            default => throw new RuntimeException('Unsupported service field type.'),
        }];

        if (in_array($field->field_type, [ServiceFormField::SELECT, ServiceFormField::RADIO], true)) {
            $rules[] = Rule::in($field->options_json ?? []);
        }

        foreach ($field->validation_json ?? [] as $additionalRule) {
            if (! in_array($additionalRule, $rules, true)) {
                $rules[] = $additionalRule;
            }
        }

        return $rules;
    }

    private function assertFileField(EloquentCollection $fields, string $fieldKey): void
    {
        $field = $fields->firstWhere('field_key', $fieldKey);

        if ($field === null || $field->field_type !== ServiceFormField::FILE) {
            throw ValidationException::withMessages([
                'field_key' => ['The supplied field key is not a file field in the pinned service version.'],
            ]);
        }
    }

    private function streamAttachment(
        ServiceRequest $serviceRequest,
        ServiceRequestAttachment $attachment
    ): StreamedResponse {
        abort_unless(
            (int) $attachment->service_request_id === (int) $serviceRequest->id,
            404,
            'The attachment was not found for this service request.'
        );

        abort_unless(
            Storage::disk(self::DISK)->exists($attachment->file_path),
            404,
            'The attachment file was not found.'
        );

        return Storage::disk(self::DISK)->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    private function assertDraft(ServiceRequest $serviceRequest): void
    {
        $serviceRequest->loadMissing('currentStatus');

        if ($serviceRequest->currentStatus?->code !== ServiceStatus::DRAFT) {
            throw new ConflictHttpException('Only draft service requests can be modified.');
        }
    }

    private function verifiedCitizenProfile(User $actor): CitizenProfile
    {
        $citizenProfile = $this->citizenProfile($actor);

        abort_unless(
            $citizenProfile->is_verified === true,
            403,
            'Your citizen profile must be verified before using municipal service requests.'
        );

        return $citizenProfile;
    }

    private function citizenProfile(User $actor): CitizenProfile
    {
        abort_unless($actor->hasRole('citizen'), 403, 'Only citizens may access this operation.');

        $citizenProfile = $actor->citizenProfile;
        abort_if($citizenProfile === null, 403, 'A citizen profile is required.');

        return $citizenProfile;
    }

    private function assertCitizenPermission(User $actor, string $permission): void
    {
        abort_unless($actor->can($permission), 403, 'You do not have the required permission.');
    }

    private function citizenRelations(): array
    {
        return [
            'serviceTypeVersion.serviceType.municipality',
            'serviceTypeVersion.fields',
            'currentStatus',
            'attachments',
            'generatedDocument.signature',
        ];
    }
}
