<?php


use App\Http\Controllers\Authentication\CitizenIdentityController;
use App\Http\Controllers\Authentication\AuthenticationController;
use App\Http\Controllers\Authentication\CitizenVerificationController;
use App\Http\Controllers\Authentication\PasswordResetController;
use App\Http\Controllers\EmployeeRoleController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\RolePermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
|
| هذه routes لا تحتاج token.
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
| Authenticated Routes - Before Password Change
|--------------------------------------------------------------------------
|
| هذه routes تحتاج token فقط.
| لا تضع password.changed هنا؛ لأن المستخدم قد يكون مطالبًا بتغيير كلمة المرور.
|
*/

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    /*
     * هذا route يجب أن يبقى خارج password.changed
     * لأن المستخدم الذي يستدعيه يكون must_change_password = true
     */
    Route::post('/change-temporary-password', [AuthenticationController::class, 'changeTemporaryPassword'])
        ->middleware('ability:change-password');

    /*
     * يمكن السماح بالـ logout حتى لو لم يغير كلمة المرور.
     */
    Route::post('/logout', [AuthenticationController::class, 'logout']);

    /*
     * بيانات المستخدم لا تظهر إلا بعد تغيير كلمة المرور.
     */
    Route::get('/me', [AuthenticationController::class, 'me'])
        ->middleware('password.changed');
});


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
|
| كل routes النظام الأساسية هنا.
| المستخدم يجب أن يكون:
| 1. authenticated
| 2. غيّر كلمة المرور المؤقتة إن وجدت
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
    | Employees - Municipality Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('municipality/employees')->group(function () {


        Route::post('/{employee}/assign-role', [EmployeeRoleController::class, 'assignRole'])
            ->middleware('permission:assign roles to employees');
    });


    /*
    |--------------------------------------------------------------------------
    | Register Employee - Old Authentication Controller
    |--------------------------------------------------------------------------
    |
    | إذا كنت ما زلت تستخدم registerEmployee داخل AuthenticationController
    | اتركه هنا مؤقتًا.
    | لكن الأفضل لاحقًا نقله بالكامل إلى EmployeeController.
    |
    */

    Route::post('/auth/register-employee', [AuthenticationController::class, 'registerEmployee'])
        ->middleware('permission:manage municipality employees');


    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions - System Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->middleware('permission:manage roles')->group(function () {
        Route::get('/roles', [RolePermissionController::class, 'roles']);
        Route::post('/roles', [RolePermissionController::class, 'storeRole']);
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole']);
        Route::delete('/roles/{role}', [RolePermissionController::class, 'deleteRole']);

        Route::get('/permissions', [RolePermissionController::class, 'permissions']);
        Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions']);
    });
});
