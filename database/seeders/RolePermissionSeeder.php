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
            /*
            |--------------------------------------------------------------------------
            | Platform / System Admin
            |--------------------------------------------------------------------------
            */

            'manage municipalities',          // إدارة البلديات
            'activate municipalities',        // تفعيل / تعطيل البلديات
            'manage municipality admins',     // إنشاء / إدارة مسؤول البلدية
            'manage roles',                   // إدارة الأدوار والصلاحيات العامة

            /*
            |--------------------------------------------------------------------------
            | Municipality Admin
            |--------------------------------------------------------------------------
            */

            'manage municipality employees',  // إنشاء / تعديل موظفي البلدية
            'assign roles to employees',      // إسناد roles للموظفين
            'suspend employees',              // تعطيل حساب موظف
            'force employee password change', // إجبار موظف على تغيير كلمة المرور

            /*
            |--------------------------------------------------------------------------
            | Auth / Password
            |--------------------------------------------------------------------------
            */

            'request password reset',         // طلب استعادة كلمة المرور
            'reset password',                 // إعادة تعيين كلمة المرور

            /*
            |--------------------------------------------------------------------------
            | Citizen Verification
            |--------------------------------------------------------------------------
            */

            'upload identity photos',         // رفع صور الهوية
            'verify citizens',                // توثيق المواطن
            'reject citizen verification',    // رفض توثيق المواطن - مبدئيًا قد لا تستخدمها

            /*
            |--------------------------------------------------------------------------
            | Complaints
            |--------------------------------------------------------------------------
            */

            'create complaint',               // إنشاء شكوى
            'view own complaints',            // عرض شكاوى المواطن الخاصة
            'review complaints',              // مراجعة الشكاوى
            'assign complaints',              // تحويل الشكوى إلى القسم المختص
            'execute complaints',             // تنفيذ الشكوى
            'resolve complaints',             // حل الشكوى
            'reject complaints',              // رفض الشكوى

            /*
            |--------------------------------------------------------------------------
            | Municipal Services / Transactions
            |--------------------------------------------------------------------------
            */

            'manage service types',           // إدارة أنواع الخدمات
            'manage service forms',           // إدارة نماذج الخدمات
            'submit service request',         // تقديم معاملة
            'review service requests',        // مراجعة المعاملة
            'engineering approve service requests', // موافقة المكتب الهندسي
            'mayor approve service requests',       // اعتماد رئيس البلدية

            /*
            |--------------------------------------------------------------------------
            | Documents
            |--------------------------------------------------------------------------
            */

            'issue documents',                // إصدار الوثائق
            'sign documents',                 // توقيع الوثائق
            'verify documents',               // التحقق من الوثائق
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | System Admin Permissions
        |--------------------------------------------------------------------------
        |
        | يدير المنصة نفسها:
        | - البلديات
        | - تفعيل البلديات
        | - إنشاء أول مسؤول لكل بلدية
        | - إدارة الأدوار العامة
        |
        */

        $systemAdmin->syncPermissions([
            'manage municipality employees',
            'manage municipalities',
            'activate municipalities',
            'manage municipality admins',
            'manage roles',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Municipality Admin Permissions
        |--------------------------------------------------------------------------
        |
        | مسؤول البلدية يدير موظفي بلديته فقط.
        | لا نعطيه manage municipalities لأنه لا ينشئ بلديات جديدة.
        |
        */

        $municipalityAdmin->syncPermissions([
            'manage municipality employees',
            'assign roles to employees',
            'suspend employees',
            'force employee password change',
            'verify citizens',
            'reject citizen verification',
            'manage service types',
            'manage service forms',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Citizen Permissions
        |--------------------------------------------------------------------------
        */

        $citizen->syncPermissions([
            'request password reset',
            'reset password',
            'upload identity photos',
            'create complaint',
            'view own complaints',
            'submit service request',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Technical Office Permissions
        |--------------------------------------------------------------------------
        */

        $technicalOffice->syncPermissions([
            'review complaints',
            'assign complaints',
            'reject complaints',
            'review service requests',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Department Manager Permissions
        |--------------------------------------------------------------------------
        */

        $departmentManager->syncPermissions([
            'execute complaints',
            'resolve complaints',
            'reject complaints',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Field Inspector Permissions
        |--------------------------------------------------------------------------
        */

        $fieldInspector->syncPermissions([
            'execute complaints',
            'resolve complaints',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Engineering Office Permissions
        |--------------------------------------------------------------------------
        */

        $engineeringOffice->syncPermissions([
            'engineering approve service requests',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Mayor Permissions
        |--------------------------------------------------------------------------
        */

        $mayor->syncPermissions([
            'mayor approve service requests',
            'issue documents',
            'sign documents',
            'verify documents',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
