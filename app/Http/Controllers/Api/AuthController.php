<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie as CookieFacade;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use App\Mail\EmailVerificationMail;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class AuthController extends ApiController
{
    /**
     * Compute client hash from the request. If the request contains a pre-hashed
     * password (password_hash) return it directly; otherwise compute the
     * client-style sha256 hash from the plaintext password.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $passwordKey
     * @param string $passwordHashKey
     * @return string|null
     */
    protected function clientHashFromRequest(Request $request, string $passwordKey = 'password', string $passwordHashKey = 'password_hash'): ?string
    {
        if ($request->filled($passwordHashKey)) {
            return trim((string) $request->input($passwordHashKey));
        }

        if ($request->filled($passwordKey)) {
            return hash('sha256', (string) $request->input($passwordKey));
        }

        return null;
    }

    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            // Accept either a frontend-provided pre-hash for password or plaintext password
            'password_hash' => 'required_without:password|string|confirmed',
            'password' => 'required_without:password_hash|min:6|confirmed',
        ]);

        if ($v->fails()) {
            return $this->error('Validation failed', 422, $v->errors()->toArray());
        }

        $clientHash = $this->clientHashFromRequest($request);

        $user = User::create([
            'username' => $request->username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? null,
            'email' => $request->email,
            // Re-hash the client-provided hash using a server-side slow hash.
            'password' => Hash::make($clientHash),
        ]);

        // Create a Sanctum personal access token
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success(['user' => $user, 'token' => $token], 'Registered', 201);
    }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required_without:password_hash',
            'password_hash' => 'required_without:password',
        ]);

        if ($v->fails()) {
            return $this->error('Validation failed', 422, $v->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        $clientHash = $this->clientHashFromRequest($request);

        $valid = false;
        if ($clientHash && $user) {
            // We store server-side Argon/Bcrypt of the clientHash, so verify by re-hashing check
            $valid = Hash::check($clientHash, (string) $user->password);
        }

        if (! $user || ! $valid) {
            return $this->error('Invalid credentials', 401);
        }

        // Create a Sanctum personal access token for the user
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success(['user' => $user, 'token' => $token], 'Logged in');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // If the client is not authenticated via the usual means, but has an api_token cookie,
        // attempt to revoke the token represented by that cookie.
        $cookieToken = $request->cookie('api_token');

        // If we have a logged-in user, revoke their current token or all tokens
        if ($user) {
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            } else {
                $user->tokens()->delete();
            }
        }

        // If there was a cookie token provided and the DB stores a hashed token (Sanctum),
        // try to delete it explicitly as well.
        if (! empty($cookieToken)) {
            // Sanctum stores token column as a SHA-256 hash of the plain token
            $hashed = hash('sha256', $cookieToken);
            // Delete any matching personal access token record
            DB::table('personal_access_tokens')->where('token', $hashed)->delete();
        }

        // Clear the cookie from the browser. Also return JSON if requested.
        $cookie = CookieFacade::forget('api_token');

        if ($request->wantsJson() || $request->accepts('application/json')) {
            return $this->success(null, 'Logged out')->withCookie($cookie);
        }

        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $redirectTo = $frontend . '/auth/logout';

        return redirect($redirectTo)->withCookie($cookie);
    }

    public function redirectToGoogle(Request $request)
    {
        // Use Socialite to redirect user to Google for OAuth consent.
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            return $this->error('Unable to create Google redirect', 500, ['error' => $e->getMessage()]);
        }
    }

    // Authenticated social linking
    public function linkToGoogle(Request $request)
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            return $this->error('Unable to create Google redirect', 500, ['error' => $e->getMessage()]);
        }
    }

    public function handleLinkGoogleCallback(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        try {
            $social = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return $this->error('Failed to get user from Google', 400, ['error' => $e->getMessage()]);
        }

        $user->provider_name = 'google';
        $user->provider_id = $social->getId();
        $user->avatar = $social->getAvatar() ?? $user->avatar;
        $user->save();

        return $this->success(['user' => $user], 'Linked Google account');
    }

    public function redirectToGithub(Request $request)
    {
        try {
            return Socialite::driver('github')->redirect();
        } catch (\Exception $e) {
            return $this->error('Unable to create GitHub redirect', 500, ['error' => $e->getMessage()]);
        }
    }

    public function linkToGithub(Request $request)
    {
        try {
            return Socialite::driver('github')->redirect();
        } catch (\Exception $e) {
            return $this->error('Unable to create GitHub redirect', 500, ['error' => $e->getMessage()]);
        }
    }

    public function handleLinkGithubCallback(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        try {
            $social = Socialite::driver('github')->user();
        } catch (\Exception $e) {
            return $this->error('Failed to get user from GitHub', 400, ['error' => $e->getMessage()]);
        }

        $user->provider_name = 'github';
        $user->provider_id = $social->getId();
        $user->avatar = $social->getAvatar() ?? $user->avatar;
        $user->save();

        return $this->success(['user' => $user], 'Linked GitHub account');
    }

    public function handleGithubCallback(Request $request)
    {
        try {
            $social = Socialite::driver('github')->user();
        } catch (\Exception $e) {
            return $this->error('Failed to get user from GitHub', 400, ['error' => $e->getMessage()]);
        }

        $provider = 'github';
        $providerId = $social->getId();
        $email = $social->getEmail();
        $name = $social->getName() ?? $social->getNickname() ?? $email;
        $avatar = $social->getAvatar();

        $user = User::where('provider_name', $provider)->where('provider_id', $providerId)->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $parts = preg_split('/\s+/', trim($name));
            $firstName = $parts[0] ?? null;
            $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

            $preferredUsername = $email ? explode('@', $email)[0] : Str::slug($name);
            $base =
                $preferredUsername ? Str::slug($preferredUsername) : Str::slug($firstName . '-' . ($lastName ?? ''));
            $username = $base;
            $i = 0;
            while (User::where('username', $username)->exists()) {
                $i++;
                $username = $base . $i;
            }

            $user = User::create([
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make(Str::random(24)),
                'provider_name' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
            ]);
        } else {
            $user->provider_name = $user->provider_name ?? $provider;
            $user->provider_id = $user->provider_id ?? $providerId;
            $user->avatar = $avatar ?? $user->avatar;
            $user->save();
        }

        $token = $user->createToken('github')->plainTextToken;

        if ($request->wantsJson() || $request->accepts('application/json')) {
            return $this->success(['user' => $user, 'token' => $token], 'Authenticated via GitHub');
        }

        $minutes = (int) env('SANCTUM_COOKIE_TTL', 60 * 24 * 30);
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $redirectTo = $frontend . '/auth/complete';

        $secure = ! app()->environment('local');
        $sameSite = 'Lax';

        $cookie = cookie('api_token', $token, $minutes, '/', null, $secure, true, false, $sameSite);

        return redirect($redirectTo)->withCookie($cookie);
    }

    public function unlinkProvider(Request $request)
    {
        $request->validate(['provider' => 'required|string']);
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        $provider = $request->input('provider');
        if ($user->provider_name !== $provider) {
            return $this->error('Provider not linked', 400);
        }

        $user->provider_name = null;
        $user->provider_id = null;
        $user->save();

        return $this->success(null, 'Provider unlinked');
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $social = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return $this->error('Failed to get user from Google', 400, ['error' => $e->getMessage()]);
        }

        $provider = 'google';
        $providerId = $social->getId();
        $email = $social->getEmail();
        $name = $social->getName() ?? $social->getNickname() ?? $email;
        $avatar = $social->getAvatar();

        // Try to find existing user by provider id
        $user = User::where('provider_name', $provider)->where('provider_id', $providerId)->first();

        if (! $user && $email) {
            // Try find by email and attach provider info
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            // create user with a random password (not used for OAuth)
            $parts = preg_split('/\s+/', trim($name));
            $firstName = $parts[0] ?? null;
            $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

            $preferredUsername = $email ? explode('@', $email)[0] : Str::slug($name);
            $base =
                $preferredUsername ? Str::slug($preferredUsername) : Str::slug($firstName . '-' . ($lastName ?? ''));
            $username = $base;
            $i = 0;
            while (User::where('username', $username)->exists()) {
                $i++;
                $username = $base . $i;
            }

            $user = User::create([
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make(Str::random(24)),
                'provider_name' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
            ]);
        } else {
            // update provider fields if missing
            $user->provider_name = $user->provider_name ?? $provider;
            $user->provider_id = $user->provider_id ?? $providerId;
            $user->avatar = $avatar ?? $user->avatar;
            $user->save();
        }

        $token = $user->createToken('google')->plainTextToken;

        // If the caller expects JSON (API client), return token in JSON.
        if ($request->wantsJson() || $request->accepts('application/json')) {
            return $this->success(['user' => $user, 'token' => $token], 'Authenticated via Google');
        }

        // Otherwise assume web/browser flow: set a secure, HttpOnly cookie and redirect to the SPA so the token
        // never appears in the URL. Cookie life in minutes (default 30 days): SANCTUM_COOKIE_TTL
        $minutes = (int) env('SANCTUM_COOKIE_TTL', 60 * 24 * 30);
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $redirectTo = $frontend . '/auth/complete';

        $secure = ! app()->environment('local');
        $sameSite = 'Lax';

        $cookie = cookie('api_token', $token, $minutes, '/', null, $secure, true, false, $sameSite);

        return redirect($redirectTo)->withCookie($cookie);
    }

    /**
     * Send password reset email (creates a token and emails the frontend reset link)
     */
    public function sendPasswordReset(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            // do not reveal whether the email exists
            return $this->success(null, 'If an account exists we will send a password reset link');
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        Mail::to($user->email)->send(new PasswordResetMail($token));

        return $this->success(null, 'Password reset link sent if account exists');
    }

    /**
     * Reset user password (accepts token and new password)
     */
    public function resetPassword(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            // Accept a frontend provided hash, or a raw password for backwards compatibility
            'password_hash' => 'required_without:password|string|confirmed',
            'password' => 'required_without:password_hash|min:6|confirmed',
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $payload['email'])->first();
        if (! $row || ! hash_equals($row->token, $payload['token'])) {
            return $this->error('Invalid or expired token', 422);
        }

        // token valid for 2 hours
        if ($row->created_at && now()->diffInMinutes($row->created_at) > 120) {
            DB::table('password_reset_tokens')->where('email', $payload['email'])->delete();
            return $this->error('Token expired', 422);
        }

        $user = User::where('email', $payload['email'])->first();
        if (! $user) {
            return $this->error('User not found', 404);
        }

        // Determine the client hash (sha256 or provided). Re-hash on server-side with Hash::make.
        $clientNewHash = $payload['password_hash'] ?? hash('sha256', $payload['password'] ?? '');
        $user->password = Hash::make($clientNewHash);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $payload['email'])->delete();

        // issue a new token after password reset
        // Revoke tokens when password is reset
        $user->tokens()->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', User::class)
            ->delete();

        $token = $user->createToken('password-reset')->plainTextToken;

        return $this->success(['user' => $user, 'token' => $token], 'Password reset successfully');
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        $payload = $request->validate([
            'current_password' => 'required_without:current_password_hash|string',
            'current_password_hash' => 'required_without:current_password|string',
            'password_hash' => 'required_without:password|string|confirmed',
            'password' => 'required_without:password_hash|string|min:6|confirmed',
        ]);

        $currentClientHash = $payload['current_password_hash'] ?? ($payload['current_password'] ? hash('sha256', $payload['current_password']) : null);
        $currentOk = $currentClientHash ? Hash::check($currentClientHash, (string) $user->password) : false;

        if (! $currentOk) {
            return $this->error('Current password is incorrect', 422);
        }

        $clientNewHash = $payload['password_hash'] ?? hash('sha256', $payload['password'] ?? '');
        $user->password = Hash::make($clientNewHash);
        $user->save();

        // Optionally revoke tokens when password changes
        if (config('security.revoke_tokens_on_password_change', true)) {
            $user->tokens()->delete();
            // Also delete using the Sanctum model directly
            SanctumPersonalAccessToken::where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
        }
        return $this->success(null, 'Password changed successfully');
    }

    /**
     * Send an email verification message to the user (or email provided)
     */
    public function sendVerification(Request $request)
    {
        // If a user is logged in, send to that user, otherwise expect 'email' param
        $email = $request->user()?->email ?? $request->validate(['email' => 'required|email'])['email'];

        $user = User::where('email', $email)->first();
        if (! $user) {
            return $this->error('User not found', 404);
        }

        if ($user->email_verified_at) {
            return $this->success(null, 'Email already verified');
        }

        $token = Str::random(64);
        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        Mail::to($user->email)->send(new EmailVerificationMail($token));

        return $this->success(null, 'Verification email sent');
    }

    /**
     * Verify email via token (callback route)
     */
    public function verifyEmail(Request $request, string $token)
    {
        $row = DB::table('email_verification_tokens')->where('token', $token)->first();
        if (! $row) {
            return $this->error('Invalid verification token', 400);
        }

        $user = User::where('email', $row->email)->first();
        if (! $user) {
            return $this->error('User not found', 404);
        }

        $user->email_verified_at = now();
        $user->save();

        DB::table('email_verification_tokens')->where('email', $row->email)->delete();

        // Browser flow should redirect to frontend confirmation page
        if (! $request->wantsJson()) {
            $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            return redirect($frontend . '/auth/verified');
        }

        return $this->success(['user' => $user], 'Email verified');
    }
}
