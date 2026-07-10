<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinutes(1,5)
                ->by($email . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again later',
                        'data' => null,
                        'errors' => [
                            'retry_after' => $headers['Retry-After'] ?? null,
                        ],
                        'status' => 429,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(3)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many reset_password attempts. Please try again later.',
                        'data' => null,
                        'errors' => [
                            'retry_after' => $headers['Retry-After'] ?? null,
                        ],
                        'status' => 429,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'تم تجاوز عدد محاولات التحقق من الرمز. حاول لاحقًا.',
                        'data' => null,
                        'errors' => [
                            'retry_after' => $headers['Retry-After'] ?? null,
                        ],
                        'status' => 429,
                    ], 429, $headers);
                });
        });
    }
}
