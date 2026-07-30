<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DatabaseSetupCheck;
use App\Http\Controllers\Concerns\InstallationState;
use App\Models\AppSettings;
use App\Models\AdminCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class SetupController extends Controller
{
    use DatabaseSetupCheck;
    use InstallationState;

    /**
     * Show setup page or redirect if already installed
     */
    public function index()
    {
        if (! $this->isApplicationInstalled()) {
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
            $this->clearInstallerCaches();

            $this->refreshDatabaseConnection();
            Artisan::call('migrate', ['--force' => true]);

            if ($this->isDatabaseReady()) {
                return redirect()->route('setup.index')->with('success', 'Migration successful. You may now login or continue setup.');
            }

            return redirect()->route('setup.db-check')->with('error', 'Migrations finished but some tables are still missing. Please check your DB connection and credentials.');
        } catch (\Throwable $e) {
            report($e);
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
            'db_password' => 'nullable|string',
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
            // Remove stale cached configuration before using the submitted
            // database and session settings.
            $this->clearInstallerCaches();
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

            DB::transaction(function () use ($request): void {
                // A previous attempt may have created the admin before a later
                // step failed. Reuse it instead of creating a duplicate.
                $admin = AdminCredential::query()->first();

                if (! $admin) {
                    $admin = AdminCredential::create([
                        'username' => $request->input('admin_username'),
                        'password' => Hash::make($request->input('admin_password')),
                        'role' => 'super_admin',
                        'permissions' => ['*'],
                        'activated_at' => now(),
                        'is_active' => true,
                    ]);
                }

                if (! $admin->is_active) {
                    throw new \RuntimeException('An admin credential already exists but is inactive. Activate it before completing setup.');
                }

                $this->markApplicationInstalled();
            });

            // The database state is committed before attempting the optional
            // .env persistence step.
            $this->persistInstalledEnvironment();

            // Clear caches again after APP_INSTALLED and the final environment
            // values are persisted, before the redirect starts a new request.
            $this->clearInstallerCaches();

            session()->flash('success', 'Setup completed successfully! You can now login.');
            return redirect()->route('admin.login');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Store admin setup (username and password)
     */
    public function storeAdminSetup(Request $request)
    {
        try {
            if (AdminCredential::query()->exists()) {
                $this->markApplicationInstalled();
                $this->persistInstalledEnvironment();
                $this->clearInstallerCaches();

                return redirect()->route('admin.login')->with('success', 'An admin account already exists. You can now login.');
            }
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Unable to check existing admin credentials: ' . $e->getMessage());
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($request): void {
                if (AdminCredential::query()->exists()) {
                    $this->markApplicationInstalled();
                    return;
                }

                AdminCredential::create([
                    'username' => $request->input('username'),
                    'password' => Hash::make($request->input('password')),
                    'role' => 'super_admin',
                    'permissions' => ['*'],
                    'activated_at' => now(),
                    'is_active' => true,
                ]);

                $this->markApplicationInstalled();
            });

            $this->persistInstalledEnvironment();
            $this->clearInstallerCaches();

            session()->flash('success', 'Admin account created successfully! You can now login.');
            return redirect()->route('admin.login');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to create admin account: ' . $e->getMessage());
        }
    }

    /**
     * Persist the installation state in the database.
     */
    private function markApplicationInstalled(): void
    {
        AppSettings::setSetting('app_installed', true, [
            'category' => 'general',
            'description' => 'Application installed flag',
        ]);
    }

    /**
     * Keep APP_INSTALLED in .env in sync when the deployment filesystem allows
     * it. The database flag remains authoritative when this is not possible.
     */
    private function persistInstalledEnvironment(): void
    {
        try {
            if (! $this->setEnvValue('APP_INSTALLED', 'true')) {
                Log::warning('Unable to persist APP_INSTALLED=true to the environment file; database installation state remains authoritative.');
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to persist APP_INSTALLED=true to the environment file.', [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Clear all Laravel caches that can preserve pre-install configuration.
     */
    private function clearInstallerCaches(): void
    {
        foreach (['config:clear', 'cache:clear', 'route:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $e) {
                Log::warning('Installer cache cleanup command failed.', [
                    'command' => $command,
                    'exception' => $e,
                ]);
            }
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
        $envContent .= "APP_INSTALLED=false\n";

        if (File::put(base_path('.env'), $envContent) === false) {
            throw new \RuntimeException('Unable to write the environment file. Check that the application directory is writable.');
        }
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
    private function setEnvValue(string $key, string $value): bool
    {
        $path = base_path('.env');
        $line = $key . '=' . $value;
        if (!File::exists($path)) {
            return File::put($path, $line . PHP_EOL) !== false;
        }

        $contents = File::get($path);
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=.*$/m', $contents)) {
            $contents = preg_replace('/^' . preg_quote($key, '/') . '\s*=.*$/m', $line, $contents);
        } else {
            $contents .= PHP_EOL . $line . PHP_EOL;
        }

        return File::put($path, $contents) !== false;
    }
}
