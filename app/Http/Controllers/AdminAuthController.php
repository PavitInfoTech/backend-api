<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DatabaseSetupCheck;
use App\Http\Controllers\Concerns\InstallationState;
use App\Models\AdminCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    use DatabaseSetupCheck;
    use InstallationState;
    /**
     * Show login form
     */
    public function showLogin()
    {
        // If the application is not yet installed, redirect to setup
        if (! $this->isApplicationInstalled()) {
            return redirect()->route('setup.index');
        }

        if (! $this->isDatabaseReady()) {
            return redirect()->route('setup.db-check')->with('error', 'Database setup is incomplete; run migrations first.');
        }

        if (session('admin')) {
            return redirect()->route('settings.index');
        }

        return view('admin.login');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        // Prevent DB access when app isn't installed yet
        if (! $this->isApplicationInstalled()) {
            return redirect()->route('setup.index');
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (! $this->isDatabaseReady()) {
            return redirect()->route('setup.db-check')->with('error', 'Database tables are missing. Migrate database before login.');
        }

        try {
            $credential = AdminCredential::active()
                ->where('username', $request->input('username'))
                ->first();
        } catch (\Throwable $e) {
            return redirect()->route('setup.db-check')->with('error', 'Database query failed: ' . $e->getMessage());
        }

        if (!$credential || !Hash::check($request->input('password'), $credential->password)) {
            return back()->with('error', 'Invalid username or password.');
        }

        // Update last login
        $credential->update(['last_login_at' => now()]);

        // Store in session
        session(['admin' => $credential->id]);

        return redirect()->route('settings.index')->with('success', 'Logged in successfully!');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        session()->forget('admin');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Logged out successfully!');
    }
}
