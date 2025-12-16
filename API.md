# Backend API — Endpoints & Configuration

This document describes the API routes, required request parameters, example responses, and environment variables to configure for Google OAuth, email, Gorq AI, and Google Maps integration.

---

## Quick setup / migrations

-   Add required environment variables in `.env` (see `.env.example`).
-   Run database migrations:

```powershell
php artisan migrate
```

### Database tables

The following tables are created by the migrations:

| Table                       | Description                                                                                                                                                                                 |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`                     | User accounts with fields: id, username, first_name, last_name, email, email_verified_at, password, provider_name, provider_id, avatar, current_plan, remember_token, timestamps            |
| `password_reset_tokens`     | Password reset tokens (email, token, created_at)                                                                                                                                            |
| `sessions`                  | Session storage for web authentication                                                                                                                                                      |
| `personal_access_tokens`    | Sanctum API tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, timestamps)                                                                         |
| `email_verification_tokens` | Email verification tokens (email, token, created_at)                                                                                                                                        |
| `ai_requests`               | AI generation request logs (id, user_id, model, prompt, status, result, error, tokens_used, meta, timestamps)                                                                               |
| `newsletter_subscribers`    | Newsletter subscriptions (id, name, email, verification_token, verified_at, unsubscribe_token, timestamps)                                                                                  |
| `subscription_plans`        | Available plans (id, name, slug, description, price, currency, interval, trial_days, features, is_active, timestamps)                                                                       |
| `payments`                  | Payment records (id, user_id, transaction_id, gateway, amount, currency, status, type, card_last_four, card_brand, description, plan_name, gateway_response, metadata, paid_at, timestamps) |
| `cache`                     | Laravel cache storage                                                                                                                                                                       |
| `cache_locks`               | Laravel cache locks                                                                                                                                                                         |
| `jobs`                      | Laravel queue jobs                                                                                                                                                                          |
| `job_batches`               | Laravel queue job batches                                                                                                                                                                   |
| `failed_jobs`               | Failed queue jobs                                                                                                                                                                           |

Installed and recommended packages:

-   Laravel Sanctum — installed and configured for personal access tokens (token-based API auth)
-   Laravel Socialite — configured for Google/GitHub OAuth flows

---

## Environment variables (added/required)

These variables were added to `.env.example` and must be configured in your `.env` when you connect services:

-   GOOGLE_CLIENT_ID — Google OAuth client ID (Socialite)
-   GOOGLE_CLIENT_SECRET — Google OAuth secret
-   GOOGLE_REDIRECT — OAuth callback (default: `${APP_URL}/auth/google/callback`)
-   GITHUB_CLIENT_ID — GitHub OAuth client ID
-   GITHUB_CLIENT_SECRET — GitHub OAuth secret
-   GITHUB_REDIRECT — GitHub OAuth callback
-   GORQ_API_KEY — API key for Gorq (or your AI provider)
-   GORQ_BASE_URL — Base URL for Gorq API (default `https://api.gorq.ai`)
-   GORQ_DEFAULT_MODEL — Optional default model to use
-   FRONTEND_URL — Frontend SPA address for CORS/callbacks
-   SANCTUM_STATEFUL_DOMAINS — If using Sanctum for SPA auth

The repo already contains mail config examples (MAIL\_\* in `.env.example`) for sending messages.

---

## Routes summary

All API endpoints are exposed from `routes/api.php` and served from the API subdomain (e.g., `https://api.example.com/auth/login`).

### All endpoints at a glance

| Method              | URI                                    | Description                               | Auth  |
| ------------------- | -------------------------------------- | ----------------------------------------- | ----- |
| GET                 | `/ping`                                | Health check                              | No    |
| **Authentication**  |                                        |                                           |       |
| POST                | `/auth/register`                       | Register new user                         | No    |
| POST                | `/auth/login`                          | Login with email/password                 | No    |
| POST                | `/auth/logout`                         | Logout (revoke token)                     | Yes   |
| GET                 | `/auth/google/redirect`                | Redirect to Google OAuth                  | No    |
| GET                 | `/auth/google/callback`                | Google OAuth callback                     | No    |
| POST                | `/auth/google/token`                   | Exchange Google code/credential for token | No    |
| GET                 | `/auth/github/redirect`                | Redirect to GitHub OAuth                  | No    |
| GET                 | `/auth/github/callback`                | GitHub OAuth callback                     | No    |
| POST                | `/auth/github/token`                   | Exchange GitHub code/token for API token  | No    |
| POST                | `/auth/password/forgot`                | Request password reset email              | No    |
| POST                | `/auth/password/reset`                 | Reset password with token                 | No    |
| POST                | `/auth/password/change`                | Change password (authenticated)           | Yes   |
| POST                | `/auth/verify/send`                    | Send/resend verification email            | No    |
| GET                 | `/auth/verify/{token}`                 | Verify email with token                   | No    |
| GET                 | `/auth/link/google/redirect`           | Link Google account                       | Yes   |
| GET                 | `/auth/link/google/callback`           | Google link callback                      | Yes   |
| GET                 | `/auth/link/github/redirect`           | Link GitHub account                       | Yes   |
| GET                 | `/auth/link/github/callback`           | GitHub link callback                      | Yes   |
| POST                | `/auth/unlink`                         | Unlink OAuth provider                     | Yes   |
| **User Profile**    |                                        |                                           |       |
| GET                 | `/user`                                | Get current user profile                  | Yes   |
| PUT                 | `/user`                                | Update user profile                       | Yes   |
| POST                | `/user/avatar`                         | Upload avatar image                       | Yes   |
| DELETE              | `/user`                                | Delete user account                       | Yes   |
| GET                 | `/users/{id}/public`                   | Get public profile                        | No    |
| **Mail**            |                                        |                                           |       |
| POST                | `/mail/contact`                        | Send contact message                      | No    |
| POST                | `/mail/newsletter`                     | Subscribe to newsletter                   | No    |
| GET                 | `/mail/newsletter/verify/{token}`      | Verify newsletter subscription            | No    |
| GET                 | `/mail/newsletter/unsubscribe/{token}` | Unsubscribe from newsletter               | No    |
| POST                | `/mail/password-reset`                 | Send password reset email                 | No    |
| **AI / Gorq**       |                                        |                                           |       |
| POST                | `/ai/generate`                         | Generate AI response                      | No    |
| GET                 | `/ai/jobs/{id}/status`                 | Get async AI job status                   | No    |
| **Maps**            |                                        |                                           |       |
| POST                | `/maps/pin`                            | Generate Google Maps embed URL            | No    |
| **Payments**        |                                        |                                           |       |
| POST                | `/subscriptions`                       | Pay for a plan (purchase)                 | Yes   |
| POST                | `/payments/process`                    | Process one-time payment                  | Yes   |
| GET                 | `/payments`                            | List payment history                      | Yes   |
| GET                 | `/payments/last-plan`                  | Get last purchased plan                   | Yes   |
| GET                 | `/payments/{transactionId}`            | Verify/get payment details                | Yes   |
| POST                | `/payments/refund/{transactionId}`     | Request refund                            | Yes   |
| POST                | `/payments/revert-plan`                | Revert/clear current plan                 | Yes   |
| POST                | `/payments/webhook`                    | Payment webhook handler                   | No    |
| GET                 | `/subscription-plans`                  | List available plans                      | No    |
| GET                 | `/subscription-plans/{slug}`           | Get plan details                          | No    |
| POST                | `/subscription-plans`                  | Create new plan                           | Yes   |
| PUT                 | `/subscription-plans/{id}`             | Update plan                               | Yes   |
| DELETE              | `/subscription-plans/{id}`             | Delete plan                               | Yes   |
| **Admin/Dev Tools** |                                        |                                           |       |
| POST                | `/admin/migrate`                       | Run migrations via HTTP                   | Token |

---

## Authentication

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

-   POST /auth/register

    -   **Body (JSON):**
        -   `username` (string, required, max 255, unique) — desired username
        -   `first_name` (string, required, max 255) — user's first name
        -   `last_name` (string, optional, max 255) — user's last name
        -   `email` (string, required, valid email, unique) — user's email address
        -   `password_hash` (string, required, 64 hex chars) — SHA-256 hash of the password
        -   `password_hash_confirmation` (string, required) — must match password_hash
    -   **Behavior:**
        -   Creates a new user account with the provided details
        -   Automatically sends an email verification link
        -   Returns a Sanctum personal access token for immediate authentication
    -   **Success (201):**
        ```json
        {
            "status": "success",
            "message": "Registered. Please check your email to verify your account.",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "first_name": "John",
                    "last_name": "Doe",
                    "email": "john@example.com",
                    "avatar": null,
                    "email_verified_at": null,
                    "current_plan": null,
                    "created_at": "2025-12-12T10:30:00Z",
                    "updated_at": "2025-12-12T10:30:00Z"
                },
                "token": "1|abc123def456..."
            },
            "code": 201,
            "timestamp": "2025-12-12T10:30:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Validation failed:
            ```json
            {
                "status": "error",
                "message": "Validation failed",
                "errors": {
                    "username": ["The username has already been taken."],
                    "email": ["The email has already been taken."]
                },
                "code": 422,
                "timestamp": "2025-12-12T10:30:00Z"
            }
            ```
        -   500 — Server error

-   POST /auth/login

    -   **Body (JSON):**
        -   `email` (string, required) — user's email address
        -   `password_hash` (string, required, 64 hex chars) — SHA-256 hash of the password
    -   **Behavior:**
        -   Validates the email/password combination
        -   Returns a Sanctum personal access token if valid
    -   **Success (200):**
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
                    "avatar": "https://api.example.com/storage/avatars/avatar_123.jpg",
                    "email_verified_at": "2025-12-12T10:35:00Z",
                    "current_plan": "pro"
                },
                "token": "1|def456ghi789..."
            },
            "code": 200,
            "timestamp": "2025-12-12T10:35:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Invalid credentials:
            ```json
            {
                "status": "error",
                "message": "Invalid credentials",
                "code": 401,
                "timestamp": "2025-12-12T10:35:00Z"
            }
            ```
        -   422 — Validation failed

#### Logout (token & cookie aware)

-   POST /auth/logout

    -   Behavior: API clients get JSON + token revocation; browser requests (Accept HTML) revoke tokens, clear the `api_token` cookie, and 302 redirect to `${FRONTEND_URL}/auth/logout`.
    -   Success (200 or 302): JSON `{ status: 'success', message: 'Logged out' }` or 302 redirect to frontend logout page.

#### Google OAuth (browser redirect flow)

-   GET /auth/google/redirect

    -   Redirects to Google OAuth consent page using Laravel Socialite. This endpoint issues an HTTP redirect (302) that should be followed by the browser or frontend app. If your frontend needs the direct URL instead, call this endpoint and read the Location header of the response.

-   GET /auth/google/callback

    -   OAuth callback — handled with Laravel Socialite.
    -   Behavior:
        -   Socialite reads Google user info (id, name, email, avatar). The backend will map provider `name` into `first_name` and `last_name` where possible and generate a `username` using the preferred username or email localpart.
        -   If a user exists with the same `provider_name` + `provider_id`, that user is returned.
        -   Otherwise the backend attempts to find a user by email and attach Google provider data.
        -   If no matching user exists, a new user is created and provider fields (`provider_name`, `provider_id`, `avatar`) are saved.
        -   A Laravel Sanctum personal access token is created.
        -   Behavior detail:
            -   **By default (browser flow):** The server redirects (302) to `${FRONTEND_URL}/auth/complete?token=<sanctum-token>`. The token is passed as a query parameter for the frontend to extract and store.
            -   **JSON response:** Only returned when the request is an explicit AJAX call (`X-Requested-With: XMLHttpRequest`), the `Accept` header specifically prefers `application/json` without `text/html` or `*/*`, or the query param `?format=json` is present.
    -   Browser flow response (Redirect — default for all browser requests):
        -   302 redirect to `${FRONTEND_URL}/auth/complete?token=<plain-text-token>`
        -   The frontend should extract the token from the URL, store it, and replace the URL in browser history to remove the token.
    -   API flow response (JSON — only when explicitly requested):
        -   { status: 'success', message: 'Authenticated via Google', data: { user: {...}, token: '<plain-text-token>' } }
        -   To get JSON, use one of: `?format=json` query param, `X-Requested-With: XMLHttpRequest` header, or `Accept: application/json` (without `text/html` or `*/*`).

-   POST /auth/google/token

    -   **Body (JSON):**
        -   `code` (string) — authorization code received from Google OAuth (required if `credential` missing)
        -   `credential` (string) — ID token from Google One Tap / Credential API (required if `code` missing)
        -   `redirect_uri` (string, optional) — override redirect URI used during code exchange
    -   **Behavior:**
        -   If `code` is provided, the backend exchanges it against Google's token endpoint using the configured `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, obtains an access token, resolves the user profile via Socialite, and creates/logs in the user.
        -   If `credential` (ID token) is provided, the backend verifies it using Google's `tokeninfo` endpoint and uses the resulting profile to authenticate the user.
        -   Always returns JSON (no redirects) with the Sanctum token.
    -   **Success response (200):**
        ```json
        {
            "status": "success",
            "message": "Authenticated via Google",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "first_name": "John",
                    "last_name": "Doe",
                    "email": "john@example.com",
                    "avatar": "https://lh3.googleusercontent.com/a/ABC123...",
                    "email_verified_at": "2025-12-12T13:05:00Z",
                    "current_plan": null,
                    "created_at": "2025-12-12T13:05:00Z",
                    "updated_at": "2025-12-12T13:05:00Z"
                },
                "token": "1|google_token_123..."
            },
            "code": 200,
            "timestamp": "2025-12-12T13:05:00Z"
        }
        ```
    -   **Errors:**
        -   400 — Invalid/expired code or credential, or Google API error:
            ```json
            {
                "status": "error",
                "message": "Invalid Google credential",
                "code": 400,
                "timestamp": "2025-12-12T13:05:00Z"
            }
            ```
        -   422 — Missing `code`/`credential` payload

#### GitHub OAuth (browser redirect flow)

-   GET /auth/github/redirect

    -   Redirects the browser to GitHub's OAuth consent page (via Socialite). If your SPA needs the URL to redirect itself, call this endpoint and read the Location header.

-   GET /auth/github/callback

    -   OAuth callback endpoint which handles the GitHub response and redirects to `${FRONTEND_URL}/auth/complete?token=<token>` for browser flows or returns JSON for API flows.

#### GitHub OAuth behavior

Behavior is identical to Google OAuth flow but uses the `github` Socialite driver:

-   Creates the user if not present and saves `provider_name` = 'github' and `provider_id`.
-   If a user already exists with the same email, the code attaches `provider` fields to that existing user rather than creating a new one.
-   Redirects with token in query param on browser flows, returns JSON with token for API flows.

-   POST /auth/github/token

    -   **Body (JSON):**
        -   `code` (string) — authorization code returned by GitHub's OAuth authorize endpoint (required if `access_token` missing)
        -   `access_token` (string) — GitHub access token obtained on the client (required if `code` missing)
        -   `redirect_uri` (string, optional) — custom redirect URI used when generating the code
    -   **Behavior:**
        -   When a `code` is provided, the backend calls `https://github.com/login/oauth/access_token` with your app's client ID/secret to exchange it for an access token, then fetches the user profile using Socialite and logs the user in.
        -   When `access_token` is provided directly, it is used immediately to fetch the GitHub profile.
        -   Always responds with JSON, returning `{ user, token }` on success or a structured 400 error on failure.
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Authenticated via GitHub",
            "data": {
                "user": {
                    "id": 124,
                    "username": "janedoe",
                    "first_name": "Jane",
                    "last_name": "Doe",
                    "email": "jane@example.com",
                    "avatar": "https://avatars.githubusercontent.com/u/123456?v=4",
                    "email_verified_at": "2025-12-12T13:10:00Z",
                    "current_plan": null,
                    "created_at": "2025-12-12T13:10:00Z",
                    "updated_at": "2025-12-12T13:10:00Z"
                },
                "token": "1|github_token_456..."
            },
            "code": 200,
            "timestamp": "2025-12-12T13:10:00Z"
        }
        ```
    -   **Errors:**
        -   400 — Code exchange failed or provided access token invalid/expired:
            ```json
            {
                "status": "error",
                "message": "GitHub authentication failed",
                "code": 400,
                "timestamp": "2025-12-12T13:10:00Z"
            }
            ```
        -   422 — Neither `code` nor `access_token` provided

#### Password reset

-   POST /auth/password/forgot

    -   **Body (JSON):**
        -   `email` (string, required, valid email) — user's email address
    -   **Behavior:**
        -   Creates a password reset token stored in `password_reset_tokens` (valid for ~2 hours)
        -   Emails the frontend password-reset link to the user if the account exists
        -   The response does not reveal whether the account exists for security
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Password reset link sent if account exists",
            "code": 200,
            "timestamp": "2025-12-12T10:40:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Invalid email format

-   POST /auth/password/reset

    -   **Body (JSON):**
        -   `email` (string, required, valid email) — user's email address
        -   `token` (string, required) — password reset token from email link
        -   `password_hash` (string, required, 64 hex chars) — new SHA-256 hash of the password
        -   `password_hash_confirmation` (string, required) — must match password_hash
    -   **Behavior:**
        -   Verifies the reset token and ensures it is not expired (2 hours)
        -   Updates the user's password hash
        -   Deletes the token
        -   Returns a new API token so the user is authenticated immediately
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Password reset successfully",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "first_name": "John",
                    "last_name": "Doe",
                    "email": "john@example.com",
                    "avatar": "https://api.example.com/storage/avatars/avatar_123.jpg",
                    "email_verified_at": "2025-12-12T10:35:00Z",
                    "current_plan": "pro"
                },
                "token": "1|newtoken123..."
            },
            "code": 200,
            "timestamp": "2025-12-12T10:45:00Z"
        }
        ```
    -   **Errors:**
        -   400 — Invalid or expired token
        -   422 — Validation failed (password mismatch, invalid format)

#### Email verification

-   POST /auth/verify/send

    -   **Body (JSON):**
        -   `email` (string, optional, valid email) — email address to verify (if not provided, uses authenticated user's email)
    -   **Headers:**
        -   `Authorization: Bearer <token>` (optional if email provided)
    -   **Behavior:**
        -   Creates/updates an email verification token stored in `email_verification_tokens`
        -   Sends a verification email with a link containing the token
        -   For security, the response does not reveal whether the email exists if not authenticated
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Verification email sent",
            "code": 200,
            "timestamp": "2025-12-12T10:50:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated (if no email provided)
        -   422 — Invalid email format
        -   429 — Too many requests

-   GET /auth/verify/{token}

    -   **URL Parameters:**
        -   `token` (string, required) — email verification token from email link
    -   **Behavior:**
        -   Verifies the token and sets `email_verified_at` timestamp for the user
        -   Deletes the token
        -   Either returns JSON (API clients) or redirects the browser to `${FRONTEND_URL}/auth/verified`
    -   **Success (200 for API, 302 for browser):**
        -   API Response (200):
            ```json
            {
                "status": "success",
                "message": "Email verified",
                "data": {
                    "user": {
                        "id": 123,
                        "username": "johndoe",
                        "first_name": "John",
                        "last_name": "Doe",
                        "email": "john@example.com",
                        "email_verified_at": "2025-12-12T10:55:00Z",
                        "current_plan": "pro"
                    }
                },
                "code": 200,
                "timestamp": "2025-12-12T10:55:00Z"
            }
            ```
        -   Browser Response (302): Redirect to `${FRONTEND_URL}/auth/verified`
    -   **Errors:**
        -   400 — Invalid or expired token

### Social account linking / unlinking

-   GET `/auth/link/{provider}/redirect` + `/auth/link/{provider}/callback` (authenticated) allow existing users to attach Google/GitHub accounts to their profile.
-   POST `/auth/unlink` removes the provider association.
-   These routes require Bearer tokens (they live in the authenticated group) and return JSON.

### User profile (protected)

-   GET /user

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Behavior:**
        -   Returns the current authenticated user's complete profile
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "User profile",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "first_name": "John",
                    "last_name": "Doe",
                    "email": "john@example.com",
                    "avatar": "https://api.example.com/storage/avatars/avatar_123.jpg",
                    "email_verified_at": "2025-12-12T10:35:00Z",
                    "current_plan": "pro",
                    "created_at": "2025-12-01T08:00:00Z",
                    "updated_at": "2025-12-12T10:35:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T11:00:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated

-   PUT /user

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Body (JSON, all fields optional):**
        -   `username` (string, max 255, unique) — new username
        -   `first_name` (string, max 255) — new first name
        -   `last_name` (string, max 255) — new last name
        -   `email` (string, valid email, unique) — new email address
        -   `avatar` (string, valid URL) — new avatar URL
    -   **Behavior:**
        -   Updates only the provided fields
        -   Validates uniqueness for username and email
        -   If email is changed, sets `email_verified_at` to null and sends verification email
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Profile updated",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe_updated",
                    "first_name": "John",
                    "last_name": "Smith",
                    "email": "johnsmith@example.com",
                    "avatar": "https://api.example.com/storage/avatars/avatar_123.jpg",
                    "email_verified_at": null,
                    "current_plan": "pro"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T11:05:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated
        -   422 — Validation failed:
            ```json
            {
                "status": "error",
                "message": "Validation failed",
                "errors": {
                    "username": ["The username has already been taken."],
                    "email": ["The email has already been taken."]
                },
                "code": 422,
                "timestamp": "2025-12-12T11:05:00Z"
            }
            ```

-   POST /user/avatar

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Body (multipart/form-data):**
        -   `avatar` (file, required) — image file (jpg, png, gif, webp, max 5MB)
    -   **Behavior:**
        -   Stores the uploaded image in `storage/app/public/avatars/`
        -   Updates the user's avatar field with the public URL
        -   Overwrites any existing avatar
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Avatar uploaded",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "first_name": "John",
                    "last_name": "Doe",
                    "email": "john@example.com",
                    "avatar": "https://api.example.com/storage/avatars/avatar_123_1734000000.jpg",
                    "current_plan": "pro"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T11:10:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated
        -   422 — Invalid file type or size:
            ```json
            {
                "status": "error",
                "message": "Validation failed",
                "errors": {
                    "avatar": [
                        "The avatar must be an image.",
                        "The avatar may not be greater than 5120 kilobytes."
                    ]
                },
                "code": 422,
                "timestamp": "2025-12-12T11:10:00Z"
            }
            ```

-   DELETE /user

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Behavior:**
        -   Permanently deletes the user account and all associated data (tokens, payments, etc.)
        -   This action cannot be undone
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Account deleted",
            "code": 200,
            "timestamp": "2025-12-12T11:15:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated

-   GET /users/{id}/public

    -   **URL Parameters:**
        -   `id` (integer, required) — user ID
    -   **Behavior:**
        -   Returns limited public profile information for the specified user
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Public profile",
            "data": {
                "user": {
                    "id": 456,
                    "username": "janedoe",
                    "first_name": "Jane",
                    "last_name": "Doe",
                    "avatar": "https://api.example.com/storage/avatars/avatar_456.jpg"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T11:20:00Z"
        }
        ```
    -   **Errors:**
        -   404 — User not found:
            ```json
            {
                "status": "error",
                "message": "User not found",
                "code": 404,
                "timestamp": "2025-12-12T11:20:00Z"
            }
            ```

### Mail endpoints

-   POST /mail/contact

    -   **Body (JSON):**
        -   `name` (string, required, max 255) — sender's name
        -   `email` (string, required, valid email) — sender's email address
        -   `subject` (string, required, max 255) — message subject
        -   `message` (string, required, max 1000) — message content
    -   **Behavior:**
        -   Sends a contact email to the configured admin email address
        -   Includes sender information in the email
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Message sent",
            "code": 200,
            "timestamp": "2025-12-12T11:25:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Validation failed:
            ```json
            {
                "status": "error",
                "message": "Validation failed",
                "errors": {
                    "email": ["The email must be a valid email address."],
                    "message": [
                        "The message may not be greater than 1000 characters."
                    ]
                },
                "code": 422,
                "timestamp": "2025-12-12T11:25:00Z"
            }
            ```

-   POST /mail/newsletter

    -   **Body (JSON):**
        -   `email` (string, required, valid email) — subscriber's email address
        -   `name` (string, optional, max 255) — subscriber's name
    -   **Behavior:**
        -   Subscribes the email to the newsletter
        -   Sends a verification email to confirm subscription
        -   Creates a record in `newsletter_subscribers` table
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Subscription verification sent",
            "code": 200,
            "timestamp": "2025-12-12T11:30:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Invalid email format

-   GET /mail/newsletter/verify/{token}

    -   **URL Parameters:**
        -   `token` (string, required) — newsletter verification token from email
    -   **Behavior:**
        -   Verifies the newsletter subscription using the token
        -   Sets `verified_at` timestamp and clears the verification token
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Newsletter subscription verified",
            "code": 200,
            "timestamp": "2025-12-12T11:35:00Z"
        }
        ```
    -   **Errors:**
        -   400 — Invalid token

-   GET /mail/newsletter/unsubscribe/{token}

    -   **URL Parameters:**
        -   `token` (string, required) — newsletter unsubscribe token
    -   **Behavior:**
        -   Unsubscribes the email from the newsletter using the token
        -   Removes the subscriber record or marks as unsubscribed
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Unsubscribed from newsletter",
            "code": 200,
            "timestamp": "2025-12-12T11:40:00Z"
        }
        ```
    -   **Errors:**
        -   400 — Invalid token

-   POST /mail/password-reset

    -   **Body (JSON):**
        -   `email` (string, required, valid email) — user's email address
    -   **Behavior:**
        -   Alias for `/auth/password/forgot` - sends password reset email
        -   Same behavior as the auth endpoint
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Password reset link sent if account exists",
            "code": 200,
            "timestamp": "2025-12-12T11:45:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Invalid email format

### AI / Gorq endpoints

-   POST /ai/generate

    -   **Body (JSON):**
        -   `prompt` (string, required, max 10000) — the text prompt for AI generation
        -   `model` (string, optional) — AI model to use (defaults to configured default)
        -   `temperature` (number, optional, 0.0-2.0) — creativity/randomness level
        -   `max_tokens` (integer, optional) — maximum tokens to generate
        -   `async` (boolean, optional) — whether to process asynchronously (default: false)
    -   **Behavior:**
        -   Generates AI response using Gorq API
        -   Supports both sync and async modes
        -   Rate limited by `throttle:ai` middleware
        -   Logs request in `ai_requests` table
    -   **Success (200 for sync, 202 for async):**
        -   Sync Response (200):
            ```json
            {
                "status": "success",
                "message": "Generated",
                "data": {
                    "result": "This is the AI-generated response based on your prompt...",
                    "tokens_used": 150,
                    "model": "gpt-3.5-turbo",
                    "request_id": "req_abc123"
                },
                "code": 200,
                "timestamp": "2025-12-12T11:50:00Z"
            }
            ```
        -   Async Response (202):
            ```json
            {
                "status": "success",
                "message": "Request queued for processing",
                "data": {
                    "job_id": "job_123456",
                    "status": "queued",
                    "estimated_time": 30
                },
                "code": 202,
                "timestamp": "2025-12-12T11:50:00Z"
            }
            ```
    -   **Errors:**
        -   422 — Validation failed (invalid prompt, model, etc.)
        -   429 — Rate limit exceeded
        -   503 — AI service unavailable

-   GET /ai/jobs/{id}/status

    -   **URL Parameters:**
        -   `id` (string, required) — job ID from async generation request
    -   **Behavior:**
        -   Returns the status of an async AI generation job
        -   Includes result when completed
    -   **Success (200):**
        -   Queued/Processing:
            ```json
            {
                "status": "success",
                "message": "Job status",
                "data": {
                    "status": "processing",
                    "progress": 75,
                    "estimated_completion": "2025-12-12T11:52:00Z"
                },
                "code": 200,
                "timestamp": "2025-12-12T11:51:00Z"
            }
            ```
        -   Completed:
            ```json
            {
                "status": "success",
                "message": "Job completed",
                "data": {
                    "status": "completed",
                    "result": "This is the completed AI-generated response...",
                    "tokens_used": 150,
                    "model": "gpt-3.5-turbo",
                    "completed_at": "2025-12-12T11:51:30Z"
                },
                "code": 200,
                "timestamp": "2025-12-12T11:51:30Z"
            }
            ```
        -   Failed:
            ```json
            {
                "status": "success",
                "message": "Job failed",
                "data": {
                    "status": "failed",
                    "error": "AI service temporarily unavailable",
                    "error_code": "SERVICE_UNAVAILABLE"
                },
                "code": 200,
                "timestamp": "2025-12-12T11:51:00Z"
            }
            ```
    -   **Errors:**
        -   404 — Job not found

### Maps endpoint

-   POST /maps/pin

    -   **Body (JSON):**
        -   `address` (string, required, max 500) — full address to geocode and map
        -   `zoom` (integer, optional, 1-20, default 15) — map zoom level
        -   `size` (string, optional, default "400x300") — embed size in format "WIDTHxHEIGHT"
    -   **Behavior:**
        -   Geocodes the address using Google Maps API
        -   Generates a Google Maps embed URL and direct link
        -   Returns both embeddable iframe URL and direct maps link
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Map generated",
            "data": {
                "embed_url": "https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY&q=1600+Amphitheatre+Parkway,+Mountain+View,+CA&zoom=15",
                "maps_link": "https://www.google.com/maps/search/?api=1&query=1600+Amphitheatre+Parkway%2C+Mountain+View%2C+CA",
                "address": "1600 Amphitheatre Parkway, Mountain View, CA, USA",
                "coordinates": {
                    "lat": 37.4224764,
                    "lng": -122.0842499
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:00:00Z"
        }
        ```
    -   **Errors:**
        -   422 — Validation failed:
            ```json
            {
                "status": "error",
                "message": "Validation failed",
                "errors": {
                    "address": ["The address field is required."]
                },
                "code": 422,
                "timestamp": "2025-12-12T12:00:00Z"
            }
            ```
        -   400 — Address not found or geocoding failed
        -   503 — Google Maps API unavailable

### Subscription plans

**Note:** These endpoints are currently disabled in the routes configuration but documented for future implementation.

-   GET /subscription-plans

    -   **Behavior:**
        -   Returns all available subscription plans ordered by price
        -   Only includes active plans (`is_active = true`)
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Plans retrieved",
            "data": {
                "plans": [
                    {
                        "id": 1,
                        "name": "Basic Plan",
                        "slug": "basic",
                        "description": "Perfect for getting started",
                        "price": "9.99",
                        "currency": "USD",
                        "interval": "month",
                        "trial_days": 7,
                        "features": ["Feature 1", "Feature 2"],
                        "is_active": true,
                        "created_at": "2025-01-01T00:00:00Z",
                        "updated_at": "2025-01-01T00:00:00Z"
                    },
                    {
                        "id": 2,
                        "name": "Pro Plan",
                        "slug": "pro",
                        "description": "For power users",
                        "price": "19.99",
                        "currency": "USD",
                        "interval": "month",
                        "trial_days": 14,
                        "features": [
                            "All Basic features",
                            "Advanced feature",
                            "Priority support"
                        ],
                        "is_active": true,
                        "created_at": "2025-01-01T00:00:00Z",
                        "updated_at": "2025-01-01T00:00:00Z"
                    }
                ]
            },
            "code": 200,
            "timestamp": "2025-12-12T12:05:00Z"
        }
        ```

-   GET /subscription-plans/{slug}

    -   **URL Parameters:**
        -   `slug` (string, required) — plan slug (e.g., "basic", "pro")
    -   **Behavior:**
        -   Returns details for a specific plan by slug
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Plan retrieved",
            "data": {
                "plan": {
                    "id": 2,
                    "name": "Pro Plan",
                    "slug": "pro",
                    "description": "For power users",
                    "price": "19.99",
                    "currency": "USD",
                    "interval": "month",
                    "trial_days": 14,
                    "features": [
                        "All Basic features",
                        "Advanced feature",
                        "Priority support"
                    ],
                    "is_active": true,
                    "created_at": "2025-01-01T00:00:00Z",
                    "updated_at": "2025-01-01T00:00:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:10:00Z"
        }
        ```
    -   **Errors:**
        -   404 — Plan not found:
            ```json
            {
                "status": "error",
                "message": "Subscription plan not found",
                "code": 404,
                "timestamp": "2025-12-12T12:10:00Z"
            }
            ```

### Payments

-   POST /subscriptions

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Body (JSON):**
        -   `plan_slug` (string, required) — slug of the plan to subscribe to
        -   `payment_method` (object, required) — payment method details:
            -   `card_number` (string, required) — card number (13-19 digits)
            -   `expiry_month` (string, required) — expiry month (01-12)
            -   `expiry_year` (string, required) — expiry year (YY format)
            -   `cvv` (string, required) — CVV code (3-4 digits)
            -   `card_holder` (string, required) — cardholder name
        -   `billing_address` (object, optional) — billing address:
            -   `line1` (string) — address line 1
            -   `city` (string) — city
            -   `state` (string) — state/province
            -   `postal_code` (string) — postal code
            -   `country` (string) — country code (ISO 3166-1 alpha-2)
    -   **Behavior:**
        -   Processes payment for a subscription plan
        -   Creates payment record and updates user's current_plan
        -   Uses sandbox payment gateway for testing
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Subscription created",
            "data": {
                "payment": {
                    "id": 123,
                    "transaction_id": "TXN_SANDBOX_123456",
                    "gateway": "sandbox",
                    "amount": "19.99",
                    "currency": "USD",
                    "status": "completed",
                    "type": "subscription",
                    "plan_name": "pro",
                    "card_last_four": "4242",
                    "card_brand": "visa",
                    "paid_at": "2025-12-12T12:15:00Z",
                    "created_at": "2025-12-12T12:15:00Z"
                },
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "current_plan": "pro",
                    "updated_at": "2025-12-12T12:15:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:15:00Z"
        }
        ```
    -   **Errors:**
        -   401 — Unauthenticated
        -   422 — Validation failed or invalid plan
        -   402 — Payment failed (card declined, insufficient funds, etc.)

-   POST /payments/process

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Body (JSON):**
        -   `amount` (string/number, required) — payment amount
        -   `currency` (string, required, 3 chars) — currency code (e.g., "USD")
        -   `payment_method` (object, required) — same structure as subscription endpoint
        -   `description` (string, optional) — payment description
    -   **Behavior:**
        -   Processes a one-time payment (not subscription)
        -   Creates payment record but doesn't change user's plan
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Payment processed",
            "data": {
                "payment": {
                    "id": 124,
                    "transaction_id": "TXN_SANDBOX_123457",
                    "gateway": "sandbox",
                    "amount": "49.99",
                    "currency": "USD",
                    "status": "completed",
                    "type": "one-time",
                    "description": "Premium feature purchase",
                    "card_last_four": "4242",
                    "card_brand": "visa",
                    "paid_at": "2025-12-12T12:20:00Z",
                    "created_at": "2025-12-12T12:20:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:20:00Z"
        }
        ```

-   GET /payments

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Behavior:**
        -   Returns the authenticated user's payment history
        -   Ordered by most recent first
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Payments retrieved",
            "data": {
                "payments": [
                    {
                        "id": 124,
                        "transaction_id": "TXN_SANDBOX_123457",
                        "gateway": "sandbox",
                        "amount": "49.99",
                        "currency": "USD",
                        "status": "completed",
                        "type": "one-time",
                        "description": "Premium feature purchase",
                        "card_last_four": "4242",
                        "card_brand": "visa",
                        "paid_at": "2025-12-12T12:20:00Z",
                        "created_at": "2025-12-12T12:20:00Z"
                    },
                    {
                        "id": 123,
                        "transaction_id": "TXN_SANDBOX_123456",
                        "gateway": "sandbox",
                        "amount": "19.99",
                        "currency": "USD",
                        "status": "completed",
                        "type": "subscription",
                        "plan_name": "pro",
                        "card_last_four": "4242",
                        "card_brand": "visa",
                        "paid_at": "2025-12-12T12:15:00Z",
                        "created_at": "2025-12-12T12:15:00Z"
                    }
                ]
            },
            "code": 200,
            "timestamp": "2025-12-12T12:25:00Z"
        }
        ```

-   GET /payments/last-plan

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Behavior:**
        -   Returns the user's most recent subscription plan purchase
        -   Includes both payment and plan details
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Last plan retrieved",
            "data": {
                "plan": {
                    "id": 2,
                    "name": "Pro Plan",
                    "slug": "pro",
                    "description": "For power users",
                    "price": "19.99",
                    "currency": "USD",
                    "interval": "month"
                },
                "payment": {
                    "id": 123,
                    "transaction_id": "TXN_SANDBOX_123456",
                    "amount": "19.99",
                    "currency": "USD",
                    "status": "completed",
                    "paid_at": "2025-12-12T12:15:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:30:00Z"
        }
        ```

-   GET /payments/{transactionId}

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **URL Parameters:**
        -   `transactionId` (string, required) — payment transaction ID
    -   **Behavior:**
        -   Returns details for a specific payment transaction
        -   User can only access their own payments
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Payment details",
            "data": {
                "payment": {
                    "id": 123,
                    "transaction_id": "TXN_SANDBOX_123456",
                    "gateway": "sandbox",
                    "amount": "19.99",
                    "currency": "USD",
                    "status": "completed",
                    "type": "subscription",
                    "plan_name": "pro",
                    "card_last_four": "4242",
                    "card_brand": "visa",
                    "description": null,
                    "paid_at": "2025-12-12T12:15:00Z",
                    "created_at": "2025-12-12T12:15:00Z",
                    "updated_at": "2025-12-12T12:15:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:35:00Z"
        }
        ```
    -   **Errors:**
        -   404 — Payment not found or doesn't belong to user

-   POST /payments/refund/{transactionId}

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **URL Parameters:**
        -   `transactionId` (string, required) — payment transaction ID
    -   **Behavior:**
        -   Requests a refund for the specified payment
        -   Only works for completed payments
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Refund requested",
            "data": {
                "refund": {
                    "id": 456,
                    "payment_id": 123,
                    "transaction_id": "REFUND_SANDBOX_789012",
                    "amount": "19.99",
                    "currency": "USD",
                    "status": "completed",
                    "processed_at": "2025-12-12T12:40:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:40:00Z"
        }
        ```

-   POST /payments/revert-plan

    -   **Headers:**
        -   `Authorization: Bearer <token>` (required)
    -   **Behavior:**
        -   Reverts the user's current plan to null
        -   Used when subscription is cancelled or payment fails
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Plan reverted",
            "data": {
                "user": {
                    "id": 123,
                    "username": "johndoe",
                    "current_plan": null,
                    "updated_at": "2025-12-12T12:45:00Z"
                }
            },
            "code": 200,
            "timestamp": "2025-12-12T12:45:00Z"
        }
        ```

-   POST /payments/webhook

    -   **Body:** Webhook payload from payment provider (varies by provider)
    -   **Behavior:**
        -   Handles payment provider webhooks for payment status updates
        -   No authentication required (secured by webhook signature validation)
        -   Updates payment status, triggers subscription changes, etc.
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Webhook processed",
            "code": 200,
            "timestamp": "2025-12-12T12:50:00Z"
        }
        ```

## Error Response Format

All API endpoints follow a consistent error response format:

```json
{
    "status": "error",
    "message": "Human-readable error message",
    "errors": {
        "field_name": ["Specific validation error message"],
        "another_field": ["Another error message"]
    },
    "code": 400,
    "timestamp": "2025-12-12T13:15:00Z"
}
```

### Common HTTP Status Codes

-   **200** — Success
-   **201** — Created (for registration)
-   **302** — Redirect (OAuth flows)
-   **400** — Bad Request (invalid input, expired tokens)
-   **401** — Unauthorized (missing/invalid token)
-   **404** — Not Found (resource doesn't exist)
-   **422** — Validation Failed (invalid input format)
-   **429** — Too Many Requests (rate limited)
-   **500** — Internal Server Error
-   **503** — Service Unavailable (external API down)

### Authentication Errors

When a protected endpoint is accessed without proper authentication:

```json
{
    "status": "error",
    "message": "Unauthenticated",
    "code": 401,
    "timestamp": "2025-12-12T13:15:00Z"
}
```

### Validation Errors

When request data fails validation:

```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "email": ["The email must be a valid email address."],
        "password_hash": ["The password hash must be 64 characters."]
    },
    "code": 422,
    "timestamp": "2025-12-12T13:15:00Z"
}
```

### Ping endpoint

-   GET /ping

    -   **Behavior:**
        -   Simple health check endpoint
        -   Returns basic status information
        -   No authentication required
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "OK",
            "data": {
                "status": "ok"
            },
            "code": 200,
            "timestamp": "2025-12-12T12:55:00Z"
        }
        ```

### Developer tools

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
curl -X POST https://api.example.com/admin/migrate \
    -H "X-RUN-MIG-TOKEN: $RUN_MIG_TOKEN"
```

Response:

-   **Success (200):**
    ```json
    {
        "status": "success",
        "message": "Migrations completed",
        "data": {
            "output": "Migrating: 2025_01_01_000000_create_users_table\nMigrated:  2025_01_01_000000_create_users_table\n...",
            "exit_code": 0
        },
        "code": 200,
        "timestamp": "2025-12-12T13:00:00Z"
    }
    ```
-   **Error (500):**
    ```json
    {
        "status": "error",
        "message": "Migration failed",
        "data": {
            "output": "Error: SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'users' already exists...",
            "exit_code": 1
        },
        "code": 500,
        "timestamp": "2025-12-12T13:00:00Z"
    }
    ```

Security note: After running migrations via HTTP, disable ALLOW_RUN_MIG or rotate the token. This endpoint provides a convenient but sensitive capability and should be restricted to trusted usage only.

### Subscription Plan Management

These endpoints allow authenticated users to manage subscription plans through the API.

-   POST /subscription-plans

    -   **Authentication:** Required (Bearer token)
    -   **Parameters:**
        -   `name` (string, required): Plan name
        -   `slug` (string, required): Unique URL slug
        -   `description` (string, required): Plan description
        -   `price` (number, required): Plan price
        -   `currency` (string, required): Currency code (e.g., "USD")
        -   `interval` (string, required): "monthly" or "yearly"
        -   `trial_days` (integer, optional): Trial period in days
        -   `features` (array, optional): Array of feature strings
        -   `is_active` (boolean, optional): Whether plan is active
    -   **Success (201):**
        ```json
        {
            "status": "success",
            "message": "Subscription plan created",
            "data": {
                "id": 1,
                "name": "Pro Plan",
                "slug": "pro",
                "description": "Professional plan",
                "price": "19.99",
                "currency": "USD",
                "interval": "monthly",
                "trial_days": 14,
                "features": ["Unlimited AI", "Priority support"],
                "is_active": true
            },
            "code": 201,
            "timestamp": "2025-12-12T12:00:00Z"
        }
        ```

-   PUT /subscription-plans/{id}

    -   **Authentication:** Required (Bearer token)
    -   **Parameters:** Same as create, all optional for partial updates
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Subscription plan updated",
            "data": {
                "id": 1,
                "name": "Updated Pro Plan",
                "slug": "pro",
                "price": "24.99"
            },
            "code": 200,
            "timestamp": "2025-12-12T12:00:00Z"
        }
        ```

-   DELETE /subscription-plans/{id}

    -   **Authentication:** Required (Bearer token)
    -   **Behavior:** Deletes a subscription plan (fails if plan has active subscriptions)
    -   **Success (200):**
        ```json
        {
            "status": "success",
            "message": "Subscription plan deleted",
            "code": 200,
            "timestamp": "2025-12-12T12:00:00Z"
        }
        ```
    -   **Error (422) - Active subscriptions:**
        ```json
        {
            "status": "error",
            "message": "Cannot delete plan with active subscriptions",
            "code": 422,
            "timestamp": "2025-12-12T12:00:00Z"
        }
        ```
