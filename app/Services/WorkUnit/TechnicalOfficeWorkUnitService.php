<?php

namespace App\Services\WorkUnit;

use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class TechnicalOfficeWorkUnitService
{
    public function getAssignableWorkUnits(User $user): Collection
    {
        $municipalityId = $this->employeeMunicipalityId($user);

        return WorkUnit::query()
            ->where('municipality_id', $municipalityId)
            ->where('is_active', true)
            ->whereNotNull('department_manager_id')
            ->whereHas('departmentManager', fn ($query) => $query
                ->role('department_manager')
                ->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery
                    ->where('municipality_id', $municipalityId)
                    ->where('status', 'active')
                )
            )
            ->with([
                'municipality:id,name',
                'departmentManager:id,full_name,email,phone_number',
            ])
            ->orderBy('name')
            ->get();
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
}
