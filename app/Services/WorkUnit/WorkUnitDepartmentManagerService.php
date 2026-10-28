<?php

namespace App\Services\WorkUnit;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkUnitDepartmentManagerService
{
    public function assign(User $actor, WorkUnit $workUnit, int $managerId): WorkUnit
    {
        $municipalityId = $this->employeeMunicipalityId($actor);

        return DB::transaction(function () use ($workUnit, $managerId, $municipalityId) {
            $lockedWorkUnit = WorkUnit::query()
                ->lockForUpdate()
                ->findOrFail($workUnit->id);

            if ((int) $lockedWorkUnit->municipality_id !== $municipalityId) {
                throw new AuthorizationException('You cannot manage a work unit that belongs to another municipality.');
            }

            if (! $lockedWorkUnit->is_active) {
                throw ValidationException::withMessages([
                    'work_unit' => [
                        'The work unit is inactive.',
                    ],
                ]);
            }

            $manager = User::query()
                ->with('employeeProfile')
                ->findOrFail($managerId);

            if (! $manager->hasRole('department_manager')) {
                throw ValidationException::withMessages([
                    'department_manager_id' => [
                        'The selected user does not have the department manager role.',
                    ],
                ]);
            }

            if ($manager->employeeProfile === null) {
                throw ValidationException::withMessages([
                    'department_manager_id' => [
                        'The selected department manager does not have an employee profile.',
                    ],
                ]);
            }

            if ((int) $manager->employeeProfile->municipality_id !== $municipalityId) {
                throw ValidationException::withMessages([
                    'department_manager_id' => [
                        'The selected department manager does not belong to the same municipality.',
                    ],
                ]);
            }

            if (! (bool) $manager->employeeProfile->status) {
                throw ValidationException::withMessages([
                    'department_manager_id' => [
                        'The selected department manager account is inactive.',
                    ],
                ]);
            }

            $lockedWorkUnit->update([
                'department_manager_id' => $manager->id,
            ]);

            return $lockedWorkUnit->refresh()->load([
                'municipality:id,name',
                'departmentManager:id,full_name,email,phone',
            ]);
        }, attempts: 3);
    }

    private function employeeMunicipalityId(User $user): int
    {
        $municipalityId = $user->employeeProfile?->municipality_id;

        if ($municipalityId === null) {
            throw new AuthorizationException('This account is not associated with a municipality.');
        }

        return (int) $municipalityId;
    }
}
