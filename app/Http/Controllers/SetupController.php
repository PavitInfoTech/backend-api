<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DatabaseSetupCheck;
use App\Models\AppSettings;
use App\Models\AdminCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class SetupController extends Controller
{
    use DatabaseSetupCheck;

    /**
     * Show setup page or redirect if already installed
     */
    public function index()
    {
        $envExists = $this->isEnvExists();
        $appInstalled = env('APP_INSTALLED');

        if (! $envExists || ! $appInstalled || $appInstalled === 'false') {
            return view('setup.index');
        }

        if (! $this->isDatabaseReady()) {
            return view('setup.db-check');
        }

        try {
            if (AdminCredential::active()->exists()) {
                return redirect()->route('admin.login');
            }

            return view('setup.admin-setup');
        } catch (\Throwable $e) {
            return view('setup.db-check')->with('error', 'Database check failed: ' . $e->getMessage());
        }
    }

    /**
     * Display database check / re-run migration view
     */
    public function dbCheck()
    {
        if (! $this->isEnvExists()) {
            return redirect()->route('setup.index');
        }

        if ($this->isDatabaseReady()) {
            return redirect()->route('setup.index')->with('success', 'Database tables already exist.');
        }

        return view('setup.db-check');
    }

    /**
     * Run migrations if tables are missing
     */
    public function runMigrations(Request $request)
    {
        if (! $this->isEnvExists()) {
            return redirect()->route('setup.index')->with('error', 'Environment file not found. Please complete setup first.');
        }

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $this->refreshDatabaseConnection();
            Artisan::call('migrate', ['--force' => true]);

            if ($this->isDatabaseReady()) {
                return redirect()->route('setup.index')->with('success', 'Migration successful. You may now login or continue setup.');
            }

            return redirect()->route('setup.db-check')->with('error', 'Migrations finished but some tables are still missing. Please check your DB connection and credentials.');
        } catch (\Exception $e) {
            return redirect()->route('setup.db-check')->with('error', 'Migration failed: ' . $e->getMessage());
        }
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
            // Create .env file with user values
            $this->createEnvFile($request);

            // Reload config and DB connection using supplied credentials so migrate uses this new DB.
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            $this->refreshDatabaseConnection([
                'driver' => 'mysql',
                'host' => $request->input('db_host'),
                'port' => $request->input('db_port'),
                'database' => $request->input('db_database'),
                'username' => $request->input('db_username'),
                'password' => $request->input('db_password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ]);

            // Run migrations in the newly configured database
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

            // Persist APP_INSTALLED=true to .env so restarts honour installed state
            try {
                $this->setEnvValue('APP_INSTALLED', 'true');
            } catch (\Throwable $e) {
                // ignore non-fatal
            }

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

            // Persist APP_INSTALLED=true to .env so restarts honour installed state
            try {
                $this->setEnvValue('APP_INSTALLED', 'true');
            } catch (\Throwable $e) {
                // ignore
            }

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
}
