<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Traits\ApiResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    use ApiResponse;

    public function roles()
    {
        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->latest()
            ->get();

        return $this->successResponse(
            RoleResource::collection($roles),
            'Roles successfully retrieved.'
        );
    }

    public function permissions()
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            PermissionResource::collection($permissions),
            'Permissions successfully retrieved.'
        );
    }

    public function storeRole(StoreRoleRequest $request)
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $this->successResponse(
            new RoleResource($role->load('permissions')),
            'The role has been successfully created.',
            201
        );
    }

    public function updateRole(UpdateRoleRequest $request, Role $role)
    {
        $this->preventProtectedRoleModification($role);

        $role->update($request->validated());

        return $this->successResponse(
            new RoleResource($role->fresh('permissions')),
            'The role has been successfully updated.'
        );
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $this->preventProtectedRoleModification($role);

        $role->syncPermissions($request->validated('permissions'));

        return $this->successResponse(
            new RoleResource($role->fresh('permissions')),
            'The role permissions have been successfully updated.'
        );
    }

    public function deleteRole(Role $role)
    {
        $this->preventProtectedRoleDeletion($role);

        if ($role->users()->exists()) {
            return $this->errorResponse(
                'The role cannot be deleted because it is assigned to users.',
                null,
                409
            );
        }

        $role->delete();

        return $this->successResponse(
            null,
            'The role has been successfully deleted.'
        );
    }

    private function preventProtectedRoleModification(Role $role): void
    {
        if (in_array($role->name, ['system_admin', 'municipality_admin', 'citizen'])) {
            abort(403, 'This fundamental role cannot be modified.');
        }
    }

    private function preventProtectedRoleDeletion(Role $role): void
    {
        if (in_array($role->name, [
            'system_admin',
            'municipality_admin',
            'citizen',
            'mayor',
            'technical_office',
            'engineering_office',
            'department_manager',
            'field_inspector',
        ])) {
            abort(403, 'A fundamental role in the system cannot be deleted.');
        }
    }
}
