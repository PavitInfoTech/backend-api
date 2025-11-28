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
-   GOOGLE_REDIRECT — OAuth callback (default: `${APP_URL}/api/auth/google/callback`)
-   GORQ_API_KEY — API key for Gorq (or your AI provider)
-   GORQ_BASE_URL — Base URL for Gorq API (default `https://api.gorq.ai`)
-   GORQ_DEFAULT_MODEL — Optional default model to use
-   GOOGLE_MAPS_API_KEY — Google Maps API key (for static map pins)
-   FRONTEND_URL — Frontend SPA address for CORS/callbacks
-   SANCTUM_STATEFUL_DOMAINS — If using Sanctum for SPA auth

The repo already contains mail config examples (MAIL\_\* in `.env.example`) for sending messages.

---

## Routes summary

All API endpoints are exposed from `routes/api.php`.

-   Default (development/testing): If `API_DOMAIN` is not set, routes are served with the `/api` prefix (e.g., `/api/auth/login`).
-   Subdomain mode (production): If you set `API_DOMAIN` to your API subdomain (e.g., `api.example.com`), the same `routes/api.php` endpoints are exposed at the root path on that domain — e.g., `https://api.example.com/auth/login` (no `/api` prefix).

For a full guide on configuring Google Cloud credentials, Socialite server usage, and SPA redirect handling (secure token flows and examples), see `docs/socialite-google-spa.md`.

### Authentication

-   POST /api/auth/register (or POST /auth/register if `API_DOMAIN` is set)
    -   Request body (application/json):
        -   name (string, required)
        -   email (string, required)
        -   password (string, required)
        -   password_confirmation (string, required)
    -   Success (201):
        -   { status: 'success', message: 'Registered', data: { user: {...}, token: '...'} }
    -   Note: This endpoint now issues a Laravel Sanctum personal access token (plain text). Save this token client-side and send it on protected requests with the Authorization header:

```
Authorization: Bearer <your-plain-text-token-here>
```

-   POST /api/auth/login (or POST /auth/login if `API_DOMAIN` is set)

    -   Request body: { email, password }
    -   Success (200): { status: 'success', message: 'Logged in', data: { user, token } }

-   GET /api/auth/google/redirect (or GET /auth/google/redirect if `API_DOMAIN` is set)

    -   Redirects to Google OAuth consent page using Laravel Socialite. This endpoint issues an HTTP redirect (302) that should be followed by the browser or frontend app. If your frontend needs the direct URL instead, call this endpoint and read the Location header of the response.

-   GET /api/auth/google/callback (or GET /auth/google/callback if `API_DOMAIN` is set)

    -   OAuth callback — handled with Laravel Socialite.
    -   Behavior:
        -   Socialite reads Google user info (id, name, email, avatar).
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

-   POST /api/auth/password/forgot (or POST /auth/password/forgot if `API_DOMAIN` is set)

    -   Body: { email }
    -   Behavior: server will create a password reset token stored in `password_reset_tokens` (valid for ~2 hours) and email the frontend password-reset link to the user if the account exists. The response does not reveal whether the account exists.
    -   Success (200): { status: 'success', message: 'Password reset link sent if account exists' }

-   POST /api/auth/password/reset (or POST /auth/password/reset if `API_DOMAIN` is set)

    -   Body: { email, token, password, password_confirmation }
    -   Behavior: verifies the reset token, ensures it is not expired (2 hours), updates the user's password, deletes the token, and returns a new API token so the user is authenticated immediately.
    -   Success (200): { status: 'success', message: 'Password reset successfully', data: { user: {...}, token: '<plain-text-token>' } }

-   POST /api/auth/verify/send (or POST /auth/verify/send if `API_DOMAIN` is set)

    -   Body: { email } or (authenticated) send to current user
    -   Behavior: creates an email verification token stored in `email_verification_tokens` and sends a verification email with a backend callback URL. The callback verifies the token and marks the account as verified.
    -   Success (200): { status: 'success', message: 'Verification email sent' }

-   GET /api/auth/verify/{token} (or GET /auth/verify/{token} if `API_DOMAIN` is set)

    -   Behavior: verifies the token, sets `email_verified_at` for the user, deletes the token, and either returns JSON (API clients) or redirects the browser to `${FRONTEND_URL}/auth/verified`.
    -   Success (200 or 302): JSON { status: 'success', message: 'Email verified', data: { user } } or 302 redirect to frontend verified page.

-   POST /api/auth/logout (or POST /auth/logout if `API_DOMAIN` is set)
    -   POST /api/auth/logout
        -   Behavior: - For API clients (Accept: application/json), the endpoint revokes the current bearer token (or all tokens for the user) and returns JSON: { status: 'success', message: 'Logged out' } — the response includes a Set-Cookie header clearing the `api_token` cookie if present. - For browser flows (normal HTML requests), the endpoint will revoke tokens and return a 302 redirect to `${FRONTEND_URL}/auth/logout`, setting an HttpOnly cookie deletion header so the token never remains in the browser.

### User profile (protected)

-   GET /api/user (or GET /user if `API_DOMAIN` is set)

    -   Returns current authenticated user's profile.

-   PUT /api/user (or PUT /user if `API_DOMAIN` is set)

    -   Body (optional any): { name, email, avatar }
    -   Validates unique email and updates the user.

-   POST /api/user/avatar (or POST /user/avatar if `API_DOMAIN` is set)

    -   Multipart/form-data: file field `avatar` (image, max 5MB)
    -   Behavior: authenticated endpoint. Validates and stores the uploaded image under `storage/app/public/avatars/{user_id}/` and returns the public URL. If a previous avatar was stored on the server it will be deleted.
    -   Success (200): { status: 'success', message: 'Avatar uploaded', data: { avatar_url: '<url>' } }

-   GET /api/users/{id}/public (or GET /users/{id}/public if `API_DOMAIN` is set)
    -   Returns a limited public profile object suitable for other users or public pages: { id, name, avatar, created_at }

Notes: run `php artisan storage:link` in deployment to make `storage/app/public` available at `/storage` so avatar URLs are reachable by the browser.

### Mail endpoints

-   POST /api/mail/contact (or POST /mail/contact if `API_DOMAIN` is set)

    -   Body: { name, email, message }
    -   Action: Sends a contact message to the configured `MAIL_FROM_ADDRESS`.

-   POST /api/mail/newsletter (or POST /mail/newsletter if `API_DOMAIN` is set)

    -   Body: { email }
    -   Action: Adds an email for newsletter signup (stub — you should save to DB or 3rd party service).

-   POST /api/mail/password-reset (or POST /mail/password-reset if `API_DOMAIN` is set)
    -   Body: { email }
    -   Action: Placeholder to send password reset email — integrate Laravel's password reset flow for production.

### AI / Gorq

-   POST /api/ai/generate (or POST /ai/generate if `API_DOMAIN` is set)
-   POST /api/ai/generate

    -   Body: { prompt: string, model?: string, max_tokens?: integer, async?: boolean }
    -   Action: Validates and sanitizes prompt input, logs the request (ai_requests table) and either:
        -   Synchronous (default): forwards the request to the configured GORQ service (via `GORQ_API_KEY`) and returns provider result. The `ai_requests` record is updated with status and result.
        -   Async (async=true): creates an `ai_requests` record (status `pending`) and dispatches a queued job to process the request. Responds 202 Accepted with `job_id` and `status_url` to poll.
    -   Rate limiting: protected by `throttle:ai` rate limiter (per-user or by IP). Configure with `AI_RATE_LIMIT_PER_MINUTE` (default 60/min).
    -   Example successful sync response: { status: 'success', data: { ... } }
    -   Example async accepted response: { status: 'accepted', data: { job_id: 123, status_url: '/api/ai/jobs/123/status' } }

-   GET /api/ai/jobs/{id}/status (or GET /ai/jobs/{id}/status if `API_DOMAIN` is set)
    -   Returns the job status and result (or error) for async requests:
    -   Response (200): { status: 'success', data: { id, status, result?, error?, meta?, created_at, updated_at } }

### Google Maps / Pinned static map

-   POST /api/maps/pin (or POST /maps/pin if `API_DOMAIN` is set)

### Ping / health check

-   GET /api/ping — returns a simple JSON response with `{ "status": "ok" }`. If `API_DOMAIN` is set you can use `GET /ping` on your API subdomain (e.g. `https://api.example.com/ping`) to check it is reachable.

### Error response format

When requests fail, the API returns a consistent JSON error structure with an appropriate HTTP status code. The standard fields are:

-   `status` — always `error` for failed responses.
-   `message` — a short human readable message such as `Unauthenticated`, `Validation failed`, or `Resource not found`.
-   `errors` — (nullable) an object keyed by field names for validation or structured errors; otherwise `null`.
-   `code` — the HTTP status code returned (e.g., `401`, `422`, `404`, `500`).
-   `timestamp` — ISO 8601 timestamp when the response was generated.

Example 401 (Unauthenticated):

```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
    "status": "error",
    "message": "Unauthenticated",
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
    "message": "Validation failed",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    },
    "code": 422,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Example 404 (Not found):

```
HTTP/1.1 404 Not Found
Content-Type: application/json

{
    "status": "error",
    "message": "Resource not found",
    "errors": null,
    "code": 404,
    "timestamp": "2025-11-28T12:34:56Z"
}
```

Notes:

-   Internally thrown exceptions return the same standard format. When `APP_DEBUG=true`, additional debug details may be included in the response (`exception`, `trace`) to help troubleshooting; avoid enabling debug in production.

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

## Implementation notes & next steps

-   Replaced the placeholder token generation with Laravel Sanctum personal access tokens. Registration/login now issue real Sanctum tokens; the app includes a `personal_access_tokens` migration and the `User` model uses the `HasApiTokens` trait.
-   Install and configure Laravel Socialite to perform Google OAuth flows and to populate `provider_name`, `provider_id`, and `avatar` fields (see the migration).
-   Improve email flows: create `Mail` classes for templating and integrate password reset using Laravel's built-in features.
-   Add tests for each endpoint and add a simple Postman / OpenAPI specification if you want SDKs or automated testing.

If you'd like, I can next:

-   add Sanctum + Socialite installation instructions and scaffold config code, or
-   implement real token-based auth flows and sample Postman/OpenAPI specs.
