<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintReport;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintReviewService
{
    private const SIMILARITY_RADIUS_METERS = 50;

    private const SIMILARITY_DAYS = 15;

    public function __construct(private readonly ComplaintStatusTransitionService $transitionService)
    {
    }

    public function paginatePendingReports(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $municipalityId = $this->employeeMunicipalityId($user);
        $submittedStatus = $this->transitionService->getStatus(ComplaintStatus::SUBMITTED);

        return ComplaintReport::query()
            ->where('municipality_id', $municipalityId)
            ->where('current_status_id', $submittedStatus->id)
            ->whereNotNull('submitted_at')
            ->whereNull('complaint_id')
            ->with([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',
                'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',
            ])
            ->latest('submitted_at')
            ->paginate($perPage);
    }

    public function showPendingReport(User $user, ComplaintReport $report): ComplaintReport
    {
        $this->assertPendingReport($report, $this->employeeMunicipalityId($user));

        return $this->loadReport($report);
    }

    public function findSimilarComplaints(User $user, ComplaintReport $report): Collection
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        $this->assertPendingReport($report, $municipalityId);

        $latitude = (float) $report->latitude;
        $longitude = (float) $report->longitude;

        $latitudeDelta = self::SIMILARITY_RADIUS_METERS / 111320;
        $longitudeScale = max(cos(deg2rad($latitude)), 0.000001);
        $longitudeDelta = self::SIMILARITY_RADIUS_METERS / (111320 * $longitudeScale);

        $distanceSql = <<<'SQL'
            6371000 * ACOS(
                LEAST(
                    1,
                    GREATEST(
                        -1,
                        COS(RADIANS(?))
                        * COS(RADIANS(latitude))
                        * COS(
                            RADIANS(longitude)
                            - RADIANS(?)
                        )
                        + SIN(RADIANS(?))
                        * SIN(RADIANS(latitude))
                    )
                )
            )
        SQL;

        $startDate = $report->submitted_at->copy()->subDays(self::SIMILARITY_DAYS);
        $endDate = $report->submitted_at->copy();

        $complaints = Complaint::query()
            ->select([
                'complaints.id',
                'complaints.municipality_id',
                'complaints.category_id',
                'complaints.current_status_id',
                'complaints.title',
                'complaints.canonical_description',
                'complaints.text_location',
                'complaints.latitude',
                'complaints.longitude',
                'complaints.submitted_at',
                'complaints.created_at',
                'complaints.updated_at',
            ])
            ->selectRaw("{$distanceSql} AS distance_meters", [
                $latitude,
                $longitude,
                $latitude,
            ])
            ->where('municipality_id', $municipalityId)
            ->where('category_id', $report->category_id)
            ->whereBetween('submitted_at', [$startDate, $endDate])
            ->whereBetween('latitude', [
                $latitude - $latitudeDelta,
                $latitude + $latitudeDelta,
            ])
            ->whereBetween('longitude', [
                $longitude - $longitudeDelta,
                $longitude + $longitudeDelta,
            ])
            ->whereHas('currentStatus', fn ($query) => $query->where('is_terminal', false))
            ->with([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',
            ])
            ->withCount('reports')
            ->having('distance_meters', '<=', self::SIMILARITY_RADIUS_METERS)
            ->orderBy('distance_meters')
            ->get();

        $normalizedReportLocation = $this->normalizeLocationText($report->text_location);

        if ($normalizedReportLocation !== '') {
            $complaints = $complaints
                ->filter(fn (Complaint $complaint) => $this->normalizeLocationText($complaint->text_location) === $normalizedReportLocation)
                ->values();
        }

        return $complaints;
    }

    public function createUnifiedComplaint(User $user, ComplaintReport $report, array $data): Complaint
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        return DB::transaction(function () use ($user, $report, $data, $municipalityId) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->assertPendingReport($lockedReport, $municipalityId);

            $submittedStatus = $this->transitionService->getStatus(ComplaintStatus::SUBMITTED);

            $complaint = Complaint::query()->create([
                'municipality_id' => $lockedReport->municipality_id,
                'category_id' => $lockedReport->category_id,
                'current_status_id' => $submittedStatus->id,
                'title' => $data['title'] ?? $lockedReport->title,
                'canonical_description' => $data['canonical_description'] ?? $lockedReport->description,
                'text_location' => $data['text_location'] ?? $lockedReport->text_location,
                'latitude' => $lockedReport->latitude,
                'longitude' => $lockedReport->longitude,
                'submitted_at' => $lockedReport->submitted_at,
            ]);

            $lockedReport->update([
                'complaint_id' => $complaint->id,
                'linked_by' => $user->id,
                'linked_at' => now(),
            ]);

            $complaint = $this->transitionService->transitionUnifiedComplaintLocked(
                $complaint,
                ComplaintStatus::UNDER_REVIEW,
                $user,
                $data['note'] ?? 'The Technical Office has begun reviewing the complaint.',
                true
            );

            return $this->loadComplaint($complaint);
        }, attempts: 3);
    }

    public function mergeWithComplaint(User $user, ComplaintReport $report, int $complaintId): Complaint
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        return DB::transaction(function () use ($user, $report, $complaintId, $municipalityId) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->assertPendingReport($lockedReport, $municipalityId);

            $complaint = Complaint::query()
                ->lockForUpdate()
                ->findOrFail($complaintId);

            $this->assertMergeAllowed($lockedReport, $complaint, $municipalityId);

            $lockedReport->update([
                'complaint_id' => $complaint->id,
                'linked_by' => $user->id,
                'linked_at' => now(),
            ]);

            $this->transitionService->synchronizeReportToUnifiedComplaintLocked(
                $lockedReport,
                $complaint,
                $user,
                'The report was linked to an existing unified complaint.',
                true
            );

            return $this->loadComplaint($complaint);
        }, attempts: 3);
    }

    public function rejectReport(User $user, ComplaintReport $report): ComplaintReport
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        $report = DB::transaction(function () use ($user, $report, $municipalityId) {
            $lockedReport = ComplaintReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            $this->assertPendingReport($lockedReport, $municipalityId);

            return $this->transitionService->transitionReportLocked(
                $lockedReport,
                ComplaintStatus::REJECTED,
                $user,
                null,
                true
            );
        }, attempts: 3);

        return $this->loadReport($report);
    }

    private function assertPendingReport(ComplaintReport $report, int $municipalityId): void
    {
        if ((int) $report->municipality_id !== $municipalityId) {
            throw new AuthorizationException('You are not allowed to review reports from another municipality.');
        }

        if ($report->submitted_at === null) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'This complaint report is still a draft.',
                ],
            ]);
        }

        if ($report->complaint_id !== null) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'This complaint report is already linked to a unified complaint.',
                ],
            ]);
        }

        $submittedStatus = $this->transitionService->getStatus(ComplaintStatus::SUBMITTED);

        if ((int) $report->current_status_id !== (int) $submittedStatus->id) {
            throw ValidationException::withMessages([
                'complaint_report' => [
                    'This complaint report is not waiting for Technical Office review.',
                ],
            ]);
        }
    }

    private function assertMergeAllowed(ComplaintReport $report, Complaint $complaint, int $municipalityId): void
    {
        if (
            (int) $complaint->municipality_id !== $municipalityId
            || (int) $complaint->municipality_id !== (int) $report->municipality_id
        ) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'You are not allowed to merge with a complaint from another municipality.',
                ],
            ]);
        }

        if ((int) $complaint->category_id !== (int) $report->category_id) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'The complaint category must match the report category.',
                ],
            ]);
        }

        $complaintStatus = ComplaintStatus::query()
            ->find($complaint->current_status_id);

        if ($complaintStatus === null || $complaintStatus->is_terminal) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'You are not allowed to merge with a terminal complaint.',
                ],
            ]);
        }

        $differenceInSeconds = abs(
            $complaint->submitted_at->getTimestamp()
            - $report->submitted_at->getTimestamp()
        );

        if ($differenceInSeconds > self::SIMILARITY_DAYS * 86400) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'The time difference between the reports exceeds seven days.',
                ],
            ]);
        }

        $distance = $this->distanceInMeters(
            (float) $report->latitude,
            (float) $report->longitude,
            (float) $complaint->latitude,
            (float) $complaint->longitude
        );

        if ($distance > self::SIMILARITY_RADIUS_METERS) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'The distance between the report and the complaint exceeds fifty meters.',
                ],
            ]);
        }

        $normalizedReportLocation = $this->normalizeLocationText($report->text_location);
        $normalizedComplaintLocation = $this->normalizeLocationText($complaint->text_location);

        if (
            $normalizedReportLocation !== ''
            && $normalizedReportLocation !== $normalizedComplaintLocation
        ) {
            throw ValidationException::withMessages([
                'complaint_id' => [
                    'The complaint text location does not match the report location.',
                ],
            ]);
        }
    }

    private function distanceInMeters(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $earthRadius = 6371000;

        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);

        $a =
            sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalizeLocationText(?string $location): string
    {
        $location = trim((string) $location);
        $location = preg_replace('/\s+/u', ' ', $location);

        return mb_strtolower($location ?? '');
    }

    private function employeeMunicipalityId(User $user): int
    {
        $employeeProfile = $user->employeeProfile;

        if ($employeeProfile === null) {
            throw new AuthorizationException('This account does not have an employee profile.');
        }

        if ($employeeProfile->status !== 'active') {
            throw new AuthorizationException('This employee account is inactive.');
        }

        if ($employeeProfile->municipality_id === null) {
            throw new AuthorizationException('This account is not associated with a municipality.');
        }

        return (int) $employeeProfile->municipality_id;
    }

    private function loadReport(ComplaintReport $report): ComplaintReport
    {
        return $report->refresh()->load([
            'municipality:id,name',
            'category:id,parent_id,name',
            'currentStatus:id,key,name,is_terminal',
            'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',
        ]);
    }

    private function loadComplaint(Complaint $complaint): Complaint
    {
        return $complaint->refresh()
            ->load([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',

                'reports' => fn ($query) => $query
                    ->select([
                        'id',
                        'complaint_id',
                        'municipality_id',
                        'category_id',
                        'current_status_id',
                        'title',
                        'description',
                        'text_location',
                        'latitude',
                        'longitude',
                        'submitted_at',
                        'linked_at',
                    ])
                    ->with([
                        'currentStatus:id,key,name,is_terminal',
                        'images:id,complaint_report_id,file_path,original_name,mime_type,file_size',

                        'statusHistories' => fn ($historyQuery) => $historyQuery
                            ->with([
                                'fromStatus:id,key,name',
                                'toStatus:id,key,name',
                                'changedBy:id,full_name',
                            ])
                            ->latest('created_at'),
                    ]),
            ])
            ->loadCount('reports');
    }
}
