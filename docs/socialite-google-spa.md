# Google OAuth (Server-side Socialite) + SPA flow (recommended)

This document shows how to configure Google Cloud credentials, wire Laravel Socialite on the server, and implement an SPA-friendly redirect / callback flow that issues secure tokens without exposing sensitive credentials in the browser.

Overview (safe, recommended):

-   The user clicks "Sign in with Google" in the SPA.
-   The SPA redirects the user's browser to the backend endpoint `/api/auth/google/redirect` which issues an OAuth redirect (HTTP 302) to Google's consent page.
-   Google redirects back to the backend `/api/auth/google/callback`.
-   Backend completes the OAuth flow using Socialite, creates/looks up the user, issues a Sanctum token and then redirects the browser to your SPA with the token handled securely.

Why this pattern?

-   Backend exchanges the authorization code for tokens (safe — secret keys remain on the server).
-   Backend can store tokens, create API tokens (Sanctum) or set HttpOnly cookies so SPA code never sees secrets or refresh tokens in plaintext.

---

## 1) Create credentials in Google Cloud (OAuth 2.0 client)

1. Open Google Cloud Console → APIs & Services → Credentials.
2. Create OAuth 2.0 Client ID (type: Web application).
3. Add an Authorized redirect URI that points to your backend callback URL, e.g.:

    - https://api.example.com/api/auth/google/callback

4. Copy the created Client ID and Client secret and add them to your `.env`:

```
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT=https://api.example.com/api/auth/google/callback
```

If you're working locally with e.g. `http://localhost:8000`, add the callback for local development:

```
GOOGLE_REDIRECT=http://localhost:8000/api/auth/google/callback
```

---

## 2) Server: Socialite is already installed (composer require laravel/socialite)

Add the Google settings in `config/services.php` (already done in this project):

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT'),
],
```

AuthController endpoints created for you in `/api/auth/google/redirect` and `/api/auth/google/callback`.

Example (server-side: already in this repo) — high level:

-   `redirectToGoogle()` → Socialite::driver('google')->redirect() (returns 302 redirect);
-   `handleGoogleCallback()` → obtains Socialite user details, find/create user, store `provider_name`, `provider_id`, `avatar`, and create a Laravel Sanctum token returned to client.

---

## 3) SPA-friendly UX patterns (recommendations)

You have two common approaches to handle the redirect/callback and token passage to the SPA.

A) Secure Server-side issuance + HttpOnly cookie (recommended)

-   Flow:
    1. SPA uses a backend endpoint (e.g. `GET /api/auth/google/redirect`) — redirect or fetch URL, then window.location driven redirection.
    2. Google returns to backend `/api/auth/google/callback` which uses Socialite to get user data and create/find user.
    3. Backend issues a Sanctum token and responds with a 302 redirect to the SPA URL, attaching an HttpOnly cookie that contains the token. The SPA receives the cookie on the domain and can then call protected API endpoints using the cookie + Sanctum CSRF flow or by using the token as needed.

Example backend (Laravel) to set an HttpOnly cookie and redirect:

```php
// After successfully creating $token
$frontend = config('app.frontend_url', env('FRONTEND_URL')) ?: 'https://app.example.com';

$cookie = cookie('api_token', $token, 60, '/', null, true, true, false, 'Strict');
return redirect("{$frontend}/auth/complete")->withCookie($cookie);
```

Notes:

-   `withCookie()` sets the cookie with Secure/HttpOnly flags so JavaScript cannot read it.
-   You can use Sanctum cookies alongside this approach for improved CSRF protection.

B) Server redirects to frontend with token as query param or URL fragment (less secure)

-   Flow: the backend redirects to something like `https://app.example.com/auth/complete#token=<plainToken>` or `?token=<token>`.
-   The SPA can read the token from the URL fragment and store it locally.

Security concerns for B:

-   Tokens exposed in URL query are stored in browser history and are reachable by third-party referrers; avoid passing tokens in query strings. If necessary, use URL fragment (#) and prefer short-lived tokens.

---

## 4) SPA Example (React) — simple flow

1. On click of "Sign in with Google":

```js
// Option A - redirect directly to backend which 302s to Google
window.location.href = `${API_BASE}/api/auth/google/redirect`;

// Option B - fetch URL and then redirect (if endpoint returns URL instead of redirect)
// fetch(`${API_BASE}/api/auth/google/url`).then(res => res.json()).then(({url}) => window.location.href = url);
```

2. After Google step, you'll arrive at your backend `/api/auth/google/callback` which then redirects to your SPA route, e.g. `/auth/complete` — this endpoint should present the user a link or auto-close and now the SPA will be authenticated via cookie or a token.

In `auth/complete` page, if you used method A and the backend returned the token via cookie: call your API to confirm auth and load user info.

If the backend passed a token as a fragment or query param (not recommended), parse it and persist it using secure client storage:

```js
const url = new URL(window.location.href);
// if token in hash
const token = new URLSearchParams(window.location.hash.replace("#", "?")).get(
    "token"
);
if (token) {
    localStorage.setItem("api_token", token);
}
```

---

## 5) Extra Security & improvements

-   Use the `state` parameter to store a CSRF token or the intended redirect route in the SPA.
-   Use `stateless()` with Socialite when the frontend will handle authorization states client-side; ensure you handle `state` manually.
-   Use `access_type=offline` + `prompt=consent` (Google) if you need refresh tokens — only store refresh tokens server-side.
-   Consider rotating tokens, scoping abilities and enforcing expiration on personal access tokens.

---

If you'd like, I can now:

-   implement an endpoint that returns the Google OAuth redirect URL to the SPA (instead of issuing a 302 redirect) — useful for native mobile and SPAs that want to handle navigation themselves; or
-   change the callback to set a secure HttpOnly cookie with the token and redirect to a SPA route so the token never appears in the URL.

Which of those would you prefer me to implement next? (I can add both.)
