# Backend API Endpoints Documentation

**Version**: 1.0  
**Base URL**: `(http://localhost/api)` (or your domain)  
**API Response Format**: All responses are JSON  

---

## Table of Contents
1. [Authentication](#authentication)
2. [User Profile](#user-profile)
3. [Payments & Subscriptions](#payments--subscriptions)
4. [AI Generation](#ai-generation)
5. [Email & Messaging](#email--messaging)
6. [Maps](#maps)
7. [CAPTCHA](#captcha)
8. [Response Format](#response-format)

---

## Authentication

### 1. Register User
**Endpoint**: `POST /api/auth/register`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "username": "john_doe",
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password_hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3",
  "password_hash_confirmation": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3",
  "turnstile_token": "optional_if_captcha_enabled",
  "recaptcha_token": "optional_if_captcha_enabled"
}
```

**Notes**:
- `password_hash` must be SHA-256 hash (64 hex characters) - hash on frontend before sending
- `password_hash_confirmation` must match `password_hash` exactly
- If CAPTCHA is enabled (either Turnstile or reCAPTCHA), provide at least one of `turnstile_token` or `recaptcha_token`
- `turnstile_token` must be generated with action "register" when calling Turnstile's execute() method
- `recaptcha_token` is obtained from reCAPTCHA widget
- Automatically sends email verification link to user
- User must verify email before checking email_verified_at

**Response** (201 Created):
```json
{
  "status": "success",
  "message": "Registered. Please check your email to verify your account.",
  "data": {
    "user": {
      "id": 1,
      "username": "john_doe",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "avatar": null,
      "current_plan": null,
      "provider_name": null,
      "provider_id": null,
      "created_at": "2026-02-24T12:00:00Z",
      "updated_at": "2026-02-24T12:00:00Z"
    },
    "token": "1|abcdefghijklmnopqrstuvwxyz"
  },
  "code": 201,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Username/email already exists or invalid format
- `422 Validation Error`: CAPTCHA failed (if enabled)

**Database Flow**:
- Check if username/email unique in `users` table
- Create new record in `users` table with hashed password
- Create API token in `personal_access_tokens` table via Sanctum
- Send verification email via mail queue

---

### 2. Login User
**Endpoint**: `POST /api/auth/login`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "email": "john@example.com",
  "password_hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3"
}
```

**Notes**:
- `password_hash` must be SHA-256 hash of user's password
- Uses secure hash comparison to prevent timing attacks
- Returns new API token each login

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Logged in",
  "data": {
    "user": {
      "id": 1,
      "username": "john_doe",
      "email": "john@example.com",
      "email_verified_at": "2026-02-24T11:50:00Z",
      "avatar": "https://example.com/avatars/user1/avatar.jpg",
      "current_plan": "pro",
      "created_at": "2026-02-24T12:00:00Z",
      "updated_at": "2026-02-24T12:00:00Z"
    },
    "token": "1|abcdefghijklmnopqrstuvwxyz"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `401 Unauthorized`: Email not found or password incorrect

---

### 3. Logout User
**Endpoint**: `POST /api/auth/logout`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Headers**:
```
Authorization: Bearer <token>
```

**Request Body**: Empty

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Logged out",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Revoke current API token from `personal_access_tokens` table
- Clear any API token cookies

---

### 4. Send Email Verification
**Endpoint**: `POST /api/auth/verify/send`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Throttled  

**Request Body**: Empty

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Verification email sent",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Generate unique verification token
- Send verification link via email

---

### 5. Verify Email
**Endpoint**: `GET /api/auth/verify/{token}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `token`: Unique token sent in verification email

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Email verified",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `404 Not Found`: Invalid or expired token
- `422 Validation Error`: Email already verified

**Database Flow**:
- Find token in password_reset_tokens or similar table
- Update `email_verified_at` field for user
- Delete used token

---

### 6. Send Password Reset Email
**Endpoint**: `POST /api/auth/password/forgot`  
**Auth Required**: ❌ No  
**Rate Limit**: Throttled  

**Request Body**:
```json
{
  "email": "john@example.com",
  "turnstile_token": "optional_if_captcha_enabled",
  "recaptcha_token": "optional_if_captcha_enabled"
}
```

**Notes**:
- If CAPTCHA is enabled (either Turnstile or reCAPTCHA), provide at least one of `turnstile_token` or `recaptcha_token`
- `turnstile_token` must be generated with action "password_reset" when calling Turnstile's execute() method
- `recaptcha_token` is obtained from reCAPTCHA widget

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Password reset link sent to email",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Find user by email
- Generate unique reset token
- Store token in `password_reset_tokens` table
- Send email with reset link

---

### 7. Reset Password
**Endpoint**: `POST /api/auth/password/reset`  
**Auth Required**: ❌ No  
**Rate Limit**: Throttled  

**Request Body**:
```json
{
  "token": "reset_token_from_email",
  "email": "john@example.com",
  "password_hash": "new_sha256_hash_here"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Password reset successfully",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Invalid token or email mismatch

**Database Flow**:
- Verify token in `password_reset_tokens` table
- Update user password in `users` table
- Delete used token
- Revoke all existing API tokens for security

---

### 8. Change Password (Authenticated)
**Endpoint**: `POST /api/auth/password/change`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Headers**:
```
Authorization: Bearer <token>
```

**Request Body**:
```json
{
  "current_password_hash": "sha256_of_current_password",
  "password_hash": "sha256_of_new_password"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Password changed",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `401 Unauthorized`: Current password incorrect
- `422 Validation Error`: Validation failed

---

### 9. Google OAuth Login
**Endpoint**: `POST /api/auth/google/token`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "access_token": "google_access_token_from_frontend"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Logged in with Google",
  "data": {
    "user": {...},
    "token": "1|abcdefghijklmnopqrstuvwxyz"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Requires Google OAuth credentials in settings
- Creates user if doesn't exist (OAuth signup)
- Links existing user if email matches

**Database Flow**:
- Exchange Google token for user info
- Check if user exists by email or provider_id
- Create user if not exists with provider info
- Update/create API token

---

### 10. GitHub OAuth Login
**Endpoint**: `POST /api/auth/github/token`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "access_token": "github_access_token_from_frontend"
}
```

**Response**: Same as Google OAuth (200 OK)

**Notes**:
- Requires GitHub OAuth credentials in settings
- Creates user if doesn't exist
- Links existing user by email

---

### 11. Link Google Account (Authenticated)
**Endpoint**: `GET /api/auth/link/google/redirect`  
**Auth Required**: ✅ Yes (Bearer Token + Session)  
**Rate Limit**: Standard  

**Query Parameters**:
- `redirect_uri`: (optional) Where to redirect after linking

**Response**: Redirects to Google OAuth consent screen

**Database Flow**:
- User authorizes account linking
- Callback updates user's `provider_name` and `provider_id`

---

### 12. Unlink OAuth Provider
**Endpoint**: `POST /api/auth/unlink`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "provider": "google"
}
```

---

### 13. OAuth Callback Redirect (External)
**Endpoint**: `GET /api/auth/{provider}/redirect`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Response**: Redirects to OAuth provider (Google/GitHub).  

**Post-Authentication Redirect**:  
Upon successful login/registration via the OAuth provider, the backend system redirects the user back to the frontend application with an authenticated system `token`.

**Target URL**: `https://frontend.com/auth/complete?token={api_token}`

**Query Parameters**:
- `token`: The internal API token generated for the authenticated user.

---
  "provider": "google"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Provider unlinked",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Clear provider_name and provider_id for user

---

## User Profile

### 1. Get Current User Profile
**Endpoint**: `GET /api/user`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Headers**:
```
Authorization: Bearer <token>
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "User profile",
  "data": {
    "id": 1,
    "username": "john_doe",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "avatar": "https://example.com/avatars/user1/avatar.jpg",
    "current_plan": "pro",
    "email_verified_at": "2026-02-24T11:50:00Z",
    "created_at": "2026-02-24T12:00:00Z",
    "updated_at": "2026-02-24T12:00:00Z"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `401 Unauthorized`: Invalid or expired token

---

### 2. Update User Profile
**Endpoint**: `PUT /api/user`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "username": "john_doe_updated",
  "first_name": "Johnny",
  "last_name": "Smith",
  "email": "newemail@example.com",
  "avatar": "https://example.com/new-avatar.jpg"
}
```

**Notes**:
- All fields are optional
- Username/email must remain unique
- Avatar can be URL to external image

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Profile updated",
  "data": {
    "id": 1,
    "username": "john_doe_updated",
    "first_name": "Johnny",
    ...
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Username/email already exists

**Database Flow**:
- Update user record in `users` table
- Validate unique constraints before save

---

### 3. Upload Avatar
**Endpoint**: `POST /api/user/avatar`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  
**Content-Type**: multipart/form-data

**Form Data**:
```
avatar: <image_file>
```

**Accepted Formats**: JPG, JPEG, PNG, GIF, WebP  
**Max Size**: 5MB  

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Avatar uploaded",
  "data": {
    "avatar": "https://example.com/storage/avatars/user1/random_12chars_timestamp.jpg",
    "id": 1,
    ...
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: File too large or invalid format
- `400 Bad Request`: No file uploaded

**Database Flow**:
- Store file in `storage/app/public/avatars/{user_id}/`
- Update user `avatar` field with accessible URL
- Delete old avatar file if exists
- Return accessible public URL

---

### 4. Get Public User Profile
**Endpoint**: `GET /api/users/{id}/public`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `id`: User ID

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "User profile",
  "data": {
    "id": 1,
    "username": "john_doe",
    "first_name": "John",
    "avatar": "https://example.com/avatars/user1/avatar.jpg"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Only returns public fields (no email, created_at)
- Does not require authentication

---

### 5. Delete User Account
**Endpoint**: `DELETE /api/user`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**: Empty

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "User deleted",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Delete user record from `users` table
- Delete associated personal access tokens
- Delete user's payments (cascade)
- Delete user's avatar files

---

## Payments & Subscriptions

### 1. List Subscription Plans
**Endpoint**: `GET /api/subscription-plans`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Subscription plans retrieved",
  "data": [
    {
      "id": 1,
      "name": "Starter",
      "slug": "starter",
      "description": "For small teams getting started with data convergence.",
      "price": 49.00,
      "monthly_price": 49.00,
      "yearly_price": 39.00,
      "currency": "USD",
      "interval": "monthly",
      "trial_days": 0,
      "features": ["Up to 5 data sources", "10K records/month", "Core analytics", "Email support", "1 user seat"],
      "is_active": true,
      "popular": false,
      "created_at": "2026-02-24T10:00:00Z",
      "updated_at": "2026-02-24T10:00:00Z"
    },
    {
      "id": 2,
      "name": "Professional",
      "slug": "professional",
      "description": "For growing businesses with complex data needs.",
      "price": 149.00,
      "monthly_price": 149.00,
      "yearly_price": 119.00,
      "currency": "USD",
      "interval": "monthly",
      "trial_days": 0,
      "features": ["Unlimited data sources", "1M records/month", "Advanced analytics & ML", "Priority support", "10 user seats", "Custom dashboards"],
      "is_active": true,
      "popular": true,
      "created_at": "2026-02-24T10:00:00Z",
      "updated_at": "2026-02-24T10:00:00Z"
    },
    {
      "id": 3,
      "name": "Enterprise",
      "slug": "enterprise",
      "description": "For large organizations requiring full control.",
      "price": null,
      "monthly_price": null,
      "yearly_price": null,
      "currency": "USD",
      "interval": "monthly",
      "trial_days": 0,
      "features": ["Unlimited everything", "Dedicated infrastructure", "SSO & SAML", "24/7 phone support", "Unlimited seats", "Custom SLAs", "On-premises option"],
      "is_active": true,
      "popular": false,
      "created_at": "2026-02-24T10:00:00Z",
      "updated_at": "2026-02-24T10:00:00Z"
    }
  ],
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Query active plans from `subscription_plans` table
- Order by price ascending

---

### 2. Get Single Plan
**Endpoint**: `GET /api/subscription-plans/{slug}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `slug`: Plan slug (e.g., "pro", "basic")

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Plan retrieved",
  "data": {
    "id": 2,
    "name": "Professional",
    "slug": "professional",
    "description": "For growing businesses with complex data needs.",
    "price": 149.00,
    "monthly_price": 149.00,
    "yearly_price": 119.00,
    "currency": "USD",
    "interval": "monthly",
    "trial_days": 0,
    "features": ["Unlimited data sources", "1M records/month", "Advanced analytics & ML", "Priority support"],
    "is_active": true,
    "popular": true
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `404 Not Found`: Plan doesn't exist

---

### 3. Subscribe to Plan
**Endpoint**: `POST /api/subscriptions`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "plan_slug": "pro",
  "payment_method": {
    "card_number": "4111111111111111",
    "expiry_month": "12",
    "expiry_year": "25",
    "cvv": "123",
    "card_holder": "John Doe"
  },
  "billing_address": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "zip": "10001",
    "country": "USA"
  }
}
```

**Notes**:
- For testing, card numbers starting with "4111" are treated as valid
- Card data validation: length 13-19 digits, expiry 2 digits, CVV 3-4 digits
- Creates plan if doesn't exist (for custom plans)
- Processes payment via sandbox gateway if price > 0

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Subscription processed",
  "data": {
    "payment": {
      "id": 1,
      "user_id": 1,
      "plan_slug": "pro",
      "transaction_id": "txn_2026024_00001",
      "amount": 29.99,
      "currency": "USD",
      "status": "successful",
      "created_at": "2026-02-24T12:00:00Z"
    },
    "user": {
      "id": 1,
      "current_plan": "pro",
      ...
    }
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Invalid card data
- `422 Validation Error`: Payment processing failed

**Database Flow**:
- Create plan if doesn't exist
- Process payment via SandboxPaymentGateway
- Create record in `payments` table
- Update user's `current_plan` field
- Email receipt to user

---

### 4. Process Payment
**Endpoint**: `POST /api/payments/process`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "amount": 50.00,
  "currency": "USD",
  "payment_method": {
    "card_number": "4111111111111111",
    "expiry_month": "12",
    "expiry_year": "25",
    "cvv": "123",
    "card_holder": "John Doe"
  },
  "reference": "custom_payment_ref"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Payment processed",
  "data": {
    "transaction_id": "txn_2026024_00002",
    "amount": 50.00,
    "currency": "USD",
    "status": "successful",
    "created_at": "2026-02-24T12:00:00Z"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Process payment via gateway
- Record in `payments` table
- Email confirmation

---

### 5. List User Payments
**Endpoint**: `GET /api/payments`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Query Parameters**:
- `status`: Filter by status (successful, failed, pending)
- `limit`: Pagination limit (default 20)
- `offset`: Pagination offset (default 0)

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Payments retrieved",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "transaction_id": "txn_2026024_00001",
      "plan_slug": "pro",
      "amount": 29.99,
      "currency": "USD",
      "status": "successful",
      "created_at": "2026-02-24T12:00:00Z"
    }
  ],
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Query user's payments from `payments` table
- Filter by status if provided
- Order by created_at DESC
- Apply pagination

---

### 6. Get Last/Current Plan
**Endpoint**: `GET /api/payments/last-plan`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Last plan retrieved",
  "data": {
    "id": 2,
    "name": "Pro",
    "slug": "pro",
    "price": 29.99,
    "interval": "monthly",
    "features": [...]
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Returns the subscription plan user is currently on or last paid for

---

### 7. Verify Payment
**Endpoint**: `GET /api/payments/{transactionId}`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**URL Parameters**:
- `transactionId`: Transaction ID (e.g., "txn_2026024_00001")

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Payment verified",
  "data": {
    "id": 1,
    "transaction_id": "txn_2026024_00001",
    "status": "successful",
    "amount": 29.99,
    "verified_at": "2026-02-24T12:00:00Z"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `404 Not Found`: Transaction doesn't exist
- `403 Forbidden`: User can't view this payment

---

### 8. Refund Payment
**Endpoint**: `POST /api/payments/refund/{transactionId}`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Throttled  

**URL Parameters**:
- `transactionId`: Transaction ID to refund

**Request Body**:
```json
{
  "reason": "Requested refund"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Refund processed",
  "data": {
    "original_transaction_id": "txn_2026024_00001",
    "refund_transaction_id": "refund_2026024_00001",
    "amount": 29.99,
    "status": "successful",
    "created_at": "2026-02-24T12:00:00Z"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Requires original payment to be in successful status
- Creates refund record in payments table
- Only original payment owner can refund

---

### 9. Revert Plan
**Endpoint**: `POST /api/payments/revert-plan`  
**Auth Required**: ✅ Yes (Bearer Token)  
**Rate Limit**: Standard  

**Request Body**: Empty or
```json
{
  "plan_slug": "basic"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Plan reverted",
  "data": {
    "current_plan": "basic",
    "user": {...}
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Downgrade or change user's current_plan

---

### 10. Create Subscription Plan
**Endpoint**: `POST /api/subscription-plans`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "name": "Enterprise",
  "slug": "enterprise",
  "description": "For large organizations requiring full control.",
  "monthly_price": null,
  "yearly_price": null,
  "currency": "USD",
  "trial_days": 0,
  "features": ["Unlimited everything", "Dedicated infrastructure", "SSO & SAML", "24/7 phone support"],
  "is_active": true,
  "popular": false
}
```

**Notes**:
- Public endpoint - anyone can create plans
- Slug must be unique
- Supports new pricing structure: `monthly_price` and `yearly_price` (both optional, can be null)
- Also accepts legacy format with `price` and `interval` for backward compatibility
- `popular` flag indicates if this is a featured/highlighted plan
- If only `price` provided (legacy), it will be auto-mapped to appropriate field based on `interval`

**Response** (201 Created):
```json
{
  "status": "success",
  "message": "Plan created",
  "data": {
    "id": 3,
    "name": "Enterprise",
    "slug": "enterprise",
    ...
  },
  "code": 201,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

---

### 11. Update Subscription Plan
**Endpoint**: `PUT /api/subscription-plans/{id}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**: Same as create (partial update possible)

**Example Partial Update**:
```json
{
  "monthly_price": 99.99,
  "yearly_price": 79.99,
  "popular": true
}
```

**Notes**:
- Any field can be updated individually
- Supports both new pricing fields (`monthly_price`, `yearly_price`) and legacy field (`price`)
- If legacy format used, automatically converts to new structure

**Response** (200 OK)

---

### 12. Delete Subscription Plan
**Endpoint**: `DELETE /api/subscription-plans/{id}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Plan deleted",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

---

### 13. Payment Webhook
**Endpoint**: `POST /api/payments/webhook`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Headers** (optional):
```
X-Webhook-Signature: signature_token
```

**Request Body**:
```json
{
  "event": "payment.completed",
  "transaction_id": "txn_external_123",
  "status": "successful",
  "amount": 50.00,
  "user_email": "john@example.com",
  "metadata": {}
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Webhook processed",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Called by external payment processors (Stripe, PayPal, etc.)
- Uses webhook signature verification if configured
- Processes payment updates asynchronously

---

## Admin & Settings Endpoints

### 1. Update Subscription Plans Schema (Database Migration)
**Endpoint**: `POST /settings/subscription-plans/update-schema`  
**Auth Required**: ✅ Yes (Admin Auth)  
**Rate Limit**: Standard  

**Notes**:
- Allows existing customers to upgrade their database schema
- Runs new migration for pricing structure (`monthly_price`, `yearly_price`, `popular` columns)
- Automatically populates new pricing columns from existing data
- Existing plans are preserved and converted to new format
- No request body required

**Response** (302 Redirect - with flash message):
```
Location: /settings/subscription-plans
Flash Success: "Database schema updated successfully!"
```

---

### 2. Bulk Import Subscription Plans
**Endpoint**: `POST /settings/subscription-plans/bulk-import`  
**Auth Required**: ✅ Yes (Admin Auth)  
**Rate Limit**: Standard  

**Request Body** (Form Data):
```
json_data: [
  {
    name: "Starter",
    monthlyPrice: 49,
    yearlyPrice: 39,
    description: "For small teams",
    features: ["Feature 1", "Feature 2"],
    popular: false
  }
]
```

**Supported Formats**:
- JavaScript object notation (unquoted keys) - `{ name: "value" }`
- Strict JSON (quoted keys) - `{ "name": "value" }`
- Legacy format - `{ price: 29.99, interval: "monthly" }`
- Trailing commas - `{ name: "value", }`

**Notes**:
- Required fields: `name`
- Optional fields: `slug` (auto-generated from name if not provided), `monthlyPrice`, `yearlyPrice`, `price`, `description`, `features`, `currency`, `interval`, `trial_days`, `is_active`, `popular`
- Unquoted property names are automatically converted to valid JSON
- Trailing commas are supported (JavaScript-style)
- Skips plans with duplicate slugs
- Reports import success, duplicates, and any errors

**Response** (302 Redirect):
```
Flash Success: "Successfully imported X plan(s)."
Location: /settings/subscription-plans
```

---

## AI Generation

### 1. Generate AI Response
**Endpoint**: `POST /api/ai/generate`  
**Auth Required**: ❌ No (but can be authenticated for tracking)  
**Rate Limit**: `throttle:ai` (configurable, default 60 per minute)  

**Request Body** (with prompt):
```json
{
  "prompt": "Write a short poem about artificial intelligence",
  "model": "gpt-4o-mini",
  "max_tokens": 256,
  "async": false
}
```

**Or** (with messages):
```json
{
  "messages": [
    {
      "role": "system",
      "content": "You are a helpful assistant"
    },
    {
      "role": "user",
      "content": "What is the capital of France?"
    }
  ],
  "model": "gpt-4o-mini",
  "max_tokens": 256,
  "async": false
}
```

**Notes**:
- Either `prompt` or `messages` required (not both)
- Default model from `GORQ_DEFAULT_MODEL` env var
- `async: true` returns job ID for polling status
- `async: false` (default) waits for response
- Uses Gorq AI service for generation

**Response (Synchronous)** (200 OK):
```json
{
    "status": "success",
    "message": "AI generation result",
    "data": {
        "id": "chatcmpl-1b5cc4d6-33f1-41ad-8995-e333b1a06f66",
        "object": "chat.completion",
        "created": 1774003192,
        "model": "llama-3.1-8b-instant",
        "choices": [
            {
                "index": 0,
                "message": {
                    "role": "assistant",
                    "content": "It seems like you're looking for general information about hell..."
                },
                "logprobs": null,
                "finish_reason": "stop"
            }
        ],
        "usage": {
            "queue_time": 0.0725,
            "prompt_tokens": 36,
            "completion_tokens": 185,
            "total_tokens": 221
        },
        "system_fingerprint": "fp_d317489708",
        "timestamp": "2026-03-20T10:39:52+00:00"
    },
    "code": 200,
    "timestamp": "2026-03-20T10:39:52+00:00"
}
```

**Response (Asynchronous)** (202 Accepted):
```json
{
  "status": "success",
  "message": "Generation queued",
  "data": {
    "job_id": "uuid-here",
    "status": "pending",
    "created_at": "2026-02-24T12:00:00Z"
  },
  "code": 202,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Missing prompt/messages
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Gorq API error

**Database Flow**:
- Create record in `ai_requests` table
- Store user_id (if authenticated), model, prompt, status
- If async=false: call Gorq API, get response, update status
- If async=true: dispatch ProcessAiRequest job, return job_id
- Store generated response in database

---

### 2. Get AI Job Status
**Endpoint**: `GET /api/ai/jobs/{id}/status`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `id`: Job/Request ID (returned from async generation)

**Response (Pending)** (200 OK):
```json
{
  "status": "success",
  "message": "Job status",
  "data": {
    "id": "uuid-here",
    "status": "pending",
    "progress": 0
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Response (Completed)** (200 OK):
```json
{
  "status": "success",
  "message": "Job status",
  "data": {
    "id": "uuid-here",
    "status": "completed",
    "response": "Full generated response here...",
    "tokens_used": 150,
    "completed_at": "2026-02-24T12:00:30Z"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Response (Failed)** (200 OK):
```json
{
  "status": "success",
  "message": "Job status",
  "data": {
    "id": "uuid-here",
    "status": "failed",
    "error": "API rate limit exceeded"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `404 Not Found`: Job doesn't exist or has expired

**Database Flow**:
- Query `ai_requests` table by ID
- Return status, progress, or response depending on status

---

## Email & Messaging

### 1. Contact Form Submission
**Endpoint**: `POST /api/mail/contact`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "message": "I have a question about your service",
  "turnstile_token": "optional_if_captcha_enabled",
  "recaptcha_token": "optional_if_captcha_enabled"
}
```

**Notes**:
- If CAPTCHA is enabled (either Turnstile or reCAPTCHA), provide at least one of `turnstile_token` or `recaptcha_token`
- `turnstile_token` must be generated with action "contact" when calling Turnstile's execute() method
- `recaptcha_token` is obtained from reCAPTCHA widget

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Contact message queued/sent",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Missing required fields
- `422 Validation Error`: CAPTCHA failed (if enabled)

**Database Flow**:
- Queue email to admin inbox (from config MAIL_TO_ADDRESS)
- Email sent via configured mail driver (SMTP, Mailgun, etc.)
- Log submission in application logs

---

### 2. Newsletter Signup
**Endpoint**: `POST /api/mail/newsletter`  
**Auth Required**: ❌ No  
**Rate Limit**: Throttled  

**Request Body**:
```json
{
  "email": "subscriber@example.com",
  "name": "Jane Smith",
  "turnstile_token": "optional_if_captcha_enabled",
  "recaptcha_token": "optional_if_captcha_enabled"
}
```

**Notes**:
- If CAPTCHA is enabled (either Turnstile or reCAPTCHA), provide at least one of `turnstile_token` or `recaptcha_token`
- `turnstile_token` must be generated with action "newsletter" when calling Turnstile's execute() method
- `recaptcha_token` is obtained from reCAPTCHA widget

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Newsletter subscription received. Please verify by clicking the link in the email sent to you.",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Creates or updates entry in `newsletter_subscribers` table
- Sends verification email with unique token
- Subscriber must verify before being active

**Error Responses**:
- `422 Validation Error`: Invalid email format

**Database Flow**:
- Check if email already in `newsletter_subscribers`
- Create new record with status=pending_verification
- Generate unique verification token
- Send verification email with link

---

### 3. Verify Newsletter Subscription
**Endpoint**: `GET /api/mail/newsletter/verify/{token}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `token`: Unique token from verification email

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Email verified and subscribed",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `404 Not Found`: Token invalid or expired
- `422 Validation Error`: Email already verified

**Database Flow**:
- Find token in newsletter_subscribers
- Update verified_at timestamp
- Mark as active/verified
- Delete token

---

### 4. Unsubscribe from Newsletter
**Endpoint**: `GET /api/mail/newsletter/unsubscribe/{token}`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**URL Parameters**:
- `token`: Unique unsubscribe token sent in newsletter emails

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Unsubscribed successfully",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Database Flow**:
- Find token in newsletter_subscribers
- Mark as unsubscribed
- Delete verification/unsubscribe tokens

---

### 5. Send Password Reset Email
**Endpoint**: `POST /api/mail/password-reset`  
**Auth Required**: ❌ No  
**Rate Limit**: Throttled  

**Request Body**:
```json
{
  "email": "john@example.com"
}
```

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Password reset link sent",
  "data": null,
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Same as `/api/auth/password/forgot`
- Sends reset link to user's email

---

## Maps

### 1. Create Map Pin (Get Embed URLs)
**Endpoint**: `POST /api/maps/pin`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body**:
```json
{
  "address": "123 Main Street, New York, NY 10001",
  "zoom": 15,
  "width": 600,
  "height": 450
}
```

**Notes**:
- Address is required
- Zoom: 1-21 (default 15)
- Width/Height: defaults 600x450
- Does not require stored data - generates URLs on the fly

**Response** (200 OK):
```json
{
  "status": "success",
  "message": "Map pin generated",
  "data": {
    "address": "123 Main Street, New York, NY 10001",
    "embed_url": "https://maps.google.com/maps?q=123+Main+Street%2C+New+York%2C+NY+10001&z=15&output=embed",
    "maps_link": "https://www.google.com/maps/search/?api=1&query=123+Main+Street%2C+New+York%2C+NY+10001",
    "iframe": "<iframe width=\"600\" height=\"450\" ... src=\"https://maps.google.com/maps...\"></iframe>",
    "zoom": 15,
    "width": 600,
    "height": 450
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Error Responses**:
- `422 Validation Error`: Invalid address or parameters

**Database Flow**:
- No database operation
- Directly generates and returns Google Maps URLs

---

## CAPTCHA

### 1. Verify CAPTCHA Token
**Endpoint**: `POST /api/captcha/verify`  
**Auth Required**: ❌ No  
**Rate Limit**: Standard  

**Request Body** (Turnstile):
```json
{
  "provider": "turnstile",
  "token": "captured_turnstile_token",
  "action": "contact_form"
}
```

**Or** (reCAPTCHA):
```json
{
  "provider": "recaptcha",
  "token": "captured_recaptcha_token"
}
```

**Response (Success)** (200 OK):
```json
{
  "status": "success",
  "message": "Captcha verified",
  "data": {
    "success": true,
    "score": 0.9,
    "challenge_ts": "2026-02-24T12:00:00Z",
    "hostname": "example.com"
  },
  "code": 200,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Response (Failure)** (422 Unprocessable Entity):
```json
{
  "status": "error",
  "message": "Captcha verification failed",
  "errors": {
    "error": ["Captcha token invalid or expired"]
  },
  "code": 422,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

**Notes**:
- Turnstile: Cloudflare CAPTCHA
- reCAPTCHA: Google reCAPTCHA v2/v3
- Provider auto-detects if not specified
- For Turnstile, the `action` parameter must match the action used when generating the token (e.g., "register", "contact", "newsletter", "password_reset")
- Score (reCAPTCHA only): 0.0-1.0 (higher = more human-like)
- Either Turnstile or reCAPTCHA can be used, but at least one CAPTCHA system must be enabled in settings

**Database Flow**:
- Call external API (Turnstile/reCAPTCHA verification endpoint)
- Validate response signature
- Return verification result

---

## Response Format

### Success Response
All successful API responses follow this format:

```json
{
  "status": "success",
  "message": "Human-readable message",
  "data": {},
  "code": 200,
  "timestamp": "2026-02-24T12:00:00.000Z"
}
```

**Fields**:
- `status`: Always "success" for successful responses
- `message`: Human-readable description of the response
- `data`: Response payload (object, array, or null)
- `code`: HTTP status code
- `timestamp`: ISO-8601 timestamp in UTC

### Error Response
```json
{
  "status": "error",
  "message": "Error description",
  "errors": {
    "field_name": ["Error message for field"]
  },
  "code": 422,
  "timestamp": "2026-02-24T12:00:00.000Z"
}
```

**Fields**:
- `status`: Always "error" for error responses
- `message`: General error message
- `errors`: Object containing field-specific error messages (for validation errors)
- `code`: HTTP status code
- `timestamp`: ISO-8601 timestamp

### HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Successful request |
| 201 | Created | Resource created successfully |
| 202 | Accepted | Async request accepted (queued) |
| 400 | Bad Request | Malformed request |
| 401 | Unauthorized | Missing/invalid authentication |
| 403 | Forbidden | Authenticated but not allowed |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation error |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

---

## Authentication

### Bearer Token
Include in `Authorization` header:
```
Authorization: Bearer {token}
```

### Token Format
Tokens are Sanctum personal access tokens:
```
{database_id}|{random_64_char_string}
```

Example: `1|abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnop`

### Token Expiration
- Tokens don't expire by default (can be configured)
- Can be revoked/deleted at any time
- One token per login (previous token invalidated)
- Safe to store in localStorage but secure HTTPOnly cookies recommended

---

## Rate Limiting

### Default Limits
- **Standard**: 60 requests per minute per IP
- **Throttled**: 10 requests per minute per IP (password resets, etc.)
- **AI**: 60 requests per minute (configurable via AI_RATE_LIMIT_PER_MINUTE)

### Rate Limit Headers
```
RateLimit-Limit: 60
RateLimit-Remaining: 45
RateLimit-Reset: 1645092000
```

### Exceeding Limits
Returns `429 Too Many Requests`:
```json
{
  "status": "error",
  "message": "Too Many Requests",
  "code": 429,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

---

## Common Query Parameters

### Pagination
- `limit`: Number of results (default 20, max 100)
- `offset`: Number of results to skip (default 0)
- `skip`: Alternative to offset

### Sorting
- `sort`: Field to sort by (prepend `-` for descending)
- Example: `?sort=-created_at` (newest first)

### Filtering
- `status`: Filter by status
- `email`: Filter by email
- Varies by endpoint

---

## Error Handling

### Validation Errors
```json
{
  "status": "error",
  "message": "The given data was invalid",
  "errors": {
    "email": ["The email must be a valid email address"],
    "password_hash": ["The password hash must be 64 characters"]
  },
  "code": 422,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

### Missing Required Fields
```json
{
  "status": "error",
  "message": "Missing required field",
  "errors": {
    "prompt": ["The prompt field is required"]
  },
  "code": 422,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

### Unauthorized
```json
{
  "status": "error",
  "message": "Unauthenticated",
  "errors": null,
  "code": 401,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

### Not Found
```json
{
  "status": "error",
  "message": "Resource not found",
  "errors": null,
  "code": 404,
  "timestamp": "2026-02-24T12:00:00Z"
}
```

---

## Data Types & Formats

### Password Hashing
Frontend must hash passwords to SHA-256before sending:
```javascript
// JavaScript example
const password = "userPassword123";
const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(password));
const hashHex = Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
```

### Email Addresses
- Must be valid email format
- Case-insensitive for comparison (stored as lowercase)
- Must be unique per user

### Usernames
- Alphanumeric, underscores, hyphens allowed
- Case-sensitive
- 3-255 characters
- Must be unique

### Avatar URLs
- Can be external URL
- Can upload file for local storage
- Returned as absolute HTTP/HTTPS URL

### Dates/Timestamps
- All dates in ISO-8601 format: `YYYY-MM-DDTHH:MM:SS.sssZ`
- Always UTC timezone
- Example: `2026-02-24T12:00:00.000Z`

### Card Data
- Card numbers: 13-19 digits
- Expiry month: 2 digits (01-12)
- Expiry year: 2 digits (25, 26, etc.)
- CVV: 3-4 digits

### URLs
- Must use HTTP/HTTPS protocol
- Must be properly formatted
- Example: `https://example.com/image.jpg`

---

## Security Best Practices

### For Frontend Developers

1. **Hash Passwords**: Always hash to SHA-256 before sending
2. **Use HTTPS**: Never send credentials over HTTP
3. **Store Tokens Securely**: Use HTTPOnly cookies or secure localStorage
4. **CORS**: Configure CORS policy in backend (api.example.com allowed origins)
5. **Validation**: Validate input on frontend before sending
6. **Never Log Credentials**: Don't log passwords or tokens
7. **Use CAPTCHA**: Enable Turnstile/reCAPTCHA for public forms
8. **Rate Limiting**: Handle 429 responses gracefully

### API Endpoint Security

- Public endpoints (register, login, contact, newsletter, maps, captcha, subscription plans)
- Protected endpoints (user profile, payments, AI) - require Bearer token
- All inputs validated and sanitized
- SQL injection prevented via ORM
- XSS prevention via JSON responses
- CSRF protection via token validation

---

## Example Frontend Implementations

### Login Flow
```
1. User enters email and password
2. Frontend hashes password to SHA-256
3. POST /api/auth/login with email + hash
4. Receive token in response
5. Store token securely (HTTPOnly cookie or localStorage)
6. Use token for all subsequent authenticated requests
7. Include in Authorization: Bearer header
```

### Payment Flow
```
1. GET /api/subscription-plans (display options)
2. User selects plan and enters card details
3. POST /api/subscriptions with plan_slug + card_data
4. Backend processes payment via gateway
5. Update user's current_plan
6. Display success/error to user
```

### AI Generation Flow (Async)
```
1. User submits prompt
2. POST /api/ai/generate with prompt + async: true
3. Receive job_id in 202 response
4. Poll GET /api/ai/jobs/{job_id}/status periodically
5. When status=completed, display response to user
6. Show loading state while pending
```

---

**API Version**: 1.0  
**Last Updated**: February 24, 2026  
**Status**: Production Ready
