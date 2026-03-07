<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\AdminCredential;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    /**
     * Display settings dashboard
     */
    public function index()
    {
        $settings = AppSettings::all()->groupBy('category');
        $adminCredentials = AdminCredential::all();

        return view('settings.index', compact('settings', 'adminCredentials'));
    }

    /**
     * Set or replace a value in the .env file. Creates .env if missing.
     */
    private function setEnvValue(string $key, string $value)
    {
        $path = base_path('.env');
        $line = $key . '=' . $value;
        if (!File::exists($path)) {
            File::put($path, $line . PHP_EOL);
            return;
        }

        $contents = File::get($path);
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=.*$/m', $contents)) {
            $contents = preg_replace('/^' . preg_quote($key, '/') . '\s*=.*$/m', $line, $contents);
        } else {
            $contents .= PHP_EOL . $line . PHP_EOL;
        }

        File::put($path, $contents);
    }

    /**
     * Display mail settings page
     */
    public function mailSettings()
    {
        $mailSettings = AppSettings::byCategory('mail')->get();
        return view('settings.mail', compact('mailSettings'));
    }

    /**
     * Update mail settings
     */
    public function updateMailSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_mailer' => 'required|in:log,smtp,sendmail,mailgun,postmark,ses,resend',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|in:tls,ssl,null',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
            'mail_to_address' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            AppSettings::setSetting('mail_mailer', $request->input('mail_mailer'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Mail driver',
            ]);

            AppSettings::setSetting('mail_host', $request->input('mail_host'), [
                'category' => 'mail',
                'is_encrypted' => true,
                'description' => 'Mail server host',
            ]);

            AppSettings::setSetting('mail_port', $request->input('mail_port'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Mail server port',
            ]);

            AppSettings::setSetting('mail_username', $request->input('mail_username'), [
                'category' => 'mail',
                'is_encrypted' => true,
                'description' => 'Mail username',
            ]);

            // only update password if a new one was provided, otherwise keep existing
            if ($request->filled('mail_password')) {
                AppSettings::setSetting('mail_password', $request->input('mail_password'), [
                    'category' => 'mail',
                    'is_encrypted' => true,
                    'description' => 'Mail password',
                ]);
            }

            AppSettings::setSetting('mail_encryption', $request->input('mail_encryption'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Mail encryption',
            ]);

            AppSettings::setSetting('mail_from_address', $request->input('mail_from_address'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Mail from address',
            ]);

            AppSettings::setSetting('mail_from_name', $request->input('mail_from_name'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Mail from name',
            ]);

            AppSettings::setSetting('mail_to_address', $request->input('mail_to_address'), [
                'category' => 'mail',
                'is_encrypted' => false,
                'description' => 'Recipient email for contact forms',
            ]);

            session()->flash('success', 'Mail settings updated successfully!');
            return redirect()->route('settings.mail');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update mail settings: ' . $e->getMessage());
        }
    }

    /**
     * Send a test email to verify SMTP configuration
     */
    public function testMailSettings(Request $request)
    {
        try {
            $toAddress = AppSettings::getSetting('mail_to_address')
                ?: config('mail.from.address', env('MAIL_FROM_ADDRESS'));
            
            if (!$toAddress) {
                return back()->with('error', 'Please configure a "From Address" or "To Address (Contact Form)" before testing.');
            }

            $fromAddress = AppSettings::getSetting('mail_from_address')
                ?: config('mail.from.address', env('MAIL_FROM_ADDRESS'));
            
            $fromName = AppSettings::getSetting('mail_from_name')
                ?: config('mail.from.name', env('MAIL_FROM_NAME', 'Backend API'));

            $mailDriver = config('mail.default');

            \Log::info('[TestMail] Attempting to send test email', [
                'driver' => $mailDriver,
                'to' => $toAddress,
                'from' => $fromAddress,
            ]);

            // Send a simple test email
            Mail::raw(
                'This is a test email from your Backend API to verify SMTP configuration is working correctly.',
                function ($msg) use ($toAddress, $fromAddress, $fromName) {
                    $msg->to($toAddress)
                        ->from($fromAddress, $fromName)
                        ->subject('[Test] Backend API - SMTP Configuration Test');
                }
            );

            \Log::info('[TestMail] Test email sent successfully');
            return back()->with('success', 'Test email sent successfully to ' . $toAddress . '! Check your mailbox (including spam folder).');
        } catch (\Exception $e) {
            \Log::error('[TestMail] Failed to send test email', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Display API keys settings page
     */
    public function apiSettings()
    {
        $apiSettings = AppSettings::byCategory('api')->get();
        return view('settings.api', compact('apiSettings'));
    }

    /**
     * Add/Update API key
     */
    public function storeApiKey(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key_name' => 'required|string|max:255',
            'key_value' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $keyName = 'api_' . str(str_replace([' ', '-', '_'], '_', $request->input('key_name')))->lower();

            AppSettings::setSetting($keyName, $request->input('key_value'), [
                'category' => 'api',
                'is_encrypted' => true,
                'description' => $request->input('description', $request->input('key_name')),
            ]);

            session()->flash('success', 'API key saved successfully!');
            return redirect()->route('settings.api');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to save API key: ' . $e->getMessage());
        }
    }

    /**
     * Delete API key
     */
    public function deleteApiKey($id)
    {
        try {
            AppSettings::find($id)->delete();
            session()->flash('success', 'API key deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete API key: ' . $e->getMessage());
        }

        return redirect()->route('settings.api');
    }

    /**
     * Display auth settings page
     */
    public function authSettings()
    {
        $authSettings = AppSettings::byCategory('auth')->get();
        return view('settings.auth', compact('authSettings'));
    }

    /**
     * Update auth settings
     */
    public function updateAuthSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'github_client_id' => 'nullable|string',
            'github_client_secret' => 'nullable|string',
            'turnstile_enabled' => 'nullable|boolean',
            'turnstile_site_key' => 'nullable|string',
            'turnstile_secret' => 'nullable|string',
            'recaptcha_enabled' => 'nullable|boolean',
            'recaptcha_site_key' => 'nullable|string',
            'recaptcha_secret' => 'nullable|string',
            'google_maps_api_key' => 'nullable|string',
            'gorq_api_key' => 'nullable|string',
            'gorq_base_url' => 'nullable|url',
            'gorq_default_model' => 'nullable|string',
            'frontend_url' => 'nullable|url',
            'allowed_origins' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // OAuth Settings
            $this->storeSetting('google_client_id', $request->input('google_client_id'), 'auth', 'Google Client ID');
            $this->storeSetting('google_client_secret', $request->input('google_client_secret'), 'auth', 'Google Client Secret', true);
            // Optional explicit redirect URIs
            $this->storeSetting('google_redirect', $request->input('google_redirect'), 'auth', 'Google Redirect URI');
            $this->storeSetting('github_client_id', $request->input('github_client_id'), 'auth', 'GitHub Client ID');
            $this->storeSetting('github_client_secret', $request->input('github_client_secret'), 'auth', 'GitHub Client Secret', true);
            $this->storeSetting('github_redirect', $request->input('github_redirect'), 'auth', 'GitHub Redirect URI');

            // Turnstile Settings
            $this->storeSetting('turnstile_enabled', $request->has('turnstile_enabled'), 'auth', 'Turnstile Enabled');
            $this->storeSetting('turnstile_site_key', $request->input('turnstile_site_key'), 'auth', 'Turnstile Site Key');
            $this->storeSetting('turnstile_secret', $request->input('turnstile_secret'), 'auth', 'Turnstile Secret', true);

            // reCAPTCHA Settings
            $this->storeSetting('recaptcha_enabled', $request->has('recaptcha_enabled'), 'auth', 'reCAPTCHA Enabled');
            $this->storeSetting('recaptcha_site_key', $request->input('recaptcha_site_key'), 'auth', 'reCAPTCHA Site Key');
            $this->storeSetting('recaptcha_secret', $request->input('recaptcha_secret'), 'auth', 'reCAPTCHA Secret Key', true);

            // Maps & AI Settings
            $this->storeSetting('google_maps_api_key', $request->input('google_maps_api_key'), 'api', 'Google Maps API Key', true);
            $this->storeSetting('gorq_api_key', $request->input('gorq_api_key'), 'api', 'Gorq API Key', true);
            $this->storeSetting('gorq_base_url', $request->input('gorq_base_url'), 'api', 'Gorq Base URL');
            $this->storeSetting('gorq_default_model', $request->input('gorq_default_model'), 'api', 'Gorq Default Model');

            // Frontend Settings
            $this->storeSetting('frontend_url', $request->input('frontend_url'), 'general', 'Frontend URL');

            // Allowed origins: accept newline or comma separated input and store as JSON array
            $allowed = [];
            if ($request->filled('allowed_origins')) {
                $raw = $request->input('allowed_origins');
                $parts = preg_split('/[\r\n,]+/', $raw);
                foreach ($parts as $p) {
                    $t = trim($p);
                    if ($t !== '') $allowed[] = $t;
                }
            }
            AppSettings::setSetting('allowed_origins', $allowed, [
                'category' => 'general',
                'setting_type' => 'json',
                'is_encrypted' => false,
                'description' => 'CORS allowed origins',
            ]);

            // Always persist FRONTEND_URL to .env so CORS config can read it
            try {
                if ($request->filled('frontend_url')) {
                    $this->setEnvValue('FRONTEND_URL', $request->input('frontend_url'));
                }
            } catch (\Throwable $e) {
                // Non-fatal: ignore env write errors
            }

            // Optionally persist other selected values into the .env file so
            // external packages that read env() (e.g. Socialite) can pick them up.
            if ($request->has('write_env')) {
                try {
                    if ($request->filled('google_client_id')) $this->setEnvValue('GOOGLE_CLIENT_ID', $request->input('google_client_id'));
                    if ($request->filled('google_client_secret')) $this->setEnvValue('GOOGLE_CLIENT_SECRET', $request->input('google_client_secret'));

                    if ($request->filled('github_client_id')) $this->setEnvValue('GITHUB_CLIENT_ID', $request->input('github_client_id'));
                    if ($request->filled('github_client_secret')) $this->setEnvValue('GITHUB_CLIENT_SECRET', $request->input('github_client_secret'));

                    if ($request->filled('google_redirect')) $this->setEnvValue('GOOGLE_REDIRECT', $request->input('google_redirect'));
                    if ($request->filled('github_redirect')) $this->setEnvValue('GITHUB_REDIRECT', $request->input('github_redirect'));

                    if ($request->filled('gorq_api_key')) $this->setEnvValue('GORQ_API_KEY', $request->input('gorq_api_key'));
                    if ($request->filled('gorq_base_url')) $this->setEnvValue('GORQ_BASE_URL', $request->input('gorq_base_url'));

                    $this->setEnvValue('TURNSTILE_ENABLED', $request->has('turnstile_enabled') ? 'true' : 'false');
                    if ($request->filled('turnstile_site_key')) $this->setEnvValue('TURNSTILE_SITE_KEY', $request->input('turnstile_site_key'));
                    if ($request->filled('turnstile_secret')) $this->setEnvValue('TURNSTILE_SECRET', $request->input('turnstile_secret'));

                    $this->setEnvValue('RECAPTCHA_ENABLED', $request->has('recaptcha_enabled') ? 'true' : 'false');
                    if ($request->filled('recaptcha_site_key')) $this->setEnvValue('RECAPTCHA_SITE_KEY', $request->input('recaptcha_site_key'));
                    if ($request->filled('recaptcha_secret')) $this->setEnvValue('RECAPTCHA_SECRET', $request->input('recaptcha_secret'));
                } catch (\Throwable $e) {
                    // Non-fatal: ignore env write errors
                }
            }

            session()->flash('success', 'Auth & API settings updated successfully!');
            return redirect()->route('settings.auth');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update auth settings: ' . $e->getMessage());
        }
    }

    /**
     * Display admin credentials management page
     */
    public function adminCredentials()
    {
        $credentials = AdminCredential::all();
        return view('settings.admin-credentials', compact('credentials'));
    }

    /**
     * Add new admin credential
     */
    public function storeAdminCredential(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:admin_credentials,username',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:super_admin,admin,manager',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            AdminCredential::create([
                'username' => $request->input('username'),
                'password' => Hash::make($request->input('password')),
                'role' => $request->input('role'),
                'permissions' => ['*'],
                'activated_at' => now(),
                'is_active' => true,
            ]);

            session()->flash('success', 'Admin credential created successfully!');
            return redirect()->route('settings.admin-credentials');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create admin credential: ' . $e->getMessage());
        }
    }

    /**
     * Update admin credential
     */
    public function updateAdminCredential(Request $request, AdminCredential $credential)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:super_admin,admin,manager',
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $credential->role = $request->input('role');
            $credential->is_active = $request->input('is_active', false);

            if ($request->filled('password')) {
                $credential->password = Hash::make($request->input('password'));
            }

            $credential->save();

            session()->flash('success', 'Admin credential updated successfully!');
            return redirect()->route('settings.admin-credentials');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update admin credential: ' . $e->getMessage());
        }
    }

    /**
     * Delete admin credential
     */
    public function deleteAdminCredential(AdminCredential $credential)
    {
        try {
            $credential->delete();
            session()->flash('success', 'Admin credential deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete admin credential: ' . $e->getMessage());
        }

        return redirect()->route('settings.admin-credentials');
    }


    /**
     * Display cache management page
     */
    public function cacheManagement()
    {
        return view('settings.cache');
    }

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');
            session()->flash('success', 'Application cache cleared successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear cache: ' . $e->getMessage());
        }

        return redirect()->route('settings.cache');
    }

    /**
     * Clear configuration cache
     */
    public function clearConfigCache()
    {
        try {
            \Artisan::call('config:clear');
            session()->flash('success', 'Configuration cache cleared successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear config cache: ' . $e->getMessage());
        }

        return redirect()->route('settings.cache');
    }

    /**
     * Clear route cache
     */
    public function clearRouteCache()
    {
        try {
            \Artisan::call('route:clear');
            session()->flash('success', 'Route cache cleared successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear route cache: ' . $e->getMessage());
        }

        return redirect()->route('settings.cache');
    }

    /**
     * Helper to store settings
     */
    private function storeSetting($key, $value, $category, $description, $encrypt = false)
    {
        if (filled($value)) {
            AppSettings::setSetting($key, $value, [
                'category' => $category,
                'is_encrypted' => $encrypt,
                'description' => $description,
            ]);
        }
    }

    /**
     * Display subscription plans management page
     */
    public function subscriptionPlans()
    {
        $plans = SubscriptionPlan::all();
        return view('settings.subscription-plans', compact('plans'));
    }

    /**
     * Store a new subscription plan
     */
    public function storeSubscriptionPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|in:month,year',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $features = [];
            if ($request->filled('features')) {
                $features = array_map('trim', explode("\n", $request->input('features')));
                $features = array_filter($features, function($feature) {
                    return !empty($feature);
                });
            }

            SubscriptionPlan::create([
                'name' => $request->input('name'),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'price' => $request->input('price'),
                'currency' => strtoupper($request->input('currency')),
                'interval' => $request->input('interval'),
                'trial_days' => $request->input('trial_days', 0),
                'features' => $features,
                'is_active' => $request->input('is_active', true),
            ]);

            session()->flash('success', 'Subscription plan created successfully!');
            return redirect()->route('subscription-plans');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription plan: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing subscription plan
     */
    public function updateSubscriptionPlan(Request $request, SubscriptionPlan $plan)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $plan->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|in:month,year',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $features = [];
            if ($request->filled('features')) {
                $features = array_map('trim', explode("\n", $request->input('features')));
                $features = array_filter($features, function($feature) {
                    return !empty($feature);
                });
            }

            $plan->update([
                'name' => $request->input('name'),
                'slug' => $request->input('slug'),
                'description' => $request->input('description'),
                'price' => $request->input('price'),
                'currency' => strtoupper($request->input('currency')),
                'interval' => $request->input('interval'),
                'trial_days' => $request->input('trial_days', 0),
                'features' => $features,
                'is_active' => $request->input('is_active', true),
            ]);

            session()->flash('success', 'Subscription plan updated successfully!');
            return redirect()->route('subscription-plans');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update subscription plan: ' . $e->getMessage());
        }
    }

    /**
     * Delete a subscription plan
     */
    public function deleteSubscriptionPlan(SubscriptionPlan $plan)
    {
        try {
            // Check if plan has active subscriptions
            if ($plan->payments()->where('status', 'active')->exists()) {
                return back()->with('error', 'Cannot delete plan with active subscriptions. Please deactivate the plan instead.');
            }

            $plan->delete();
            session()->flash('success', 'Subscription plan deleted successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete subscription plan: ' . $e->getMessage());
        }

        return redirect()->route('subscription-plans');
    }
}
