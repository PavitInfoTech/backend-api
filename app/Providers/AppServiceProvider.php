<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

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
        // AI rate limiter (per-user or per-IP)
        RateLimiter::for('ai', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            // default: 60 requests per minute per user/ip
            return Limit::perMinute((int) env('AI_RATE_LIMIT_PER_MINUTE', 60))->by($key);
        });
    }
}
