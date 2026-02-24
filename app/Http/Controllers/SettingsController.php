<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\AdminCredential;
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

            AppSettings::setSetting('mail_password', $request->input('mail_password'), [
                'category' => 'mail',
                'is_encrypted' => true,
                'description' => 'Mail password',
            ]);

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

            session()->flash('success', 'Mail settings updated successfully!');
            return redirect()->route('settings.mail');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update mail settings: ' . $e->getMessage());
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
            'turnstile_enabled' => 'boolean',
            'turnstile_site_key' => 'nullable|string',
            'turnstile_secret' => 'nullable|string',
            'recaptcha_enabled' => 'boolean',
            'recaptcha_site_key' => 'nullable|string',
            'recaptcha_secret' => 'nullable|string',
            'google_maps_api_key' => 'nullable|string',
            'gorq_api_key' => 'nullable|string',
            'gorq_base_url' => 'nullable|url',
            'gorq_default_model' => 'nullable|string',
            'frontend_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // OAuth Settings
            $this->storeSetting('google_client_id', $request->input('google_client_id'), 'auth', 'Google Client ID');
            $this->storeSetting('google_client_secret', $request->input('google_client_secret'), 'auth', 'Google Client Secret', true);
            $this->storeSetting('github_client_id', $request->input('github_client_id'), 'auth', 'GitHub Client ID');
            $this->storeSetting('github_client_secret', $request->input('github_client_secret'), 'auth', 'GitHub Client Secret', true);

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

            // Optionally persist selected values into the .env file so
            // external packages that read env() (e.g. Socialite) can pick them up.
            if ($request->has('write_env')) {
                try {
                    if ($request->filled('google_client_id')) $this->setEnvValue('GOOGLE_CLIENT_ID', $request->input('google_client_id'));
                    if ($request->filled('google_client_secret')) $this->setEnvValue('GOOGLE_CLIENT_SECRET', $request->input('google_client_secret'));

                    if ($request->filled('github_client_id')) $this->setEnvValue('GITHUB_CLIENT_ID', $request->input('github_client_id'));
                    if ($request->filled('github_client_secret')) $this->setEnvValue('GITHUB_CLIENT_SECRET', $request->input('github_client_secret'));

                    if ($request->filled('frontend_url')) {
                        $this->setEnvValue('FRONTEND_URL', $request->input('frontend_url'));
                        $gRedirect = rtrim($request->input('frontend_url'), '/') . '/api/auth/google/callback';
                        $hRedirect = rtrim($request->input('frontend_url'), '/') . '/api/auth/github/callback';
                        $this->setEnvValue('GOOGLE_REDIRECT', $gRedirect);
                        $this->setEnvValue('GITHUB_REDIRECT', $hRedirect);
                    }

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
}
