<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\AssignEmployeeRoleRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\User;

class EmployeeRoleController extends Controller
{
    use ApiResponse;

    public function assignRole(AssignEmployeeRoleRequest $request, User $employee)
    {
        $currentUser = $request->user();

        $currentMunicipalityId = $currentUser->employeeProfile?->municipality_id;
        $employeeMunicipalityId = $employee->employeeProfile?->municipality_id;

        if (! $currentMunicipalityId) {
            return $this->errorResponse(
                'The current user is not linked to a municipality.',
                'Current user has no municipality',
                422
            );
        }

        if (! $employeeMunicipalityId) {
            return $this->errorResponse(
                'This user is not an employee.',
                'User is not employee',
                422
            );
        }

        if ($currentMunicipalityId !== $employeeMunicipalityId) {
            return $this->errorResponse(
                'You cannot edit an employee from another municipality.',
                'Employee belongs to another municipality',
                403
            );
        }

        if ($employee->hasRole('system_admin')) {
            return $this->errorResponse(
                'The system administrator role cannot be modified.',
                'Cannot modify system admin role',
                403
            );
        }

        $role = $request->validated('role');

        if ($role === 'system_admin') {
            return $this->errorResponse(
                'The System Administrator role cannot be assigned.',
                'Cannot assign system admin role',
                403
            );
        }

        if ($role === 'mayor') {
            $municipalityAlreadyHasAdmin = User::query()
                ->whereHas('employeeProfile', function ($query) use ($employeeMunicipalityId) {
                    $query->where('municipality_id', $employeeMunicipalityId);
                })
                ->where('id', '!=', $employee->id)
                ->role('mayor')
                ->exists();

            if ($municipalityAlreadyHasAdmin) {
                return $this->errorResponse(
                    'No more than one manager may be appointed for the same municipality.',
                    'More Than Mayor',
                    422
                );
            }
        }

        $employee->syncRoles([$role]);

        return $this->successResponse(
            new UserResource($employee->fresh(['employeeProfile'])),
            'The role has been successfully assigned to the employee.'
        );
    }
}
