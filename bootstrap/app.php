<?php

use App\Http\Middleware\EnsurePasswordIsChanged;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
            ]);

    })



    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'بيانات غير صالحة.',
                    'data' => null,
                    'errors' => $e->errors(),
                    'status' => 422,
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'data' => null,
                    'errors' => null,
                    'status' => 401,
                ], 401);
            }
        });

        $exceptions->render(function (UnauthorizedException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have the right permissions.',
                    'data' => null,
                    'errors' => null,
                    'status' => 403,
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $previousException = $e->getPrevious();

                if ($previousException instanceof ModelNotFoundException) {
                    $model = class_basename($previousException->getModel());

                    return response()->json([
                        'success' => false,
                        'message' => "{$model} was not found.",
                        'data' => null,
                        'errors' => null,
                        'status' => 404,
                    ], 404);
                }

                // في حال كان رابط الـ API نفسه غير موجود
                return response()->json([
                    'success' => false,
                    'message' => 'The requested endpoint was not found.',
                    'data' => null,
                    'errors' => null,
                    'status' => 404,
                ], 404);
            }
        });

//        $exceptions->render(function (AuthorizationException $e, $request) {
//            if ($request->expectsJson() || $request->is('api/*')) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'You do not have permission to perform this action.',
//                    'data' => null,
//                    'errors' => null,
//                    'status' => 403,
//                ], 403);
//            }
//        });

        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                    'data' => null,
                    'errors' => null,
                    'status' => 403,
                ], 403);
            }
        });

    })->create();
