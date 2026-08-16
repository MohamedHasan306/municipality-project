<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintAssignmentService
{
    public function __construct(private readonly ComplaintStatusTransitionService $transitionService)
    {
    }

    public function assign(User $user, Complaint $complaint, array $workUnitIds, ?string $note = null): Complaint
    {
        $municipalityId = $this->employeeMunicipalityId($user);
        $uniqueWorkUnitIds = array_values(array_unique(array_map('intval', $workUnitIds)));

        return DB::transaction(function () use ($user, $complaint, $uniqueWorkUnitIds, $note, $municipalityId) {
            $lockedComplaint = Complaint::query()
                ->lockForUpdate()
                ->findOrFail($complaint->id);

            if ((int) $lockedComplaint->municipality_id !== $municipalityId) {
                throw new AuthorizationException('You cannot assign a complaint that belongs to another municipality.');
            }

            $underReviewStatus = $this->transitionService->getStatus(ComplaintStatus::UNDER_REVIEW);

            if ((int) $lockedComplaint->current_status_id !== (int) $underReviewStatus->id) {
                throw ValidationException::withMessages([
                    'complaint' => [
                        'The complaint cannot be assigned in its current status.',
                    ],
                ]);
            }

            $workUnits = WorkUnit::query()
                ->with([
                    'departmentManager' => fn ($query) => $query
                        ->select([
                            'users.id',
                            'users.full_name',
                            'users.email',
                            'users.phone_number',
                        ])
                        ->with('employeeProfile:id,user_id,municipality_id,status'),
                ])
                ->whereIn('id', $uniqueWorkUnitIds)
                ->where('municipality_id', $municipalityId)
                ->where('is_active', true)
                ->get();

            if ($workUnits->count() !== count($uniqueWorkUnitIds)) {
                throw ValidationException::withMessages([
                    'work_unit_ids' => [
                        'One of the selected work units does not exist, is inactive, or belongs to another municipality.',
                    ],
                ]);
            }

            foreach ($workUnits as $workUnit) {
                $manager = $workUnit->departmentManager;

                if ($workUnit->department_manager_id === null || $manager === null) {
                    throw ValidationException::withMessages([
                        'work_unit_ids' => [
                            "The work unit [{$workUnit->name}] does not have a department manager.",
                        ],
                    ]);
                }

                if (! $manager->hasRole('department_manager')) {
                    throw ValidationException::withMessages([
                        'work_unit_ids' => [
                            "The manager assigned to the work unit [{$workUnit->name}] does not have the department_manager role.",
                        ],
                    ]);
                }

                if ($manager->employeeProfile === null) {
                    throw ValidationException::withMessages([
                        'work_unit_ids' => [
                            "The manager assigned to the work unit [{$workUnit->name}] does not have an employee profile.",
                        ],
                    ]);
                }

                if ((int) $manager->employeeProfile->municipality_id !== $municipalityId) {
                    throw ValidationException::withMessages([
                        'work_unit_ids' => [
                            "The manager of the work unit [{$workUnit->name}] does not belong to the same municipality.",
                        ],
                    ]);
                }

                if ($manager->employeeProfile->status !== 'active') {
                    throw ValidationException::withMessages([
                        'work_unit_ids' => [
                            "The manager of the work unit [{$workUnit->name}] is inactive.",
                        ],
                    ]);
                }
            }

            $pivotData = [];

            foreach ($workUnits as $workUnit) {
                $pivotData[$workUnit->id] = [
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                    'unassigned_at' => null,
                ];
            }

            $lockedComplaint->workUnits()->sync($pivotData);

            $lockedComplaint = $this->transitionService->transitionUnifiedComplaintLocked(
                $lockedComplaint,
                ComplaintStatus::FORWARDED_TO_DEPARTMENT,
                $user,
                $note ?: 'The complaint was forwarded to the responsible work units.',
                true
            );

            return $this->loadComplaint($lockedComplaint);
        }, attempts: 3);
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
                    ->select([
                        'work_units.id',
                        'work_units.municipality_id',
                        'work_units.department_manager_id',
                        'work_units.name',
                        'work_units.description',
                        'work_units.is_active',
                    ])
                    ->with('departmentManager:id,full_name,email,phone_number'),

                'reports:id,complaint_id,current_status_id',
                'reports.currentStatus:id,key,name,is_terminal',
            ])
            ->loadCount('reports');
    }
}
