<?php

use App\Http\Controllers\Authentication\AuthenticationController;
use App\Http\Controllers\Authentication\CitizenIdentityController;
use App\Http\Controllers\Authentication\CitizenVerificationController;
use App\Http\Controllers\Authentication\PasswordResetController;
use App\Http\Controllers\Complaint\ComplaintReportController;
use App\Http\Controllers\Complaint\DepartmentManager\DepartmentManagerComplaintController;
use App\Http\Controllers\Complaint\Statistics\ComplaintStatisticsController;
use App\Http\Controllers\Complaint\TechnicalOffice\ComplaintAssignmentController;
use App\Http\Controllers\Complaint\TechnicalOffice\ComplaintReviewController;
use App\Http\Controllers\Complaint\TechnicalOffice\TechnicalOfficeComplaintController;
use App\Http\Controllers\Complaint\TechnicalOffice\TechnicalOfficeWorkUnitController;
use App\Http\Controllers\EmployeeRoleController;
use App\Http\Controllers\GovernoratesController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\WorkUnit\WorkUnitDepartmentManagerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
|
| These routes do not require authentication.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthenticationController::class, 'login'])
        ->middleware('throttle:login');

    Route::post('/register-citizen', [AuthenticationController::class, 'registerCitizen']);

    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset');

    Route::post('/verify-reset-otp', [PasswordResetController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');

    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:password-reset');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes Before Password Change
|--------------------------------------------------------------------------
|
| These routes require authentication but do not require the temporary
| password to have been changed yet.
|
*/

Route::middleware('auth:sanctum')
    ->prefix('auth')
    ->group(function () {
        Route::post('/change-temporary-password', [AuthenticationController::class, 'changeTemporaryPassword'])
            ->middleware('ability:change-password');

        Route::post('/logout', [AuthenticationController::class, 'logout']);

        Route::get('/me', [AuthenticationController::class, 'me'])
            ->middleware('password.changed');
    });

/*
|--------------------------------------------------------------------------
| Public Location Data
|--------------------------------------------------------------------------
*/

Route::prefix('public')
    ->controller(GovernoratesController::class)
    ->group(function () {
        Route::get('/governorates', 'index');

        Route::get('/governorates/{governorate}/municipalities', 'municipalities');
    });

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
|
| All routes below require:
| - Sanctum authentication
| - Temporary password change when required
|
*/

Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Citizen Identity
    |--------------------------------------------------------------------------
    */

    Route::post('/citizen/identity-photos', [CitizenIdentityController::class, 'upload'])
        ->middleware('permission:upload identity photos');

    /*
    |--------------------------------------------------------------------------
    | Citizen Verification
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/citizens')->group(function () {
        Route::get('/pending-verification', [CitizenVerificationController::class, 'pending'])
            ->middleware('permission:verify citizens');

        Route::post('/{citizenProfile}/verify', [CitizenVerificationController::class, 'verify'])
            ->middleware('permission:verify citizens');

        Route::post('/{citizenProfile}/reject-verification', [CitizenVerificationController::class, 'reject'])
            ->middleware('permission:reject citizen verification');
    });

    /*
    |--------------------------------------------------------------------------
    | Municipalities - System Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin/municipalities')->group(function () {
        Route::get('/', [MunicipalityController::class, 'index'])
            ->middleware('permission:manage municipalities');

        Route::post('/', [MunicipalityController::class, 'store'])
            ->middleware('permission:manage municipalities');

        Route::get('/{municipality}', [MunicipalityController::class, 'show'])
            ->middleware('permission:manage municipalities');

        Route::put('/{municipality}', [MunicipalityController::class, 'update'])
            ->middleware('permission:manage municipalities');

        Route::patch('/{municipality}/activate', [MunicipalityController::class, 'activate'])
            ->middleware('permission:activate municipalities');

        Route::patch('/{municipality}/deactivate', [MunicipalityController::class, 'deactivate'])
            ->middleware('permission:activate municipalities');
    });

    /*
    |--------------------------------------------------------------------------
    | Municipality Employees
    |--------------------------------------------------------------------------
    */

    Route::prefix('municipality/employees')->group(function () {
        Route::post('/{employee}/assign-role', [EmployeeRoleController::class, 'assignRole'])
            ->middleware('permission:assign roles to employees');
    });

    Route::post('/auth/register-employee', [AuthenticationController::class, 'registerEmployee'])
        ->middleware('permission:manage municipality employees');

    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions - System Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->middleware('permission:manage roles')
        ->group(function () {
            Route::get('/roles', [RolePermissionController::class, 'roles']);

            Route::post('/roles', [RolePermissionController::class, 'storeRole']);

            Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole']);

            Route::delete('/roles/{role}', [RolePermissionController::class, 'deleteRole']);

            Route::get('/permissions', [RolePermissionController::class, 'permissions']);

            Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions']);
        });

    /*
    |--------------------------------------------------------------------------
    | Citizen Complaints
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:citizen')
        ->prefix('citizen/complaints')
        ->name('citizen.complaints.')
        ->group(function () {
            Route::get('/', [ComplaintReportController::class, 'index'])
                ->name('index');

            Route::post('/drafts', [ComplaintReportController::class, 'store'])
                ->name('store');

            Route::get('/{report}', [ComplaintReportController::class, 'show'])
                ->whereNumber('report')
                ->name('show');

            Route::patch('/{report}', [ComplaintReportController::class, 'update'])
                ->whereNumber('report')
                ->name('update');

            Route::delete('/{report}', [ComplaintReportController::class, 'destroy'])
                ->whereNumber('report')
                ->name('destroy');

            Route::post('/{report}/images', [ComplaintReportController::class, 'uploadImages'])
                ->whereNumber('report')
                ->name('images.store');

            Route::delete('/{report}/images/{image}', [ComplaintReportController::class, 'destroyImage'])
                ->whereNumber(['report', 'image'])
                ->name('images.destroy');

            Route::post('/{report}/submit', [ComplaintReportController::class, 'submit'])
                ->whereNumber('report')
                ->name('submit');
        });

    /*
    |--------------------------------------------------------------------------
    | Technical Office - Complaint Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:technical_office')
        ->prefix('technical-office/complaint-reports')
        ->name('technical-office.complaint-reports.')
        ->group(function () {
            Route::get('/', [ComplaintReviewController::class, 'index'])
                ->middleware('permission:view municipality complaint reports')
                ->name('index');

            Route::get('/{report}', [ComplaintReviewController::class, 'show'])
                ->whereNumber('report')
                ->middleware('permission:view municipality complaint reports')
                ->name('show');

            Route::get('/{report}/similar', [ComplaintReviewController::class, 'similar'])
                ->whereNumber('report')
                ->middleware('permission:detect similar complaints')
                ->name('similar');

            Route::post('/{report}/create-unified', [ComplaintReviewController::class, 'createUnified'])
                ->whereNumber('report')
                ->middleware('permission:review complaints')
                ->name('create-unified');

            Route::post('/{report}/merge', [ComplaintReviewController::class, 'merge'])
                ->whereNumber('report')
                ->middleware('permission:merge complaint reports')
                ->name('merge');

            /*
             * Reject a non-unified complaint report.
             */
            Route::patch('/{report}/reject', [ComplaintReviewController::class, 'reject'])
                ->whereNumber('report')
                ->middleware('permission:reject complaint reports')
                ->name('reject');
        });

    /*
    |--------------------------------------------------------------------------
    | Municipality Admin - Work Units
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:municipality_admin')
        ->prefix('municipality-admin/work-units')
        ->name('municipality-admin.work-units.')
        ->group(function () {
            Route::patch('/{workUnit}/department-manager', [WorkUnitDepartmentManagerController::class, 'update'])
                ->whereNumber('workUnit')
                ->middleware('permission:manage work units')
                ->name('department-manager.update');
        });

    /*
    |--------------------------------------------------------------------------
    | Technical Office - Unified Complaints
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:technical_office')
        ->prefix('technical-office/complaints')
        ->name('technical-office.complaints.')
        ->group(function () {


            /*
             * Reject a unified complaint and all reports under it.
             */
            Route::patch('/{complaint}/reject', [TechnicalOfficeComplaintController::class, 'reject'])
                ->whereNumber('complaint')
                ->middleware('permission:reject complaints')
                ->name('reject');

            /*
             * Assign the unified complaint to one or more work units.
             */
            Route::post('/{complaint}/assign-work-units', [ComplaintAssignmentController::class, 'store'])
                ->whereNumber('complaint')
                ->middleware('permission:assign complaints to work units')
                ->name('assign-work-units');
        });

    /*
    |--------------------------------------------------------------------------
    | Technical Office - Work Units
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:technical_office')
        ->prefix('technical-office/work-units')
        ->name('technical-office.work-units.')
        ->group(function () {
            Route::get('/', [TechnicalOfficeWorkUnitController::class, 'index'])
                ->middleware('permission:assign complaints to work units')
                ->name('index');
        });

    /*
    |--------------------------------------------------------------------------
    | Department Manager Complaints
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:department_manager')
        ->prefix('department-manager/complaints')
        ->name('department-manager.complaints.')
        ->group(function () {
            Route::get('/', [DepartmentManagerComplaintController::class, 'index'])
                ->middleware('permission:view work unit complaints')
                ->name('index');

            Route::get('/{complaint}', [DepartmentManagerComplaintController::class, 'show'])
                ->whereNumber('complaint')
                ->middleware('permission:view work unit complaints')
                ->name('show');

            Route::patch('/{complaint}/start', [DepartmentManagerComplaintController::class, 'start'])
                ->whereNumber('complaint')
                ->middleware('permission:execute complaints')
                ->name('start');

            Route::patch('/{complaint}/resolve', [DepartmentManagerComplaintController::class, 'resolve'])
                ->whereNumber('complaint')
                ->middleware('permission:resolve complaints')
                ->name('resolve');

            Route::patch('/{complaint}/reject', [DepartmentManagerComplaintController::class, 'reject'])
                ->whereNumber('complaint')
                ->middleware('permission:reject complaints')
                ->name('reject');
        });

    /*
    |--------------------------------------------------------------------------
    | Complaint Statistics
    |--------------------------------------------------------------------------
    */

    Route::get('/complaints/statistics', [ComplaintStatisticsController::class, 'index'])
        ->middleware('permission:view complaint statistics')
        ->name('complaints.statistics.index');
});

    Route::get('/ComplaintCategories',[ComplaintReportController::class,'complaintCategory']);




/*
          * Get unified complaints.
          */
Route::middleware([
    'auth:sanctum',
    'password.changed',
    'role:technical_office|citizen',
])->get(
    '/unified-complaints',
    [TechnicalOfficeComplaintController::class, 'index']
);
