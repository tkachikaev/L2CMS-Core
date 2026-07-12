# Administrator authentication

L2Forge CMS uses a separate `admins` table and a separate Laravel session guard named `admin`. Gaming accounts and future website user accounts are not administrators.

## First administrator

After migrations have completed, create the first administrator interactively:

```powershell
php artisan l2forge:admin-create
```

The command does not accept a password argument, so the password is not written to PowerShell history or the process command line.

Password requirements:

- at least 12 characters;
- uppercase and lowercase letters;
- at least one number.

The password is stored using the configured Laravel hasher. L2Forge CMS defaults to Argon2id.

## Routes

- `GET /admin/login` — login form;
- `POST /admin/login` — authentication;
- `GET /admin` — protected dashboard;
- `POST /admin/logout` — logout.

## Login protection

The login limiter combines normalized email and client IP. Defaults:

```env
ADMIN_LOGIN_MAX_ATTEMPTS=5
ADMIN_LOGIN_DECAY_SECONDS=60
```

Every login result is written to `admin_login_logs`. Passwords are never logged.

## Disable an administrator

Until the administrator management screen is implemented, use Tinker:

```powershell
php artisan tinker
```

```php
App\Models\Admin::where('email', 'admin@example.com')->update(['is_active' => false]);
```
