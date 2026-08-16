<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentManagerComplaintService
{
    public function __construct(private readonly ComplaintStatusTransitionService $transitionService)
    {
    }

    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $municipalityId = $this->validateManager($user);

        return Complaint::query()
            ->where('complaints.municipality_id', $municipalityId)
            ->whereHas('workUnits', fn ($query) => $query
                ->where('work_units.department_manager_id', $user->id)
                ->where('work_units.is_active', true)
                ->whereNull('complaint_work_unit.unassigned_at')
            )
            ->with([
                'municipality:id,name',
                'category:id,parent_id,name',
                'currentStatus:id,key,name,is_terminal',

                'workUnits' => fn ($query) => $query
                    ->whereNull('complaint_work_unit.unassigned_at')
                    ->with('departmentManager:id,full_name,email,phone_number'),
            ])
            ->withCount('reports')
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function show(User $user, Complaint $complaint): Complaint
    {
        $municipalityId = $this->validateManager($user);

        return Complaint::query()
            ->whereKey($complaint->id)
            ->where('complaints.municipality_id', $municipalityId)
            ->whereHas('workUnits', fn ($query) => $query
                ->where('work_units.department_manager_id', $user->id)
                ->where('work_units.is_active', true)
                ->whereNull('complaint_work_unit.unassigned_at')
            )
            ->with($this->detailsRelations())
            ->withCount('reports')
            ->firstOrFail();
    }

    public function start(User $user, Complaint $complaint, ?string $note = null): Complaint
    {
        return $this->changeStatus(
            $user,
            $complaint,
            ComplaintStatus::IN_PROGRESS,
            $note ?: 'Work on the complaint has started.'
        );
    }

    public function resolve(User $user, Complaint $complaint, ?string $note = null): Complaint
    {
        return $this->changeStatus(
            $user,
            $complaint,
            ComplaintStatus::RESOLVED,
            $note
        );
    }

    public function reject(User $user, Complaint $complaint): Complaint
    {
        return $this->changeStatus(
            $user,
            $complaint,
            ComplaintStatus::REJECTED,
            null
        );
    }

    private function changeStatus(User $user, Complaint $complaint, string $targetStatusKey, ?string $note): Complaint
    {
        $municipalityId = $this->validateManager($user);

        $complaint = DB::transaction(function () use ($user, $complaint, $targetStatusKey, $note, $municipalityId) {
            $lockedComplaint = Complaint::query()
                ->lockForUpdate()
                ->findOrFail($complaint->id);

            $this->ensureManagerCanAccessComplaint($user, $lockedComplaint, $municipalityId);

            return $this->transitionService->transitionUnifiedComplaintLocked(
                $lockedComplaint,
                $targetStatusKey,
                $user,
                $note,
                true
            );
        }, attempts: 3);

        return $this->loadDetails($complaint);
    }

    private function ensureManagerCanAccessComplaint(User $user, Complaint $complaint, int $municipalityId): void
    {
        if ((int) $complaint->municipality_id !== $municipalityId) {
            throw new AuthorizationException('You cannot access a complaint that belongs to another municipality.');
        }

        $managesComplaint = $complaint->workUnits()
            ->where('work_units.department_manager_id', $user->id)
            ->where('work_units.is_active', true)
            ->whereNull('complaint_work_unit.unassigned_at')
            ->exists();

        if (! $managesComplaint) {
            throw new AuthorizationException('This complaint is not assigned to a work unit managed by this account.');
        }
    }

    private function validateManager(User $user): int
    {
        if (! $user->hasRole('department_manager')) {
            throw new AuthorizationException('This account is not a department manager.');
        }

        $employeeProfile = $user->employeeProfile;

        if ($employeeProfile === null) {
            throw new AuthorizationException('This account does not have an employee profile.');
        }

        if ($employeeProfile->status !== 'active') {
            throw new AuthorizationException('This department manager account is inactive.');
        }

        if ($employeeProfile->municipality_id === null) {
            throw new AuthorizationException('This account is not associated with a municipality.');
        }

        if (! $user->managedWorkUnits()->where('is_active', true)->exists()) {
            throw new AuthorizationException('This department manager is not assigned to an active work unit.');
        }

        return (int) $employeeProfile->municipality_id;
    }

    private function loadDetails(Complaint $complaint): Complaint
    {
        return $complaint->refresh()
            ->load($this->detailsRelations())
            ->loadCount('reports');
    }

    private function detailsRelations(): array
    {
        return [
            'municipality:id,name',
            'category:id,parent_id,name',
            'currentStatus:id,key,name,is_terminal',

            'workUnits' => fn ($query) => $query
                ->whereNull('complaint_work_unit.unassigned_at')
                ->with('departmentManager:id,full_name,email,phone_number'),

            'reports' => fn ($query) => $query
                ->select([
                    'id',
                    'complaint_id',
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
                ])
                ->latest('submitted_at'),
        ];
    }
}
