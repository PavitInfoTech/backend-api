# Quick Start Guide

## For First-Time Installation

### 1. Prepare the Server
```bash
cd /path/to/backend-api
composer install
```

### 2. Start Setup Wizard
Open in browser: `http://your-domain/setup`

You'll see a form asking for:
- Application name
- Application URL
- Database credentials
- Admin username & password

Fill it all in and submit. The wizard will:
- Create `.env` file
- Generate APP_KEY
- Run database migrations
- Create admin credentials table
- Create your first admin account

### 3. Login to Admin Panel
Navigate to: `http://your-domain/admin/login`

Use the admin credentials you just created.

### 4. Configure Services
In the Admin Panel, configure:
1. **Mail** → Set up your email service (SMTP, Mailgun, etc.)
2. **Auth** → Add OAuth secrets (Google, GitHub, reCAPTCHA)
3. **API Keys** → Store third-party API credentials (Stripe, SendGrid, etc.)
4. **Admin Credentials** → Create additional admin accounts if needed

## API Usage

All API routes start with `/api/`:

```bash
# Register
curl -X POST http://your-domain/api/auth/register

# Login
curl -X POST http://your-domain/api/auth/login

# Check health
curl http://your-domain/api/ping

# Protected endpoint
curl -H "Authorization: Bearer TOKEN" http://your-domain/api/user
```

## Admin Panel Routes

| Route | Purpose |
|-------|---------|
| `/setup` | Initial setup wizard |
| `/admin/login` | Admin login |
| `/settings` | Settings dashboard |
| `/settings/mail` | Email configuration |
| `/settings/auth` | OAuth & security |
| `/settings/api` | API keys management |
| `/settings/admin-credentials` | Admin accounts |

## Key Features

### 🔒 Security
- Admin passwords are hashed
- API credentials are encrypted
- Session-based admin authentication
- CSRF protection on all forms

### ⚙️ Configuration
- Database settings via setup wizard
- Mail service configuration
- OAuth provider setup
- API key management
- Admin role management

### 📊 Management
- View all settings in one place
- Add/edit/remove configurations
- Multiple admin accounts support
- Encrypted sensitive data
- Audit trail via timestamps

## Common Tasks

### Add a New API Key
1. Go to `/settings/api`
2. Click "Add New Key"
3. Enter key name (e.g., "Stripe")
4. Paste the API key value
5. Add optional description
6. Click "Save Key"

### Create Another Admin Account
1. Go to `/settings/admin-credentials`
2. Click "Add Admin"
3. Enter username & password
4. Select role (Super Admin, Admin, or Manager)
5. Click "Create"

### Update Mail Settings
1. Go to `/settings/mail`
2. Change mail driver (SMTP, Mailgun, etc.)
3. Enter SMTP details if needed
4. Enter from address & name
5. Click "Save Changes"

### Add OAuth
1. Go to `/settings/auth`
2. Enter Google Client ID & Secret
3. Or enter GitHub Client ID & Secret
4. Click "Save Changes"

## Environment File

After setup, `.env` contains:

```
APP_NAME=Backend API
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=http://your-domain

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=backend_api
DB_USERNAME=root
DB_PASSWORD=your_password

MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.com

API_DOMAIN=api.example.com # optional deployment host; /api remains required
```

## Troubleshooting

**Can't access setup?**
- Check if .env already exists
- Delete .env and refresh

**Setup fails?**
- Verify database credentials
- Check database exists
- Ensure proper file permissions

**Forgot admin password?**
- Manually reset via console:
  ```bash
  php artisan tinker
  >>> $admin = App\Models\AdminCredential::first();
  >>> $admin->update(['password' => bcrypt('newpassword')]);
  >>> exit
  ```

**API routes not working?**
- Test: `curl http://localhost/api/ping`
- Check `/api/` prefix is in URL
- Verify API routes registered in bootstrap/app.php

## Documentation

See detailed documentation:
- **Complete Setup Guide**: `SETUP.md`
- **Implementation Details**: `IMPLEMENTATION.md`
- **Verification Checklist**: `VERIFICATION.md`

## Next Steps

1. ✅ Run migrations
2. ✅ Complete setup wizard
3. ✅ Login to admin panel
4. ✅ Configure mail & OAuth
5. ✅ Add API keys
6. ✅ Create additional admins
7. ✅ Start using the API!

---

**Need Help?** Check the documentation files or review the SETUP.md guide for detailed troubleshooting.
