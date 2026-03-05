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
                    // Log to help debug, but avoid converting arrays directly to string which triggers
                    // "Array to string conversion" warnings while booting (and can lead to 500s).
                    if (!empty($val)) {
                        $display = is_array($val) ? json_encode($val) : (string) $val;
                        if (strlen($display) > 50) {
                            $display = substr($display, 0, 50) . '...';
                        }
                        \Log::debug('[AppServiceProvider] Loaded setting: ' . $k . ' = ' . $display);
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

                // CORS allowed origins (stored as JSON array in settings).
                // Merge saved origins with fallback to FRONTEND_URL and API_DOMAIN from env.
                $allowed = $get('allowed_origins');
                $allowedArr = [];
                
                // Start with DB-saved origins if they exist
                if ($allowed) {
                    if (is_string($allowed)) {
                        $decoded = json_decode($allowed, true);
                        $allowedArr = is_array($decoded) ? $decoded : [$allowed];
                    } elseif (is_array($allowed)) {
                        $allowedArr = $allowed;
                    }
                }
                
                // Always include FRONTEND_URL and API_DOMAIN as fallbacks
                $fallbacks = array_filter([
                    env('FRONTEND_URL'),
                    env('API_DOMAIN'),
                    'http://localhost:3000', // dev default
                ]);
                $allowedArr = array_unique(array_merge($allowedArr, $fallbacks));
                
                if (!empty($allowedArr)) {
                    config(['cors.allowed_origins' => array_values($allowedArr)]);
                    \Log::debug('[AppServiceProvider] CORS allowed_origins set to: ' . json_encode(array_values($allowedArr)));
                }

                // Mail configuration from AppSettings
                $mailMailer = $get('mail_mailer');
                if ($mailMailer) {
                    config(['mail.default' => $mailMailer]);
                    \Log::debug('[AppServiceProvider] Set mail.default to: ' . $mailMailer);
                }

                $mailFromAddr = $get('mail_from_address');
                $mailFromName = $get('mail_from_name');
                if ($mailFromAddr || $mailFromName) {
                    config(['mail.from' => array_filter([
                        'address' => $mailFromAddr ?: config('mail.from.address'),
                        'name' => $mailFromName ?: config('mail.from.name'),
                    ])]);
                    \Log::debug('[AppServiceProvider] Updated mail.from config');
                }

                // SMTP configuration
                if ($mailMailer === 'smtp') {
                    $smtpHost = $get('mail_host');
                    $smtpPort = $get('mail_port');
                    $smtpUsername = $get('mail_username');
                    $smtpPassword = $get('mail_password');
                    $smtpEncryption = $get('mail_encryption');

                    $smtpConfig = config('mail.mailers.smtp', []);
                    if ($smtpHost) $smtpConfig['host'] = $smtpHost;
                    if ($smtpPort) $smtpConfig['port'] = (int)$smtpPort;
                    if ($smtpUsername) $smtpConfig['username'] = $smtpUsername;
                    if ($smtpPassword) $smtpConfig['password'] = $smtpPassword;
                    if (!is_null($smtpEncryption)) $smtpConfig['encryption'] = $smtpEncryption ?: null;

                    config(['mail.mailers.smtp' => $smtpConfig]);
                    \Log::debug('[AppServiceProvider] Updated SMTP configuration from AppSettings');
                }
    }
}
