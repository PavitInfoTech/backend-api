<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie as CookieFacade;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use App\Mail\EmailVerificationMail;
use Illuminate\Support\Facades\DB;

class AuthController extends ApiController
{
    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password_hash' => 'required|string|size:64', // SHA-256 hash (64 hex chars)
            'password_hash_confirmation' => 'required|string|same:password_hash',
        ]);

        if ($v->fails()) {
            return $this->error('Validation failed', 422, $v->errors()->toArray());
        }

        $user = User::create([
            'username' => $request->username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? null,
            'email' => $request->email,
            'password' => $request->password_hash, // Store the hash directly
        ]);

        // Create a Sanctum personal access token
        $token = $user->createToken('api-token')->plainTextToken;

        // Automatically send email verification
        $this->sendVerificationEmail($user);

        return $this->success(['user' => $user, 'token' => $token], 'Registered. Please check your email to verify your account.', 201);
    }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|email',
            'password_hash' => 'required|string|size:64', // SHA-256 hash (64 hex chars)
        ]);

        if ($v->fails()) {
            return $this->error('Validation failed', 422, $v->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! hash_equals($user->password, $request->password_hash)) {
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

        $profile = $this->buildProfileFromSocialiteUser($social, 'github');
        $user = $this->findOrCreateSocialUser('github', $profile);

        return $this->respondAfterSocialLogin($request, $user, 'github');
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

        $profile = $this->buildProfileFromSocialiteUser($social, 'google');
        $user = $this->findOrCreateSocialUser('google', $profile);

        return $this->respondAfterSocialLogin($request, $user, 'google');
    }

    public function googleTokenLogin(Request $request)
    {
        $payload = $request->validate([
            'code' => 'required_without:credential|string',
            'credential' => 'required_without:code|string',
            'redirect_uri' => 'sometimes|url',
        ]);

        try {
            if (! empty($payload['code'])) {
                $tokenResponse = $this->exchangeGoogleCode($payload['code'], $payload['redirect_uri'] ?? null);
                $accessToken = $tokenResponse['access_token'] ?? null;
                if (! $accessToken) {
                    throw new \RuntimeException('Google token response missing access_token');
                }
                /** @var \Laravel\Socialite\Two\GoogleProvider $googleDriver */
                $googleDriver = Socialite::driver('google');
                $social = $googleDriver->userFromToken($accessToken);
                $profile = $this->buildProfileFromSocialiteUser($social, 'google');
            } else {
                $profile = $this->profileFromGoogleIdToken($payload['credential']);
            }

            $user = $this->findOrCreateSocialUser('google', $profile);
            return $this->respondAfterSocialLogin($request, $user, 'google', true);
        } catch (\Throwable $e) {
            return $this->error('Google authentication failed', 400, ['error' => $e->getMessage()]);
        }
    }

    public function githubTokenLogin(Request $request)
    {
        $payload = $request->validate([
            'code' => 'required_without:access_token|string',
            'access_token' => 'required_without:code|string',
            'redirect_uri' => 'sometimes|url',
        ]);

        try {
            if (! empty($payload['code'])) {
                $token = $this->exchangeGithubCode($payload['code'], $payload['redirect_uri'] ?? null);
            } else {
                $token = $payload['access_token'];
            }

            if (! $token) {
                throw new \RuntimeException('GitHub access token missing');
            }

            /** @var \Laravel\Socialite\Two\GitHubProvider $githubDriver */
            $githubDriver = Socialite::driver('github');
            $social = $githubDriver->userFromToken($token);
            $profile = $this->buildProfileFromSocialiteUser($social, 'github');
            $user = $this->findOrCreateSocialUser('github', $profile);

            return $this->respondAfterSocialLogin($request, $user, 'github', true);
        } catch (\Throwable $e) {
            return $this->error('GitHub authentication failed', 400, ['error' => $e->getMessage()]);
        }
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

        Mail::to($user->email)->send(new PasswordResetMail($token, $user->email));

        return $this->success(null, 'Password reset link sent if account exists');
    }

    /**
     * Reset user password (accepts token and new password hash)
     */
    public function resetPassword(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password_hash' => 'required|string|size:64', // SHA-256 hash (64 hex chars)
            'password_hash_confirmation' => 'required|string|same:password_hash',
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

        $user->password = $payload['password_hash']; // Store the hash directly
        $user->save();

        DB::table('password_reset_tokens')->where('email', $payload['email'])->delete();

        // issue a new token after password reset
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
            'current_password_hash' => 'required|string|size:64', // SHA-256 hash (64 hex chars)
            'password_hash' => 'required|string|size:64', // SHA-256 hash (64 hex chars)
            'password_hash_confirmation' => 'required|string|same:password_hash',
        ]);

        if (! hash_equals($user->password, $payload['current_password_hash'])) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->password = $payload['password_hash']; // Store the hash directly
        $user->save();

        return $this->success(null, 'Password changed successfully');
    }

    /**
     * Helper method to send verification email to a user.
     */
    protected function sendVerificationEmail(User $user): void
    {
        $token = Str::random(64);
        DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        Mail::to($user->email)->send(new EmailVerificationMail($token));
    }

    /**
     * Resend email verification message to the user (for users who missed or lost the original email).
     */
    public function sendVerification(Request $request)
    {
        // If a user is logged in, send to that user, otherwise expect 'email' param
        $email = $request->user()?->email ?? $request->validate(['email' => 'required|email'])['email'];

        $user = User::where('email', $email)->first();
        if (! $user) {
            // Don't reveal whether user exists for security
            return $this->success(null, 'If this email is registered and unverified, a verification email has been sent');
        }

        if ($user->email_verified_at) {
            return $this->success(null, 'Email already verified');
        }

        $this->sendVerificationEmail($user);

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

    protected function buildProfileFromSocialiteUser(SocialiteUserContract $social, string $provider): array
    {
        $name = $social->getName() ?? $social->getNickname() ?? $social->getEmail() ?? $provider . '-' . Str::random(6);

        return [
            'id' => $social->getId(),
            'email' => $social->getEmail(),
            'name' => $name,
            'first_name' => $social->user['given_name'] ?? null,
            'last_name' => $social->user['family_name'] ?? null,
            'username' => $social->getNickname(),
            'avatar' => $social->getAvatar(),
        ];
    }

    protected function profileFromGoogleIdToken(string $credential): array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);
        if ($response->failed()) {
            throw new \RuntimeException('Invalid Google credential');
        }

        $data = $response->json();

        return [
            'id' => $data['sub'] ?? null,
            'email' => $data['email'] ?? null,
            'name' => $data['name'] ?? trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? '')),
            'first_name' => $data['given_name'] ?? null,
            'last_name' => $data['family_name'] ?? null,
            'avatar' => $data['picture'] ?? null,
            'username' => $data['preferred_username'] ?? ($data['email'] ?? null),
        ];
    }

    protected function findOrCreateSocialUser(string $provider, array $profile): User
    {
        $providerId = $profile['id'] ?? null;
        $email = $profile['email'] ?? null;
        $name = $profile['name'] ?? ($email ?? ($provider . '-' . Str::random(6)));
        $firstName = $profile['first_name'] ?? null;
        $lastName = $profile['last_name'] ?? null;

        if (! $firstName || ! $lastName) {
            $parts = preg_split('/\s+/', trim((string) $name));
            $firstName = $firstName ?? ($parts[0] ?? null);
            $lastName = $lastName ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null);
        }

        $user = null;
        if ($providerId) {
            $user = User::where('provider_name', $provider)->where('provider_id', $providerId)->first();
        }

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $preferredUsername = $profile['username'] ?? ($email ? explode('@', $email)[0] : Str::slug($name));
            $base = $preferredUsername ? Str::slug($preferredUsername) : Str::slug(($firstName ?? 'user') . '-' . ($lastName ?? Str::random(4)));
            $base = $base ?: 'user-' . Str::random(4);
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
                'avatar' => $profile['avatar'] ?? null,
            ]);
        } else {
            if (! $user->provider_name) {
                $user->provider_name = $provider;
            }
            if (! $user->provider_id && $providerId) {
                $user->provider_id = $providerId;
            }
            if (! empty($profile['avatar'])) {
                $user->avatar = $profile['avatar'];
            }
            $user->save();
        }

        return $user;
    }

    protected function respondAfterSocialLogin(Request $request, User $user, string $provider, bool $forceJson = false)
    {
        $token = $user->createToken($provider)->plainTextToken;
        $message = 'Authenticated via ' . ucfirst($provider);

        if ($forceJson || $this->expectsJsonResponse($request)) {
            return $this->success(['user' => $user, 'token' => $token], $message);
        }

        return $this->cookieRedirectResponse($token);
    }

    protected function cookieRedirectResponse(string $token)
    {
        $minutes = (int) env('SANCTUM_COOKIE_TTL', 60 * 24 * 30);
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $redirectTo = $frontend . '/auth/complete';

        $secure = ! app()->environment('local');
        $sameSite = 'Lax';

        $cookie = cookie('api_token', $token, $minutes, '/', null, $secure, true, false, $sameSite);

        return redirect($redirectTo)->withCookie($cookie);
    }

    protected function expectsJsonResponse(Request $request): bool
    {
        return $request->wantsJson() || $request->accepts('application/json');
    }

    protected function exchangeGoogleCode(string $code, ?string $redirectUri = null): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $redirectUri ?? config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google token exchange failed');
        }

        return $response->json();
    }

    protected function exchangeGithubCode(string $code, ?string $redirectUri = null): ?string
    {
        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://github.com/login/oauth/access_token', [
                'code' => $code,
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'redirect_uri' => $redirectUri ?? config('services.github.redirect'),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('GitHub token exchange failed');
        }

        $data = $response->json();

        return $data['access_token'] ?? null;
    }
}
