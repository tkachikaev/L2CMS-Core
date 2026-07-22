# Web Installer

KaevCMS 0.31.10 includes the hardened browser installer for ordinary PHP/MySQL hosting.

## Start

- Upload the project with `vendor/` already present.
- Set the website Document Root to `public/`.
- Enable HTTPS before entering real database or owner passwords.
- Open the site. When `.env` is missing, `public/index.php` redirects to `/install/`.

## Steps

1. Welcome and language selection.
2. PHP 8.3+, required extensions, required files and writable directories.
3. Website name/URL and MySQL verification. The probe checks connection plus CREATE, INSERT, ALTER, UPDATE, DELETE and DROP permissions using a random temporary table.
4. Owner name, email and password.
5. Atomic `.env` creation, stable `APP_KEY`, migrations, seeding, owner creation and release marking.
6. Completion with links to the website and `/admin`.

The installer does not execute Composer, npm or shell commands. The database password is stored only in the server-side installer session and `.env`; it is never rendered back into HTML. POST requests use a redirect-after-submit flow so browser refresh does not repeat installation actions.

## Security

- Installer session cookie uses `HttpOnly`, `SameSite=Lax`, strict cookie-only sessions and `Secure` on HTTPS.
- Responses use no-cache, anti-frame, MIME-sniffing, referrer, permissions and Content Security Policy headers.
- A non-blocking filesystem lock prevents two browser windows from installing at the same time.
- Unexpected PDO, migration and filesystem details are written to `storage/logs/installer.log` with a short reference code; the browser receives a generic message and database passwords are redacted.
- `.env` values are always quoted and escaped. An interrupted retry preserves the existing `APP_KEY`.

## Reinstallation and recovery

A successful installation creates `storage/app/installed.lock`. While installation is incomplete, `storage/app/installing.lock` allows a safe retry after `.env`, migrations or the owner account have already been created. Existing owner data is reused only during this explicit incomplete state, preventing duplicate administrators and allowing the final lock to be recreated.

A pre-existing `.env` without the temporary state file blocks the installer. Windows setup and update scripts refresh the same installed lock file.

Standalone installer regressions run through:

```text
php deployment/hosting/web-installer/tests/installer-regression.php
```
