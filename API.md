# Backend API — Endpoints & Configuration

This document describes the API routes added, required request parameters, example responses, and environment variables to configure for Google OAuth, email, Gorq AI, and Google Maps integration.

---

## Quick setup / migrations

-   Add required environment variables in `.env` (see `.env.example`).
-   Run database migrations:

```powershell
php artisan migrate
```

Notes:

-   A migration was added: `database/migrations/2025_11_26_000001_add_oauth_provider_fields_to_users.php` which adds `provider_name`, `provider_id` and `avatar` to `users`.
-   A migration was added: `database/migrations/2025_11_26_000002_create_personal_access_tokens_table.php` which creates `personal_access_tokens` for Sanctum personal tokens.

Installed and recommended packages:

-   Laravel Sanctum — installed and configured for personal access tokens (token-based API auth)
-   Laravel Socialite — recommended to implement Google OAuth flows

---

## Environment variables (added/required)

These variables were added to `.env.example` and must be configured in your `.env` when you connect services:

-   GOOGLE_CLIENT_ID — Google OAuth client ID (Socialite)
-   GOOGLE_CLIENT_SECRET — Google OAuth secret
-   GOOGLE_REDIRECT — OAuth callback (default: `${APP_URL}/auth/google/callback`)
-   GITHUB_CLIENT_ID -
-   GITHUB_CLIENT_SECRET -
-   GITHUB_REDIRECT -
-   GORQ_API_KEY — API key for Gorq (or your AI provider)
-   GORQ_BASE_URL — Base URL for Gorq API (default `https://api.gorq.ai`)
-   GORQ_DEFAULT_MODEL — Optional default model to use
-   FRONTEND_URL — Frontend SPA address for CORS/callbacks
-   SANCTUM_STATEFUL_DOMAINS — If using Sanctum for SPA auth

The repo already contains mail config examples (MAIL\_\* in `.env.example`) for sending messages.

---

## Routes summary

All API endpoints are exposed from `routes/api.php`.

-   Default (development/testing): If `API_DOMAIN` is not set, routes are served with the `/api` prefix (e.g., `/api/auth/login`).
-   Subdomain mode (production): If you set `API_DOMAIN` to your API subdomain (e.g., `api.example.com`), the same `routes/api.php` endpoints are exposed at the root path on that domain — e.g., `https://api.example.com/auth/login` (no `/api` prefix).

Note: For local development you can avoid configuring DNS or host entries by enabling the optional `API_PREFIX_FALLBACK` environment variable; this registers the `/api` prefix **in addition** to the domain routes when `API_DOMAIN` is set — this is handy when you set `API_DOMAIN` but still want to call the API at `/api` during development or when `api.example.com` is not resolvable locally.

## Developer tools

If you do not have terminal access on the server, there is a safe, token-protected HTTP endpoint for running migrations using Artisan. It is disabled by default and should be enabled and used with caution in production environments.

Endpoint:

-   POST /admin/migrate

Payload / headers:

-   Header `X-RUN-MIG-TOKEN` or body param `token` — the value must match `RUN_MIG_TOKEN` in `.env`.
-   Optional `seed` boolean body param to run `db:seed` after migrations.
-   Optional `path` string body param to pass `--path` to `migrate`.

Requirements & safety:

-   `ALLOW_RUN_MIG=true` must be set in `.env` to allow this endpoint to run.
-   `RUN_MIG_TOKEN` should be a long random secret and stored in server environment. Do not keep it in VCS.
-   The route is throttled (`throttle:10,1`) by default.

Example usage (curl):

```
curl -X POST https://api.pavitinfotech.com/admin/migrate \
    -H "X-RUN-MIG-TOKEN: $RUN_MIG_TOKEN"
```

Response:

-   Returns a JSON result with the Artisan output under `data.output`. If operations fail, a 500 result with details will be returned and logged.

Security note: After running migrations via HTTP, disable ALLOW_RUN_MIG or rotate the token. This endpoint provides a convenient but sensitive capability and should be restricted to trusted usage only.

For a full guide on configuring Google Cloud credentials, Socialite server usage, and SPA redirect handling (secure token flows and examples), see `docs/socialite-google-spa.md`.

### Authentication

#### Flow overview

-   **Credential (email + password hash)** — `POST /auth/register` to create an account and `POST /auth/login` to obtain a Sanctum personal access token. Tokens must be sent via `Authorization: Bearer <token>` on protected routes. Frontends are responsible for hashing the password with SHA-256 before sending it to the API.
-   **Logout** — `POST /auth/logout` works for both API calls (returns JSON) and browser flows (redirects + clears the `api_token` cookie) and revokes the active Sanctum token.
-   **OAuth browser redirects** — `GET /auth/{provider}/redirect` (Google/GitHub) sends the browser to the provider; `GET /auth/{provider}/callback` finishes authentication, issues a Sanctum token, and either returns JSON or sets the `api_token` cookie and redirects to the SPA.
-   **OAuth API/token exchange** — `POST /auth/google/token` and `POST /auth/github/token` let SPAs or native apps exchange an OAuth `code`, Google Credential API `credential`, or a GitHub access token directly for a Sanctum token without browser redirects.
-   **Email verification** — `POST /auth/verify/send` issues tokens; `GET /auth/verify/{token}` validates them and either returns JSON or redirects to the SPA.
-   **Password reset** — `POST /auth/password/forgot` creates reset tokens and emails users; `POST /auth/password/reset` validates the token and updates the stored password hash.
-   **Social linking** — Authenticated users can link/unlink Google/GitHub providers via `/auth/link/...` and `/auth/unlink` so future logins can use OAuth.
-   **Profile & session hygiene** — Protected endpoints (e.g., `/user`) require the Bearer token or the secure `api_token` cookie returned by the OAuth callbacks.

> **⚠️ IMPORTANT: Password Hashing Requirement**
>
> For security, the frontend **must hash passwords client-side** before sending them to the API. All password fields expect a **SHA-256 hash** (64 hexadecimal characters) instead of plain text passwords. This ensures passwords are never transmitted in plain text over the network.
>
> Example (JavaScript):
>
> ```javascript
> const passwordHash = await crypto.subtle
>     .digest("SHA-256", new TextEncoder().encode(password))
>     .then((buf) =>
>         Array.from(new Uint8Array(buf))
>             .map((b) => b.toString(16).padStart(2, "0"))
>             .join("")
>     );
> ```

#### Credential-based register & login

-   POST /api/auth/register (or POST /auth/register if `API_DOMAIN` is set)

    -   Request body (application/json):
        -   username (string, required, unique)
        -   first_name (string, required)
        -   last_name (string, optional)
        -   email (string, required)
        -   password_hash (string, required, 64-char SHA-256 hex hash)
        -   password_hash_confirmation (string, required, must match password_hash)
    -   Behavior: Creates the user, issues a Sanctum token, and **automatically sends a verification email**. The user should verify their email by clicking the link in the email.
    -   Success (201):
        -   { status: 'success', message: 'Registered. Please check your email to verify your account.', data: { user: {...}, token: '...'} }

    Example request (register):

    ```json
    POST /api/auth/register
    Content-Type: application/json

    {
        "username": "johndoe",
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "password_hash": "5e884898da28047d9165934e90a3ad56a3b6abe0c40d4f8b59e4c99f7a9c5d8e",
        "password_hash_confirmation": "5e884898da28047d9165934e90a3ad56a3b6abe0c40d4f8b59e4c99f7a9c5d8e"
    }
    ```

    Example response (201):

    ```json
    {
        "status": "success",
        "message": "Registered",
        "data": {
            "user": {
                "id": 123,
                "username": "johndoe",
                "first_name": "John",
                "last_name": "Doe",
                "email": "john@example.com",
                "avatar": null,
                "email_verified_at": null,
                "created_at": "2025-11-28T12:34:56Z"
            },
            "token": "plain-text-sanctum-token"
        },
        "code": 201,
        "timestamp": "2025-11-28T12:34:56Z"
    }
    ```

    -   Note: This endpoint now issues a Laravel Sanctum personal access token (plain text). Save this token client-side and send it on protected requests with the Authorization header:

```
Authorization: Bearer <your-plain-text-token-here>
```

-   POST /api/auth/login (or POST /auth/login if `API_DOMAIN` is set)
    Example request (login):

    ```json
    POST /api/auth/login
    Content-Type: application/json

    {
        "email": "john@example.com",
        "password_hash": "5e884898da28047d9165934e90a3ad56a3b6abe0c40d4f8b59e4c99f7a9c5d8e"
    }
    ```

    Example response (200):

    ```json
    {
        "status": "success",
        "message": "Logged in",
        "data": {
            "user": {
                "id": 123,
                "username": "johndoe",
                "first_name": "John",
                "last_name": "Doe",
                "email": "john@example.com",
                "avatar": null
            },
            "token": "plain-text-sanctum-token"
        },
        "code": 200,
        "timestamp": "2025-11-28T12:35:00Z"
    }
    ```

    -   Request body: { email, password_hash }
    -   Success (200): { status: 'success', message: 'Logged in', data: { user, token } }

#### Logout (token & cookie aware)

-   POST /api/auth/logout (or POST /auth/logout if `API_DOMAIN` is set)
    -   Behavior: API clients get JSON + token revocation; browser requests (Accept HTML) revoke tokens, clear the `api_token` cookie, and 302 redirect to `${FRONTEND_URL}/auth/logout`.

#### Google OAuth (browser redirect flow)

-   GET /api/auth/google/redirect (or GET /auth/google/redirect if `API_DOMAIN` is set)

    -   Redirects to Google OAuth consent page using Laravel Socialite. This endpoint issues an HTTP redirect (302) that should be followed by the browser or frontend app. If your frontend needs the direct URL instead, call this endpoint and read the Location header of the response.

-   GET /api/auth/google/callback (or GET /auth/google/callback if `API_DOMAIN` is set)

    -   OAuth callback — handled with Laravel Socialite.
    -   Behavior:
        -   Socialite reads Google user info (id, name, email, avatar). The backend will map provider `name` into `first_name` and `last_name` where possible and generate a `username` using the preferred username or email localpart.
        -   If a user exists with the same `provider_name` + `provider_id`, that user is returned.
        -   Otherwise the backend attempts to find a user by email and attach Google provider data.
        -   If no matching user exists, a new user is created and provider fields (`provider_name`, `provider_id`, `avatar`) are saved.
        -   A Laravel Sanctum personal access token is created.
        -   Behavior detail:
            -   By default the server will set a secure, HttpOnly cookie named `api_token` and redirect the browser to the SPA route (e.g. `${FRONTEND_URL}/auth/complete`) so the token never appears in the URL.
            -   If the request explicitly expects JSON (e.g., Accept: application/json), the token will be returned in the JSON response instead.
    -   Browser flow response (Redirect):
        -   302 redirect to the SPA route, cookie `api_token` set (HttpOnly, Secure in production).
    -   API flow response (JSON - when Accept: application/json):
        -   { status: 'success', message: 'Authenticated via Google', data: { user: {...}, token: '<plain-text-token>' } }

-   POST /api/auth/google/token (API-only token exchange)

    -   Body (JSON):
        -   `code` (string) — authorization code received from Google OAuth (required if `credential` missing)
        -   `credential` (string) — ID token from Google One Tap / Credential API (required if `code` missing)
        -   `redirect_uri` (string, optional) — override redirect URI used during code exchange
    -   Behavior:
        -   If `code` is provided, the backend exchanges it against Google's token endpoint using the configured `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, obtains an access token, resolves the user profile via Socialite, and creates/logs in the user.
        -   If `credential` (ID token) is provided, the backend verifies it using Google's `tokeninfo` endpoint and uses the resulting profile to authenticate the user.
        -   Always returns JSON (no redirects) with the Sanctum token.
    -   Success response (200): `{ "status": "success", "message": "Authenticated via Google", "data": { "user": {...}, "token": "plain-text-sanctum-token" } }`
    -   Errors:
        -   400 — Invalid/expired code or credential, or Google API error
        -   422 — Missing `code`/`credential` payload
    -   Use this endpoint for native apps or SPAs that already captured the Google credential and simply need to exchange it server-side without browser redirects.

#### Password reset

-   POST /api/auth/password/forgot (or POST /auth/password/forgot if `API_DOMAIN` is set)

    -   Body: { email }
    -   Behavior: server will create a password reset token stored in `password_reset_tokens` (valid for ~2 hours) and email the frontend password-reset link to the user if the account exists. The response does not reveal whether the account exists.
    -   Success (200): { status: 'success', message: 'Password reset link sent if account exists' }

-   POST /api/auth/password/reset (or POST /auth/password/reset if `API_DOMAIN` is set)

    -   Body: { email, token, password_hash, password_hash_confirmation }
    -   Note: password_hash must be a 64-character SHA-256 hex hash of the new password
    -   Behavior: verifies the reset token, ensures it is not expired (2 hours), updates the user's password hash, deletes the token, and returns a new API token so the user is authenticated immediately.
    -   Success (200): { status: 'success', message: 'Password reset successfully', data: { user: {...}, token: '<plain-text-token>' } }

#### Email verification

-   POST /api/auth/verify/send (or POST /auth/verify/send if `API_DOMAIN` is set)

    -   Body: { email } or (authenticated) send to current user
    -   Behavior: **Resend verification email** for users who missed or lost the original email sent during registration. Creates/updates an email verification token stored in `email_verification_tokens` and sends a verification email. For security, the response does not reveal whether the email exists if unregistered.
    -   Success (200): { status: 'success', message: 'Verification email sent' } or 'Email already verified' if already verified

-   GET /api/auth/verify/{token} (or GET /auth/verify/{token} if `API_DOMAIN` is set)

    -   Behavior: verifies the token, sets `email_verified_at` for the user, deletes the token, and either returns JSON (API clients) or redirects the browser to `${FRONTEND_URL}/auth/verified`.
    -   Success (200 or 302): JSON { status: 'success', message: 'Email verified', data: { user } } or 302 redirect to frontend verified page.

### GitHub OAuth (browser redirect flow)

    -   GET /api/auth/github/redirect — Redirects the browser to GitHub's OAuth consent page (via Socialite). If your SPA needs the URL to redirect itself, call this endpoint and read the Location header.
    -   GET /api/auth/github/callback — OAuth callback endpoint which handles the GitHub response and returns a token in JSON (for API clients) or sets a secure `api_token` cookie and redirects to the SPA route on success.

#### GitHub OAuth behavior

    Behavior is identical to Google OAuth flow but uses the `github` Socialite driver:

    -   Creates the user if not present and saves `provider_name` = 'github' and `provider_id`.
    -   If a user already exists with the same email, the code attaches `provider` fields to that existing user rather than creating a new one.
    -   Returns JSON with `user` and `token` in API flows, and sets an `api_token` cookie on browser flows.

-   POST /api/auth/github/token (API-only token exchange)

    -   Body (JSON):
        -   `code` (string) — authorization code returned by GitHub's OAuth authorize endpoint (required if `access_token` missing)
        -   `access_token` (string) — GitHub access token obtained on the client (required if `code` missing)
        -   `redirect_uri` (string, optional) — custom redirect URI used when generating the code
    -   Behavior:
        -   When a `code` is provided, the backend calls `https://github.com/login/oauth/access_token` with your app's client ID/secret to exchange it for an access token, then fetches the user profile using Socialite and logs the user in.
        -   When `access_token` is provided directly, it is used immediately to fetch the GitHub profile.
        -   Always responds with JSON, returning `{ user, token }` on success or a structured 400 error on failure.
    -   Errors:
        -   400 — Code exchange failed or provided access token invalid/expired
        -   422 — Neither `code` nor `access_token` provided
    -   Ideal for native apps or SPAs that already have the code/token and need a pure API flow with no redirects.

### Social account linking / unlinking

-   GET `/auth/link/{provider}/redirect` + `/auth/link/{provider}/callback` (authenticated) allow existing users to attach Google/GitHub accounts to their profile.
-   POST `/auth/unlink` removes the provider association.
-   These routes require Bearer tokens (they live in the authenticated group) and return JSON.

### User profile (protected)

    -   Returns current authenticated user's profile.

    -   Body (optional fields):
        -   `username` (string, optional, max 255, unique)
        -   `first_name` (string, optional, max 255)
        -   `last_name` (string, optional, max 255)
        -   `email` (string, optional, valid email address, unique among users)
        -   `avatar` (string, optional, url to avatar image)
            -   NOTE: When returned in API responses, `avatar` is always normalized to an absolute URL so frontends can safely display it. The API accepts multiple formats stored in the DB (absolute URLs, `/storage/...`, `avatars/...`, or relative paths) and converts them to a fully-qualified URL.
    -   Validation rules:
        -   `username` => sometimes|string|max:255|unique:users,username,{user_id}
        -   `first_name` => sometimes|string|max:255
        -   `last_name` => sometimes|string|max:255
        -   `email` => sometimes|email|unique:users,email,{user_id}
        -   `avatar` => sometimes|url
    -   Success (200): returns updated user { status: 'success', message: 'Profile updated', data: { user will be returned }}
    -   Errors:
        -   401 Unauthenticated — missing or invalid token
        -   422 Validation failed — invalid_name/email or email already taken
        -   500 Server error — database or other internal error
    -   Body (optional any): { username, first_name, last_name, email, avatar }

    Example request (GET /user):

    ```http
    GET /api/user
    Authorization: Bearer <token>
    Accept: application/json
    ```

    Example response (200):

    ```json
    {
        "status": "success",
        "message": "User profile",
        "data": {
            "id": 123,
            "username": "johndoe",
            "first_name": "John",
            "last_name": "Doe",
            "email": "john@example.com",
            "avatar": "https://cdn.example.com/avatars/123.png",
            "created_at": "2025-11-28T12:34:56Z"
        },
        "code": 200,
        "timestamp": "2025-11-28T12:35:10Z"
    }
    ```

    -   POST /api/auth/password/change (or POST /auth/password/change if `API_DOMAIN` is set)

        -   Body: { current_password_hash, password_hash, password_hash_confirmation }
        -   Note: All password fields must be 64-character SHA-256 hex hashes
        -   Behavior: Authenticated endpoint. Validates the user's current password hash, and if valid, updates the password to the new hash. This does *not* revoke active API tokens by default (frontend should re-login to refresh tokens if desired).
        -   Success (200): { status: 'success', message: 'Password changed successfully' }
        -   Errors:
            - 401 Unauthenticated — missing or invalid token
            - 422 Validation failed — wrong current password hash or invalid new password hash/confirmation

    -   DELETE /api/user (or DELETE /user if `API_DOMAIN` is set)

        -   Behavior: Authenticated endpoint. Deletes the authenticated user's account, associated avatar file stored on the server (if found), and revokes stored API tokens.
        -   Success (200): { status: 'success', message: 'Account deleted' }
        -   Errors:
            - 401 Unauthenticated — missing or invalid token

-   POST /api/user/avatar (or POST /user/avatar if `API_DOMAIN` is set)

    -   Multipart/form-data: file field `avatar` (image, max 5MB)
    -   Behavior: authenticated endpoint. Validates and stores the uploaded image under `storage/app/public/avatars/{user_id}/` and returns the public URL. If a previous avatar was stored on the server it will be deleted.
    -   Success (200): { status: 'success', message: 'Avatar uploaded', data: { avatar_url: '<url>' } }

    Example response for avatar upload (200):

    ```json
    {
        "status": "success",
        "message": "Avatar uploaded",
        "data": {
            "avatar_url": "https://your-cdn-or-domain/storage/avatars/123/abcdef_1600000000.png"
        },
        "code": 200,
        "timestamp": "2025-11-28T12:40:00Z"
    }
    ```

-   GET /api/users/{id}/public (or GET /users/{id}/public if `API_DOMAIN` is set)

    -   Returns a limited public profile object suitable for other users or public pages: { id, username, first_name, last_name, avatar, created_at }

    Example response (public profile):

    ```json
    {
        "status": "success",
        "message": "Public profile",
        "data": {
            "id": 123,
            "username": "johndoe",
            "first_name": "John",
            "last_name": "Doe",
            "avatar": "https://cdn.example.com/avatars/123.png",
            "created_at": "2025-11-28T12:34:56Z"
        },
        "code": 200,
        "timestamp": "2025-11-28T12:41:00Z"
    }
    ```

    -   Note: `avatar` in the public profile is normalized to an absolute URL (see above).

Notes: run `php artisan storage:link` in deployment to make `storage/app/public` available at `/storage` so avatar URLs are reachable by the browser.

### Mail endpoints

-   POST /api/mail/contact (or POST /mail/contact if `API_DOMAIN` is set)

    -   Body: { name, email, message }
    -   Action: Sends a contact message to the configured `MAIL_FROM_ADDRESS`.

-   POST /api/mail/newsletter (or POST /mail/newsletter if `API_DOMAIN` is set)

    -   Body: { email, name? }
    -   Validation: `email` => required|email, `name` => sometimes|string|max:255
    -   Action: Creates a newsletter subscriber with a verification token and sends a verification email. A notification is also sent to the admin inbox. Duplicate subscription attempts are idempotent.
    -   Response (200): { status: 'success', message: 'Newsletter signup processed. Please check your email to verify.', data: { subscriber_id } }

-   GET /api/mail/newsletter/verify/{token} (or GET /mail/newsletter/verify/{token} if `API_DOMAIN` is set)

    -   Action: Verifies the newsletter subscription via the token sent in the verification email. Once verified, sends a personalized welcome email to the subscriber. If `FRONTEND_URL` is set and request is not JSON, redirects to `{FRONTEND_URL}/newsletter/verified`.
    -   Response (200): { status: 'success', message: 'Subscription verified successfully' }
    -   Response (404): Invalid or expired token

-   GET /api/mail/newsletter/unsubscribe/{token} (or GET /mail/newsletter/unsubscribe/{token} if `API_DOMAIN` is set)

    -   Action: Unsubscribes the user from the newsletter by deleting their record. The token is unique per subscriber and included in all newsletter emails. If `FRONTEND_URL` is set and request is not JSON, redirects to `{FRONTEND_URL}/newsletter/unsubscribed`.
    -   Response (200): { status: 'success', message: 'Successfully unsubscribed from newsletter', data: { email } }
    -   Response (404): Invalid or expired token

-   POST /api/mail/password-reset (or POST /mail/password-reset if `API_DOMAIN` is set)
    -   Body: { email }
    -   Action: Creates a password reset token and sends the `PasswordResetMail` to the user. Response does not reveal whether the email exists (security best practice).
    -   Response (200): { status: 'success', message: 'If this email is registered, a password reset link has been sent' }

### AI / Gorq

-   POST /api/ai/generate (or POST /ai/generate if `API_DOMAIN` is set) — Public endpoint
-   POST /api/ai/generate

    -   Body: { prompt?: string (required without messages), messages?: array (required without prompt), model?: string, max_tokens?: integer, async?: boolean }
    -   Validation rules:
        -   `prompt` => required_without:messages|string|max:5000
        -   `messages` => required_without:prompt|array
        -   `messages.*.role` => sometimes|string|in:system,user,assistant,tool
        -   `messages.*.content` => sometimes|string|array
        -   `model` => sometimes|string|max:255
        -   `max_tokens` => sometimes|integer|min:1|max:2048
        -   `async` => sometimes|boolean
    -   Action: Validates and sanitizes `prompt` or `messages` input, logs the request (`ai_requests` table) and either:
        -   Synchronous (default): builds a chat-style payload and forwards the request to the configured Gorq service (via `GORQ_API_KEY`) and returns the provider result. The `ai_requests` record is updated with status and result. If only `prompt` was provided, it is converted to a single-message conversation where the role is `user`.
        -   Async (async=true): creates an `ai_requests` record (status `pending`) and dispatches a queued job to process the request. Responds 202 Accepted with `job_id` and `status_url` to poll.
    -   Rate limiting: protected by `throttle:ai` rate limiter (per IP). Configure with `AI_RATE_LIMIT_PER_MINUTE` (default 60/min).
    -   Example successful sync response: { status: 'success', data: { ... } }
    -   Example async accepted response (202 Accepted):

```
HTTP/1.1 202 Accepted
Content-Type: application/json

{
    "status": "accepted",
    "message": "Request accepted, processing",
    "data": { "job_id": 123, "status_url": "/api/ai/jobs/123/status" }
}
```

    -   Errors & failure modes:
        - 401 Unauthenticated — (not used) endpoint is public; some UI may prefer authenticated usage for billing/audit
        - 422 Validation failed — missing prompt or invalid params
        - 429 Too Many Requests — rate limiter triggered (AI rate limiter `throttle:ai`)
                - 500 Server Error — AI provider failure or internal error. Response will include `errors` which may contain `payload` (the request payload sent to the provider) and `gorq_response` (raw response details captured from Gorq including status, body, and parsed JSON when appropriate) for debugging. This extra detail can be disabled in production if you prefer not to reveal provider responses.

        -   Example request (Chat Completions style):

```
POST /api/ai/generate
Content-Type: application/json

{
    "messages": [
        { "role": "system", "content": "You are a helpful assistant." },
        { "role": "user", "content": "Give me a brief introduction to IoT monitoring." }
    ],
    "model": "gpt-test",
    "max_tokens": 256
}
```

        -   Example error response when Gorq fails (500):

```
HTTP/1.1 500 Internal Server Error
Content-Type: application/json

{
    "status": "error",
    "message": "AI provider error",
    "errors": {
        "payload": {
            "messages": [ { "role": "user", "content": "..." } ],
            "model": "gpt-test",
            "max_tokens": 256
        },
        "gorq_response": {
            "status": 502,
            "body": "Bad Gateway",
            "json": null
        }
    }
}
```

    -   GET /api/ai/jobs/{id}/status (or GET /ai/jobs/{id}/status if `API_DOMAIN` is set) — Public
    -   Returns the job status and result (or error) for async requests:
    -   Response (200): { status: 'success', data: { id, status, result?, error?, meta?, created_at, updated_at } }

### Google Maps Embed

-   POST /api/maps/pin (or POST /maps/pin if `API_DOMAIN` is set)

    -   Public: does not require authentication (no Bearer token needed)
    -   No API key required — uses Google Maps embed URL format

    -   Body: { address: string (required), zoom?: integer, width?: integer, height?: integer }
    -   Validation rules:
        -   `address` => required|string|max:500
        -   `zoom` => sometimes|integer|min:1|max:21 (default: 15)
        -   `width` => sometimes|integer|min:1|max:2048 (default: 600, for iframe)
        -   `height` => sometimes|integer|min:1|max:2048 (default: 450, for iframe)
    -   Action: Returns Google Maps URLs for embedding and linking. No API key required.
    -   Response structure:
        ```json
        {
            "status": "success",
            "message": "Map URLs generated",
            "data": {
                "embed_url": "https://maps.google.com/maps?q=...&z=15&output=embed",
                "maps_link": "https://www.google.com/maps/search/?api=1&query=...",
                "iframe": "<iframe width=\"600\" height=\"450\" src=\"...\"></iframe>",
                "address": "1600 Amphitheatre Parkway, Mountain View, CA",
                "zoom": 15
            },
            "code": 200,
            "timestamp": "2025-11-29T12:00:00Z"
        }
        ```
    -   Response fields:
        -   `embed_url` — URL for use in an iframe `src` attribute (no API key needed)
        -   `maps_link` — Direct link to Google Maps (opens in browser/app)
        -   `iframe` — Ready-to-use HTML iframe element
        -   `address` — The original address provided
        -   `zoom` — The zoom level used
    -   Errors & failure modes:
        -   422 Validation failed — missing or invalid address/fields

### Ping / health check

-   GET /api/ping — returns a simple JSON response with `{ "status": "ok" }`. If `API_DOMAIN` is set you can use `GET /ping` on your API subdomain (e.g. `https://api.example.com/ping`) to check it is reachable.

### Error response format

When requests fail, the API returns a consistent JSON error structure with an appropriate HTTP status code. The standard fields are:

-   `status` — always `error` for failed responses.
-   `message` — a short human readable message such as `Unauthenticated`, `Validation failed`, or `Resource not found`.
-   `errors` — (nullable) an object keyed by field names for validation or structured errors; otherwise `null`.
-   `code` — the HTTP status code returned (e.g., `401`, `422`, `404`, `500`).
-   `timestamp` — ISO 8601 timestamp when the response was generated.

**Authentication errors (401):** Protected routes (e.g., `GET /user`) require a valid Bearer token. If you access these routes without a token or with an expired/invalid token, the API returns a 401 JSON response — it will **never** redirect to a login page. Your frontend should handle 401 responses by prompting the user to log in.

Example 401 (Unauthenticated):

```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
    "status": "error",
    "message": "Unauthenticated.",
    "errors": null,
    "code": 401,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 422 (Validation error):

```
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json

{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    },
    "code": 422,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 403 (Forbidden):

```
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
    "status": "error",
    "message": "Forbidden.",
    "errors": null,
    "code": 403,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 404 (Not found):

```
HTTP/1.1 404 Not Found
Content-Type: application/json

{
    "status": "error",
    "message": "Resource not found.",
    "errors": null,
    "code": 404,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 405 (Method not allowed):

```
HTTP/1.1 405 Method Not Allowed
Content-Type: application/json

{
    "status": "error",
    "message": "Method not allowed.",
    "errors": null,
    "code": 405,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 500 (Server error):

```
HTTP/1.1 500 Internal Server Error
Content-Type: application/json

{
    "status": "error",
    "message": "Server Error",
    "errors": null,
    "code": 500,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Notes:

-   Internally thrown exceptions return the same standard format. When `APP_DEBUG=true`, additional debug details may be included in the response (`exception`, `trace`) to help troubleshooting; avoid enabling debug in production.
-   The API **never redirects** to a login page. All errors are returned as JSON with appropriate HTTP status codes. Your SPA/mobile client should handle these codes accordingly (e.g., redirect to login on 401, show validation errors on 422).

### Success response format

Standard success responses follow a consistent JSON response shape used throughout the API:

-   `status` — always `success` for successful responses.
-   `message` — brief human readable description (e.g., `OK`, `Registered`, `Logged in`).
-   `data` — JSON object or array containing the payload for the successful operation.
-   `code` — HTTP status code (e.g., `200` for OK, `201` for created).
-   `timestamp` — ISO 8601 timestamp at the time of response.

Example success response (GET /api/ping):

```
HTTP/1.1 200 OK
Content-Type: application/json

{
    "status": "success",
    "message": "OK",
    "data": {"status": "ok"},
    "code": 200,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

    -   Body: { lat: number, lng: number, label?: string, zoom?: integer, width?: integer, height?: integer }
    -   Action: Returns a URL to a Google Static Maps image with the requested pin.
    -   Response structure: { status: 'success', data: { map_url: 'https://maps.googleapis.com/...' } }

---
