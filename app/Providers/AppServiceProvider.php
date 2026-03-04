<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Http\Middleware\EnsureApiResponseIsJson;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSettings;

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

        // Ensure the API middleware group always returns JSON errors — wrap non-JSON errors
        // This will convert 401/404 HTML responses to structured JSON for API consumers.
        $this->app->afterResolving('router', function ($router) {
            $this->app['router']->pushMiddlewareToGroup('api', EnsureApiResponseIsJson::class);
        });

        // Load runtime service configuration from DB-backed AppSettings if available.
        // This allows storing API keys/credentials in the database while keeping
        // packages (Socialite, Gorq, etc.) configured via `config('services')`.
        try {
            if (Schema::hasTable('app_settings')) {
                $get = function ($k, $d = null) {
                    $val = AppSettings::getSetting($k, $d);
                    // Log to help debug
                    if (!empty($val)) {
                        \Log::debug('[AppServiceProvider] Loaded setting: ' . $k . ' = ' . (strlen((string)$val) > 50 ? substr((string)$val, 0, 50) . '...' : $val));
                    }
                    return $val;
                };

                // Google
                $gId = $get('google_client_id');
                $gSecret = $get('google_client_secret');
                // Prefer explicit setting; otherwise default to this app's URL + callback
                $gRedirect = $get('google_redirect') ?? (config('app.url') ? rtrim(config('app.url'), '/') . '/api/auth/google/callback' : url('/api/auth/google/callback'));
                if ($gId) {
                    config(['services.google.client_id' => $gId]);
                    \Log::debug('[AppServiceProvider] Set services.google.client_id');
                }
                if ($gSecret) {
                    config(['services.google.client_secret' => $gSecret]);
                    \Log::debug('[AppServiceProvider] Set services.google.client_secret');
                }
                if ($gRedirect) {
                    config(['services.google.redirect' => $gRedirect]);
                    \Log::debug('[AppServiceProvider] Set services.google.redirect: ' . $gRedirect);
                }

                // GitHub
                $hId = $get('github_client_id');
                $hSecret = $get('github_client_secret');
                $hRedirect = $get('github_redirect') ?? (config('app.url') ? rtrim(config('app.url'), '/') . '/api/auth/github/callback' : url('/api/auth/github/callback'));
                if ($hId) {
                    config(['services.github.client_id' => $hId]);
                    \Log::debug('[AppServiceProvider] Set services.github.client_id');
                }
                if ($hSecret) {
                    config(['services.github.client_secret' => $hSecret]);
                    \Log::debug('[AppServiceProvider] Set services.github.client_secret');
                }
                if ($hRedirect) {
                    config(['services.github.redirect' => $hRedirect]);
                    \Log::debug('[AppServiceProvider] Set services.github.redirect: ' . $hRedirect);
                }

                // Gorq
                $gorqKey = $get('gorq_api_key');
                $gorqBase = $get('gorq_base_url');
                if ($gorqKey) config(['services.gorq.key' => $gorqKey]);
                if ($gorqBase) config(['services.gorq.base_url' => $gorqBase]);

                // Turnstile / reCAPTCHA
                $turnEnabled = $get('turnstile_enabled');
                if (!is_null($turnEnabled)) config(['services.turnstile.enabled' => boolval($turnEnabled)]);
                $turnSite = $get('turnstile_site_key');
                $turnSecret = $get('turnstile_secret');
                if ($turnSite) config(['services.turnstile.site_key' => $turnSite]);
                if ($turnSecret) config(['services.turnstile.secret' => $turnSecret]);

                $recEnabled = $get('recaptcha_enabled');
                if (!is_null($recEnabled)) config(['services.recaptcha.enabled' => boolval($recEnabled)]);
                $recSite = $get('recaptcha_site_key');
                $recSecret = $get('recaptcha_secret');
                if ($recSite) config(['services.recaptcha.site_key' => $recSite]);
                if ($recSecret) config(['services.recaptcha.secret' => $recSecret]);

                // CORS allowed origins (stored as JSON array in settings)
                $allowed = $get('allowed_origins');
                if ($allowed) {
                    if (is_string($allowed)) {
                        $decoded = json_decode($allowed, true);
                        $allowedArr = is_array($decoded) ? $decoded : [$allowed];
                    } elseif (is_array($allowed)) {
                        $allowedArr = $allowed;
                    } else {
                        $allowedArr = [];
                    }

                    if (!empty($allowedArr)) {
                        config(['cors.allowed_origins' => $allowedArr]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep boot resilient during first-run when DB isn't ready
        }
    }
}
