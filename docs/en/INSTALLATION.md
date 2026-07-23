# Installation

## Requirements

- PHP 8.3 or newer.
- MySQL or MariaDB with a dedicated empty database.
- PHP extensions: PDO, `pdo_mysql`, mbstring, fileinfo, DOM, OpenSSL, tokenizer, ctype, JSON, and session.
- HTTPS for a production website.
- Writable `storage`, `bootstrap/cache`, and public `uploads` directories.

KaevCMS public entry points display a readable bilingual error on old PHP versions before Laravel or the Web Installer is loaded.

## VDS or configurable Document Root

For a complete Ubuntu 24.04 LTS, nginx, PHP 8.3-FPM, MySQL, HTTPS, permissions, scheduler, and queue-worker walkthrough, see [Ubuntu VDS installation](VDS_UBUNTU.md).


1. Extract the full release outside the web root.
2. Point the domain Document Root to the release `public/` directory.
3. Install production dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

4. Configure HTTPS, open the secure domain, and follow `/install/`. Diagnostics may be viewed over HTTP, but MySQL and owner password submission is blocked until HTTPS is enabled.
5. Use a new empty database. The installer refuses to reuse an existing KaevCMS owner account.
6. Review the final security report before opening the website to users.

## Windows development installation

```powershell
.\deployment\windows\setup.ps1
.\deployment\windows\quality.ps1
.\deployment\windows\browser-quality.ps1
```

`setup.ps1` creates the local `.env`, application key, runtime directories, and development dependencies. It is not a shared-hosting deployment script.

## After installation

- Sign in as the owner.
- Configure LoginServer and GameServer connections.
- Configure SMTP and test mail delivery.
- Review scheduler and queue diagnostics.
- Keep `APP_DEBUG=false` in production.
- Remove old release archives from public storage after extraction.
