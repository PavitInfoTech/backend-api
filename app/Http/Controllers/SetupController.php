<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\AdminCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class SetupController extends Controller
{
    /**
     * Show setup page or redirect if already installed
     */
    public function index()
    {
        // If .env does not exist OR the application is not marked installed,
        // show the full setup form so the operator can provide DB and admin
        // credentials. We avoid querying the database here because it may not
        // be initialized yet.
        $envExists = $this->isEnvExists();
        $appInstalled = env('APP_INSTALLED');

        if (! $envExists || !$appInstalled || $appInstalled === 'false') {
            return view('setup.index');
        }

        // At this point the environment reports installed; safe to check DB.
        if (AdminCredential::active()->exists()) {
            return redirect()->route('login');
        }

        return view('setup.admin-setup');
    }

    /**
     * Store environment settings
     */
    public function storeEnv(Request $request)
    {
        // Avoid database-dependent validation (like `unique:`) here because the
        // database may not yet exist during initial setup. We'll perform basic
        // format checks and create the DB/migrate later.
        $validator = Validator::make($request->all(), [
            'app_name' => 'required|string',
            'app_url' => 'required|url',
            'db_host' => 'required|string',
            'db_port' => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'required|string',
            'admin_username' => 'required|string',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Create .env file
            $this->createEnvFile($request);

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Create admin credential
            AdminCredential::create([
                'username' => $request->input('admin_username'),
                'password' => Hash::make($request->input('admin_password')),
                'role' => 'super_admin',
                'permissions' => ['*'],
                'activated_at' => now(),
                'is_active' => true,
            ]);

            // Store initial settings
            AppSettings::setSetting('app_installed', true, [
                'category' => 'general',
                'description' => 'Application installed flag',
            ]);

            session()->flash('success', 'Setup completed successfully! You can now login.');
            return redirect()->route('admin.login');
        } catch (\Exception $e) {
            return back()->with('error', 'Setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Store admin setup (username and password)
     */
    public function storeAdminSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:admin_credentials,username',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            AdminCredential::create([
                'username' => $request->input('username'),
                'password' => Hash::make($request->input('password')),
                'role' => 'super_admin',
                'permissions' => ['*'],
                'activated_at' => now(),
                'is_active' => true,
            ]);

            AppSettings::setSetting('app_installed', true, [
                'category' => 'general',
                'description' => 'Application installed flag',
            ]);

            session()->flash('success', 'Admin account created successfully! You can now login.');
            return redirect()->route('admin.login');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create admin account: ' . $e->getMessage());
        }
    }

    /**
     * Create .env file
     */
    private function createEnvFile(Request $request)
    {
        $envContent = "APP_NAME=\"{$request->input('app_name')}\"\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_KEY=" . $this->generateAppKey() . "\n";
        $envContent .= "APP_DEBUG=false\n";
        $envContent .= "APP_URL={$request->input('app_url')}\n\n";

        $envContent .= "# Database\n";
        $envContent .= "DB_CONNECTION=mysql\n";
        $envContent .= "DB_HOST={$request->input('db_host')}\n";
        $envContent .= "DB_PORT={$request->input('db_port')}\n";
        $envContent .= "DB_DATABASE={$request->input('db_database')}\n";
        $envContent .= "DB_USERNAME={$request->input('db_username')}\n";
        $envContent .= "DB_PASSWORD={$request->input('db_password')}\n\n";

        $envContent .= "# Cache and Session\n";
        $envContent .= "CACHE_DRIVER=file\n";
        $envContent .= "SESSION_DRIVER=file\n";
        $envContent .= "QUEUE_CONNECTION=sync\n\n";

        $envContent .= "# Mail\n";
        $envContent .= "MAIL_MAILER=log\n";
        $envContent .= "MAIL_HOST=localhost\n";
        $envContent .= "MAIL_PORT=1025\n";
        $envContent .= "MAIL_USERNAME=\n";
        $envContent .= "MAIL_PASSWORD=\n";
        $envContent .= "MAIL_ENCRYPTION=null\n";
        $envContent .= "MAIL_FROM_ADDRESS=noreply@example.com\n";
        $envContent .= "MAIL_FROM_NAME=\"{$request->input('app_name')}\"\n\n";

        $envContent .= "# API Settings\n";
        $envContent .= "API_PREFIX_FALLBACK=true\n";
        $envContent .= "APP_INSTALLED=false\n";

        File::put(base_path('.env'), $envContent);
    }

    /**
     * Generate application key
     */
    private function generateAppKey()
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Check if .env file exists
     */
    private function isEnvExists()
    {
        return File::exists(base_path('.env'));
    }
}
