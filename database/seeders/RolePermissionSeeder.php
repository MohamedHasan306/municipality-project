<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            'manage municipalities',
            'activate municipalities',
            'manage municipality admins',
            'manage roles',

            'manage municipality employees',
            'assign roles to employees',
            'suspend employees',
            'force employee password change',

            'request password reset',
            'reset password',

            'upload identity photos',
            'verify citizens',
            'reject citizen verification',

            'create complaint',
            'update own complaint draft',
            'delete own complaint draft',
            'submit complaint',
            'view own complaints',
            'view own complaint history',

            'view municipality complaints',
            'view municipality complaint reports',
            'view complaint status history',

            'review complaints',
            'detect similar complaints',
            'merge complaint reports',
            'assign complaints',
            'assign complaints to work units',
            'reject complaint reports',

            'execute complaints',
            'resolve complaints',
            'reject complaints',

            'view work unit complaints',
            'manage work units',

            'manage complaint categories',

            'view complaint statistics',

            'manage service types',
            'manage service forms',
            'submit service request',
            'review service requests',
            'engineering approve service requests',
            'mayor approve service requests',

            'issue documents',
            'sign documents',
            'verify documents',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $systemAdmin = Role::firstOrCreate([
            'name' => 'system_admin',
            'guard_name' => $guard,
        ]);

        $municipalityAdmin = Role::firstOrCreate([
            'name' => 'municipality_admin',
            'guard_name' => $guard,
        ]);

        $mayor = Role::firstOrCreate([
            'name' => 'mayor',
            'guard_name' => $guard,
        ]);

        $technicalOffice = Role::firstOrCreate([
            'name' => 'technical_office',
            'guard_name' => $guard,
        ]);

        $engineeringOffice = Role::firstOrCreate([
            'name' => 'engineering_office',
            'guard_name' => $guard,
        ]);

        $departmentManager = Role::firstOrCreate([
            'name' => 'department_manager',
            'guard_name' => $guard,
        ]);

        $fieldInspector = Role::firstOrCreate([
            'name' => 'field_inspector',
            'guard_name' => $guard,
        ]);

        $citizen = Role::firstOrCreate([
            'name' => 'citizen',
            'guard_name' => $guard,
        ]);

        $systemAdmin->syncPermissions([
            'manage municipality employees',
            'manage municipalities',
            'activate municipalities',
            'manage municipality admins',
            'manage roles',
            'manage complaint categories',
        ]);

        $municipalityAdmin->syncPermissions([
            'manage municipality employees',
            'assign roles to employees',
            'suspend employees',
            'force employee password change',

            'verify citizens',
            'reject citizen verification',

            'manage service types',
            'manage service forms',

            'manage work units',

            'view municipality complaints',
            'view municipality complaint reports',
            'view complaint status history',
            'view complaint statistics',
        ]);

        $citizen->syncPermissions([
            'request password reset',
            'reset password',
            'upload identity photos',

            'create complaint',
            'update own complaint draft',
            'delete own complaint draft',
            'submit complaint',
            'view own complaints',
            'view own complaint history',
            'view municipality complaints',

            'submit service request',
        ]);

        $technicalOffice->syncPermissions([
            'view municipality complaints',
            'view municipality complaint reports',
            'view complaint status history',
            'view complaint statistics',

            'review complaints',
            'detect similar complaints',
            'merge complaint reports',
            'assign complaints',
            'assign complaints to work units',

            'reject complaint reports',
            'reject complaints',

            'review service requests',
        ]);

        $departmentManager->syncPermissions([
            'view municipality complaints',
            'view municipality complaint reports',
            'view complaint status history',
            'view work unit complaints',

            'execute complaints',
            'resolve complaints',
            'reject complaints',
        ]);

        $fieldInspector->syncPermissions([
            'view work unit complaints',
            'view complaint status history',
            'execute complaints',
            'resolve complaints',
        ]);

        $engineeringOffice->syncPermissions([
            'engineering approve service requests',
        ]);

        $mayor->syncPermissions([
            'view municipality complaints',
            'view municipality complaint reports',
            'view complaint status history',
            'view complaint statistics',

            'mayor approve service requests',
            'issue documents',
            'sign documents',
            'verify documents',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
