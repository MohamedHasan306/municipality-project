<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تغيير كلمة المرور قبل استخدام النظام.',
                'data' => null,
                'errors' => [
                    'must_change_password' => true,
                ],
            ], 403);
        }

        return $next($request);
    }
}
