<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalOfficeComplaintService
{
    public function __construct(private readonly ComplaintStatusTransitionService $transitionService)
    {
    }

    public function paginate(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        return Complaint::query()
            ->where('complaints.municipality_id', $municipalityId)

            ->when($filters['status'] ?? null, fn ($query, $status) => $query
                ->whereHas('currentStatus', fn ($statusQuery) => $statusQuery->where('key', $status))
            )

            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query
                ->where('category_id', $categoryId)
            )

            ->when($filters['work_unit_id'] ?? null, fn ($query, $workUnitId) => $query
                ->whereHas('workUnits', fn ($workUnitQuery) => $workUnitQuery
                    ->where('work_units.id', $workUnitId)
                    ->whereNull('complaint_work_unit.unassigned_at')
                )
            )

            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query
                ->whereDate('submitted_at', '>=', $dateFrom)
            )

            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query
                ->whereDate('submitted_at', '<=', $dateTo)
            )

            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('canonical_description', 'like', "%{$search}%")
                        ->orWhere('text_location', 'like', "%{$search}%");
                });
            })

            ->with([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',

                'workUnits' => fn ($query) => $query
                    ->whereNull('complaint_work_unit.unassigned_at')
                    ->with('departmentManager:id,full_name,email,phone'),
            ])
            ->withCount('reports')
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function reject(User $user, Complaint $complaint): Complaint
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        $complaint = DB::transaction(function () use ($user, $complaint, $municipalityId) {
            $lockedComplaint = Complaint::query()
                ->lockForUpdate()
                ->findOrFail($complaint->id);

            if ((int) $lockedComplaint->municipality_id !== $municipalityId) {
                throw new AuthorizationException('You cannot reject a complaint that belongs to another municipality.');
            }

            $underReviewStatus = $this->transitionService->getStatus(ComplaintStatus::UNDER_REVIEW);

            if ((int) $lockedComplaint->current_status_id !== (int) $underReviewStatus->id) {
                throw ValidationException::withMessages([
                    'complaint' => [
                        'The Technical Office may only reject a complaint while it is under review.',
                    ],
                ]);
            }

            return $this->transitionService->transitionUnifiedComplaintLocked(
                $lockedComplaint,
                ComplaintStatus::REJECTED,
                $user,
                null,
                true
            );
        }, attempts: 3);

        return $this->loadComplaint($complaint);
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

    private function loadComplaint(Complaint $complaint): Complaint
    {
        return $complaint->refresh()
            ->load([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',

                'workUnits' => fn ($query) => $query
                    ->whereNull('complaint_work_unit.unassigned_at')
                    ->with('departmentManager:id,full_name,email,phone'),

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
