<?php

namespace App\Services\Complaints;

use App\Models\ComplaintCategory;
use App\Models\ComplaintReport;
use App\Models\ComplaintReportImage;
use App\Models\ComplaintStatus;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ComplaintReportService
{
    private const DISK = 'public';

    private const MAX_IMAGES = 5;

    public function __construct(private readonly ComplaintStatusTransitionService $transitionService)
    {
    }

    public function paginateForCitizen(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $citizenProfileId = $this->citizenProfileId($user);

        return ComplaintReport::query()
            ->where('citizen_profile_id', $citizenProfileId)
            ->with([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',
                'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',
                'complaint' => fn ($query) => $query
                    ->select(['id'])
                    ->withCount('reports'),
            ])
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function showForCitizen(User $user, ComplaintReport $report): ComplaintReport
    {
        $this->ensureOwnership($user, $report);

        return $report->load([
            'municipality:id,name',
            'category:id,parent_id,name',
            'currentStatus:id,key,name,is_terminal',
            'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',

            'statusHistories' => fn ($query) => $query
                ->where('is_public', true)
                ->with([
                    'fromStatus:id,key,name',
                    'toStatus:id,key,name',
                ])
                ->latest('created_at'),

            'complaint' => fn ($query) => $query
                ->select(['id'])
                ->withCount('reports'),
        ]);
    }

    public function createDraft(User $user, array $data): ComplaintReport
    {
        $citizenProfileId = $this->citizenProfileId($user);
        $draftStatus = $this->transitionService->getStatus(ComplaintStatus::DRAFT);

        $report = DB::transaction(function () use ($user, $citizenProfileId, $draftStatus, $data) {
            $report = ComplaintReport::query()->create([
                ...$data,
                'citizen_profile_id' => $citizenProfileId,
                'complaint_id' => null,
                'current_status_id' => $draftStatus->id,
                'submitted_at' => null,
                'linked_by' => null,
                'linked_at' => null,
            ]);

            return $this->transitionService->recordInitialStatusLocked(
                $report,
                ComplaintStatus::DRAFT,
                $user,
                'The complaint draft was created.',
                true
            );
        }, attempts: 3);

        return $this->loadRelations($report);
    }

    public function updateDraft(User $user, ComplaintReport $report, array $data): ComplaintReport
    {
        $report = DB::transaction(function () use ($user, $report, $data) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->ensureOwnership($user, $lockedReport);
            $this->ensureDraft($lockedReport);

            $lockedReport->fill($data);
            $lockedReport->save();

            return $lockedReport->refresh();
        }, attempts: 3);

        return $this->loadRelations($report);
    }

    public function deleteDraft(User $user, ComplaintReport $report): void
    {
        DB::transaction(function () use ($user, $report) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->ensureOwnership($user, $lockedReport);
            $this->ensureDraft($lockedReport);

            $lockedReport->delete();
        }, attempts: 3);
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function uploadImages(User $user, ComplaintReport $report, array $files): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($user, $report, $files, &$storedPaths) {
                $lockedReport = ComplaintReport::query()
                    ->lockForUpdate()
                    ->findOrFail($report->id);

                $this->ensureOwnership($user, $lockedReport);
                $this->ensureDraft($lockedReport);

                $currentImagesCount = $lockedReport->images()->count();

                if ($currentImagesCount + count($files) > self::MAX_IMAGES) {
                    throw ValidationException::withMessages([
                        'images' => [
                            'A complaint report may contain no more than five images.',
                        ],
                    ]);
                }

                $createdImages = new Collection();

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile) {
                        throw ValidationException::withMessages([
                            'images' => [
                                'One of the uploaded files is invalid.',
                            ],
                        ]);
                    }

                    $path = Storage::disk(self::DISK)->putFile("complaint-reports/{$lockedReport->id}", $file);

                    if ($path === false) {
                        throw new RuntimeException('The complaint image could not be stored.');
                    }

                    $storedPaths[] = $path;

                    $image = $lockedReport->images()->create([
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);

                    $createdImages->push($image);
                }

                return $createdImages;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk(self::DISK)->delete($storedPaths);
            }

            throw $exception;
        }
    }

    public function deleteImage(User $user, ComplaintReport $report, ComplaintReportImage $image): void
    {
        $filePath = DB::transaction(function () use ($user, $report, $image) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->ensureOwnership($user, $lockedReport);
            $this->ensureDraft($lockedReport);

            $lockedImage = ComplaintReportImage::query()
                ->whereKey($image->id)
                ->where('complaint_report_id', $lockedReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $path = $lockedImage->file_path;

            $lockedImage->delete();

            return $path;
        }, attempts: 3);

        if (! Storage::disk(self::DISK)->delete($filePath)) {
            Log::warning('Failed to delete complaint image file.', [
                'file_path' => $filePath,
                'complaint_report_id' => $report->id,
                'image_id' => $image->id,
            ]);
        }
    }

    public function submit(User $user, ComplaintReport $report): ComplaintReport
    {
        $report = DB::transaction(function () use ($user, $report) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->ensureOwnership($user, $lockedReport);
            $this->ensureDraft($lockedReport);

            $citizenProfile = $user->citizenProfile()
                ->lockForUpdate()
                ->first();

            if ($citizenProfile === null || ! $citizenProfile->is_verified) {
                throw ValidationException::withMessages([
                    'citizen' => [
                        'The citizen account must be verified before submitting a complaint.',
                    ],
                ]);
            }

            $this->validateReportCompleteness($lockedReport);
            $this->validateMunicipality($lockedReport);
            $this->validateCategory($lockedReport);

            $lockedReport->update([
                'submitted_at' => now(),
            ]);

            return $this->transitionService->transitionReportLocked(
                $lockedReport,
                ComplaintStatus::SUBMITTED,
                $user,
                'The complaint report was submitted.',
                true
            );
        }, attempts: 3);

        return $this->loadRelations($report);
    }

    private function validateReportCompleteness(ComplaintReport $report): void
    {
        Validator::make(
            $report->getAttributes(),
            [
                'municipality_id' => ['required', 'integer'],
                'category_id' => ['required', 'integer'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:5000'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ],
            [
                'municipality_id.required' => 'A municipality must be selected before submitting the complaint.',
                'category_id.required' => 'A complaint category must be selected before submitting the complaint.',
                'title.required' => 'A complaint title is required before submission.',
                'description.required' => 'A complaint description is required before submission.',
                'latitude.required' => 'The complaint location latitude is required.',
                'longitude.required' => 'The complaint location longitude is required.',
            ]
        )->validate();
    }

    private function validateMunicipality(ComplaintReport $report): void
    {
        $municipalityExists = Municipality::query()
            ->whereKey($report->municipality_id)
            ->where('status', true)
            ->exists();

        if (! $municipalityExists) {
            throw ValidationException::withMessages([
                'municipality_id' => [
                    'The selected municipality does not exist or is inactive.',
                ],
            ]);
        }
    }

    private function validateCategory(ComplaintReport $report): void
    {
        $categoryExists = ComplaintCategory::query()
            ->whereKey($report->category_id)
            ->where('is_active', true)
            ->exists();

        if (! $categoryExists) {
            throw ValidationException::withMessages([
                'category_id' => [
                    'The selected complaint category does not exist or is inactive.',
                ],
            ]);
        }
    }

    private function ensureOwnership(User $user, ComplaintReport $report): void
    {
        $citizenProfileId = $this->citizenProfileId($user);

        if ((int) $report->citizen_profile_id !== $citizenProfileId) {
            throw new AuthorizationException('You are not allowed to access this complaint report.');
        }
    }

    private function ensureDraft(ComplaintReport $report): void
    {
        $draftStatus = $this->transitionService->getStatus(ComplaintStatus::DRAFT);

        if (
            (int) $report->current_status_id !== (int) $draftStatus->id
            || $report->submitted_at !== null
            || $report->complaint_id !== null
        ) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'The complaint report can no longer be modified.',
                ],
            ]);
        }
    }

    private function citizenProfileId(User $user): int
    {
        $citizenProfile = $user->citizenProfile;

        if ($citizenProfile === null) {
            throw new AuthorizationException('This account does not have a citizen profile.');
        }

        return (int) $citizenProfile->id;
    }

    private function loadRelations(ComplaintReport $report): ComplaintReport
    {
        return $report->load([
            'municipality:id,name',
            'category:id,parent_id,name',
            'currentStatus:id,key,name,is_terminal',
            'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',

            'complaint' => fn ($query) => $query
                ->select(['id'])
                ->withCount('reports'),
        ]);
    }
}
