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
use App\Http\Controllers\ServiceRequests\CitizenServiceController;
use App\Http\Controllers\ServiceRequests\EngineeringOfficeServiceController;
use App\Http\Controllers\ServiceRequests\FieldInspectorDocumentController;
use App\Http\Controllers\ServiceRequests\MayorServiceController;
use App\Http\Controllers\ServiceRequests\SystemAdminServiceController;
use App\Http\Controllers\ServiceRequests\TechnicalOfficeServiceController;
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
            Route::patch('/{complaint}/reject', [TechnicalOfficeComplaintController::class, 'reject'])
                ->whereNumber('complaint')
                ->middleware('permission:reject complaints')
                ->name('reject');

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

    /*
    |--------------------------------------------------------------------------
    | Citizen Municipal Service Requests
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:citizen')
        ->prefix('citizen')
        ->name('citizen.services.')
        ->group(function () {
            Route::get('/services', [CitizenServiceController::class, 'indexServices'])
                ->middleware('permission:submit service request')
                ->name('catalog.index');

            Route::get('/services/{serviceType}', [CitizenServiceController::class, 'showService'])
                ->whereNumber('serviceType')
                ->middleware('permission:submit service request')
                ->name('catalog.show');

            Route::get('/service-requests', [CitizenServiceController::class, 'indexRequests'])
                ->middleware('permission:view own service requests')
                ->name('requests.index');

            Route::post('/service-requests/drafts', [CitizenServiceController::class, 'storeDraft'])
                ->middleware('permission:submit service request')
                ->name('requests.drafts.store');

            Route::get('/service-requests/{serviceRequest}', [CitizenServiceController::class, 'showRequest'])
                ->whereNumber('serviceRequest')
                ->middleware('permission:view own service requests')
                ->name('requests.show');

            Route::patch('/service-requests/{serviceRequest}', [CitizenServiceController::class, 'updateDraft'])
                ->whereNumber('serviceRequest')
                ->middleware('permission:manage own service request drafts')
                ->name('requests.update');

            Route::delete('/service-requests/{serviceRequest}', [CitizenServiceController::class, 'destroyDraft'])
                ->whereNumber('serviceRequest')
                ->middleware('permission:manage own service request drafts')
                ->name('requests.destroy');

            Route::post(
                '/service-requests/{serviceRequest}/attachments',
                [CitizenServiceController::class, 'storeAttachment']
            )
                ->whereNumber('serviceRequest')
                ->middleware('permission:manage own service request drafts')
                ->name('requests.attachments.store');

            Route::get(
                '/service-requests/{serviceRequest}/attachments/{attachment}',
                [CitizenServiceController::class, 'downloadAttachment']
            )
                ->whereNumber(['serviceRequest', 'attachment'])
                ->middleware('permission:view own service requests')
                ->name('requests.attachments.show');

            Route::delete(
                '/service-requests/{serviceRequest}/attachments/{attachment}',
                [CitizenServiceController::class, 'destroyAttachment']
            )
                ->whereNumber(['serviceRequest', 'attachment'])
                ->middleware('permission:manage own service request drafts')
                ->name('requests.attachments.destroy');

            Route::post('/service-requests/{serviceRequest}/submit', [CitizenServiceController::class, 'submit'])
                ->whereNumber('serviceRequest')
                ->middleware('permission:submit service request')
                ->name('requests.submit');

            Route::get(
                '/service-requests/{serviceRequest}/document',
                [CitizenServiceController::class, 'downloadDocument']
            )
                ->whereNumber('serviceRequest')
                ->middleware('permission:download own issued documents')
                ->name('requests.document');
        });

    /*
    |--------------------------------------------------------------------------
    | System Admin - Service Types and Versions
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:system_admin')
        ->prefix('system-admin/service-types')
        ->name('system-admin.service-types.')
        ->group(function () {
            Route::get('/', [SystemAdminServiceController::class, 'index'])
                ->middleware('permission:manage service types')
                ->name('index');

            Route::post('/', [SystemAdminServiceController::class, 'store'])
                ->middleware('permission:manage service types')
                ->name('store');

            Route::get('/{serviceType}', [SystemAdminServiceController::class, 'show'])
                ->whereNumber('serviceType')
                ->middleware('permission:manage service types')
                ->name('show');

            Route::patch('/{serviceType}', [SystemAdminServiceController::class, 'update'])
                ->whereNumber('serviceType')
                ->middleware('permission:manage service types')
                ->name('update');

            Route::post('/{serviceType}/versions', [SystemAdminServiceController::class, 'storeVersion'])
                ->whereNumber('serviceType')
                ->middleware('permission:manage service forms')
                ->name('versions.store');
        });

    /*
    |--------------------------------------------------------------------------
    | Technical Office - Service Requests
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:technical_office', 'permission:review service requests'])
        ->prefix('technical-office/service-requests')
        ->name('technical-office.service-requests.')
        ->group(function () {
            Route::get('/', [TechnicalOfficeServiceController::class, 'index'])
                ->name('index');

            Route::get('/{serviceRequest}', [TechnicalOfficeServiceController::class, 'show'])
                ->whereNumber('serviceRequest')
                ->name('show');

            Route::get(
                '/{serviceRequest}/attachments/{attachment}',
                [TechnicalOfficeServiceController::class, 'downloadAttachment']
            )
                ->whereNumber(['serviceRequest', 'attachment'])
                ->name('attachments.show');

            Route::patch('/{serviceRequest}/start-review', [TechnicalOfficeServiceController::class, 'startReview'])
                ->whereNumber('serviceRequest')
                ->name('start-review');

            Route::patch(
                '/{serviceRequest}/forward-to-engineering',
                [TechnicalOfficeServiceController::class, 'forwardToEngineering']
            )
                ->whereNumber('serviceRequest')
                ->name('forward-to-engineering');

            Route::patch('/{serviceRequest}/reject', [TechnicalOfficeServiceController::class, 'reject'])
                ->whereNumber('serviceRequest')
                ->name('reject');
        });

    /*
    |--------------------------------------------------------------------------
    | Engineering Office - Service Requests
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:engineering_office',
        'permission:engineering approve service requests',
    ])
        ->prefix('engineering-office/service-requests')
        ->name('engineering-office.service-requests.')
        ->group(function () {
            Route::get('/', [EngineeringOfficeServiceController::class, 'index'])
                ->name('index');

            Route::get('/{serviceRequest}', [EngineeringOfficeServiceController::class, 'show'])
                ->whereNumber('serviceRequest')
                ->name('show');

            Route::get(
                '/{serviceRequest}/attachments/{attachment}',
                [EngineeringOfficeServiceController::class, 'downloadAttachment']
            )
                ->whereNumber(['serviceRequest', 'attachment'])
                ->name('attachments.show');

            Route::patch(
                '/{serviceRequest}/forward-to-mayor',
                [EngineeringOfficeServiceController::class, 'forwardToMayor']
            )
                ->whereNumber('serviceRequest')
                ->name('forward-to-mayor');

            Route::patch('/{serviceRequest}/reject', [EngineeringOfficeServiceController::class, 'reject'])
                ->whereNumber('serviceRequest')
                ->name('reject');
        });

    /*
    |--------------------------------------------------------------------------
    | Mayor - Service Requests
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:mayor', 'permission:mayor approve service requests'])
        ->prefix('mayor/service-requests')
        ->name('mayor.service-requests.')
        ->group(function () {
            Route::get('/', [MayorServiceController::class, 'index'])
                ->name('index');

            Route::get('/{serviceRequest}', [MayorServiceController::class, 'show'])
                ->whereNumber('serviceRequest')
                ->name('show');

            Route::get(
                '/{serviceRequest}/attachments/{attachment}',
                [MayorServiceController::class, 'downloadAttachment']
            )
                ->whereNumber(['serviceRequest', 'attachment'])
                ->name('attachments.show');

            Route::patch('/{serviceRequest}/reject', [MayorServiceController::class, 'reject'])
                ->whereNumber('serviceRequest')
                ->name('reject');

            Route::post('/{serviceRequest}/approve-and-issue', [MayorServiceController::class, 'approveAndIssue'])
                ->whereNumber('serviceRequest')
                ->middleware([
                    'permission:issue documents',
                    'permission:sign documents',
                ])
                ->name('approve-and-issue');
        });

    /*
    |--------------------------------------------------------------------------
    | Field Inspector - Document Verification
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:field_inspector', 'permission:verify documents'])
        ->prefix('field-inspector/documents')
        ->name('field-inspector.documents.')
        ->group(function () {
            Route::get('/{verificationCode}/verify', [FieldInspectorDocumentController::class, 'verify'])
                ->where('verificationCode', '[a-f0-9]{64}')
                ->name('verify');

            Route::get('/{verificationCode}/file', [FieldInspectorDocumentController::class, 'file'])
                ->where('verificationCode', '[a-f0-9]{64}')
                ->name('file');
        });
});

Route::get('/ComplaintCategories', [ComplaintReportController::class, 'complaintCategory']);

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

Route::middleware(['auth:sanctum','role:system_admin|municipality_admin'])->group(function () {

    Route::get('employees', [AuthenticationController::class, 'allEmployees']);
});
