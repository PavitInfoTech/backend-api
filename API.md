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
        -   username (string, required, unique)
        -   first_name (string, required)
        -   last_name (string, optional)
        -   email (string, required)
        -   password (string, required)
        -   password_confirmation (string, required)
    -   Success (201):
        -   { status: 'success', message: 'Registered', data: { user: {...}, token: '...'} }

    Example request (register):

    ```json
    POST /api/auth/register
    Content-Type: application/json

    {
        "username": "johndoe",
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "password": "secret123",
        "password_confirmation": "secret123"
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
        "password": "secret123"
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

    -   Request body: { email, password }
    -   Success (200): { status: 'success', message: 'Logged in', data: { user, token } }

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

    ### GitHub OAuth

    -   GET /api/auth/github/redirect — Redirects the browser to GitHub's OAuth consent page (via Socialite). If your SPA needs the URL to redirect itself, call this endpoint and read the Location header.
    -   GET /api/auth/github/callback — OAuth callback endpoint which handles the GitHub response and returns a token in JSON (for API clients) or sets a secure `api_token` cookie and redirects to the SPA route on success.

    Behavior is identical to Google OAuth flow but uses the `github` Socialite driver:

    -   Creates the user if not present and saves `provider_name` = 'github' and `provider_id`.
    -   If a user already exists with the same email, the code attaches `provider` fields to that existing user rather than creating a new one.
    -   Returns JSON with `user` and `token` in API flows, and sets an `api_token` cookie on browser flows.

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

        -   Body: { current_password, password, password_confirmation }
        -   Behavior: Authenticated endpoint. Validates the user's current password, and if valid, updates the password to the new one. This does *not* revoke active API tokens by default (frontend should re-login to refresh tokens if desired).
        -   Success (200): { status: 'success', message: 'Password changed successfully' }
        -   Errors:
            - 401 Unauthenticated — missing or invalid token
            - 422 Validation failed — wrong current password or invalid new password/confirmation

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

    -   Body: { email }
    -   Action: Adds an email for newsletter signup (stub — you should save to DB or 3rd party service).

-   POST /api/mail/password-reset (or POST /mail/password-reset if `API_DOMAIN` is set)
    -   Body: { email }
    -   Action: Placeholder to send password reset email — integrate Laravel's password reset flow for production.

### AI / Gorq

-   POST /api/ai/generate (or POST /ai/generate if `API_DOMAIN` is set)
-   POST /api/ai/generate

    -   Body: { prompt: string (required), model?: string, max_tokens?: integer, async?: boolean }
    -   Validation rules:
        -   `prompt` => required|string|max:5000
        -   `model` => sometimes|string|max:255
        -   `max_tokens` => sometimes|integer|min:1|max:2048
        -   `async` => sometimes|boolean
    -   Action: Validates and sanitizes prompt input, logs the request (ai_requests table) and either:
        -   Synchronous (default): forwards the request to the configured GORQ service (via `GORQ_API_KEY`) and returns provider result. The `ai_requests` record is updated with status and result.
        -   Async (async=true): creates an `ai_requests` record (status `pending`) and dispatches a queued job to process the request. Responds 202 Accepted with `job_id` and `status_url` to poll.
    -   Rate limiting: protected by `throttle:ai` rate limiter (per-user or by IP). Configure with `AI_RATE_LIMIT_PER_MINUTE` (default 60/min).
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
        - 401 Unauthenticated — needs Bearer token or valid cookie
        - 422 Validation failed — missing prompt or invalid params
        - 429 Too Many Requests — rate limiter triggered (AI rate limiter `throttle:ai`)
        - 500 Server Error — AI provider failure or internal error

-   GET /api/ai/jobs/{id}/status (or GET /ai/jobs/{id}/status if `API_DOMAIN` is set)
    -   Returns the job status and result (or error) for async requests:
    -   Response (200): { status: 'success', data: { id, status, result?, error?, meta?, created_at, updated_at } }

### Google Maps / Pinned static map

-   POST /api/maps/pin (or POST /maps/pin if `API_DOMAIN` is set)

    -   Protected: requires authentication (Bearer token or auth cookie).

    -   Body: { lat: number, lng: number, address?: string, label?: string, zoom?: integer, width?: integer, height?: integer }
    -   Validation rules:
        -   `lat` => required|numeric
        -   `lng` => required|numeric
        -   `label` => sometimes|string|max:64
        -   `zoom` => sometimes|integer|min:0|max:21
        -   `width` => sometimes|integer|min:1|max:2048
        -   `height` => sometimes|integer|min:1|max:2048
    -   Action: Returns a URL to a Google Static Maps image with the requested pin.
    -   Response structure: { status: 'success', data: { map_url: 'https://maps.googleapis.com/...' } }
    -   Errors & failure modes:
        -   401 Unauthenticated — needs Bearer token
        -   422 Validation failed — missing/invalid lat or lng or other fields
        -   422 Address could not be geocoded — when address is provided and no geocoding results were found
        -   500 Server Error — missing or invalid `GOOGLE_MAPS_API_KEY` environment variable

### Ping / health check

-   GET /api/ping — returns a simple JSON response with `{ "status": "ok" }`. If `API_DOMAIN` is set you can use `GET /ping` on your API subdomain (e.g. `https://api.example.com/ping`) to check it is reachable.

### Error response format

When requests fail, the API returns a consistent JSON error structure with an appropriate HTTP status code. The standard fields are:

-   `status` — always `error` for failed responses.
-   `message` — a short human readable message such as `Unauthenticated`, `Validation failed`, or `Resource not found`.
-   `errors` — (nullable) an object keyed by field names for validation or structured errors; otherwise `null`.
-   `code` — the HTTP status code returned (e.g., `401`, `422`, `404`, `500`).
-   `timestamp` — ISO 8601 timestamp when the response was generated.

**Authentication errors (401):** Protected routes (e.g., `GET /user`, `POST /ai/generate`) require a valid Bearer token. If you access these routes without a token or with an expired/invalid token, the API returns a 401 JSON response — it will **never** redirect to a login page. Your frontend should handle 401 responses by prompting the user to log in.

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
