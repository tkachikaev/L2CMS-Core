# Changelog

## 0.5.0 - 2026-07-12

### Added

- News management section at `/admin/news`.
- Empty state with a direct action for creating the first news item.
- News creation and editing forms.
- Draft, scheduled and published states.
- Automatic unique slug generation with stable URLs after title edits.
- Publication counters and links to live news pages.
- Feature tests for administrator news management and public visibility.
- Clean installations now start without demo news, so the administrator sees the first-news empty state.

### Security

- News body is rendered as escaped plain text with preserved line breaks.
- Draft and scheduled news cannot be opened through public routes.
- All administrator write operations remain protected by authentication and CSRF.

## 0.4.0 - 2026-07-12

### Changed

- Project renamed from L2CMS Core to **L2Forge CMS**.
- Default application name changed to `L2Forge CMS`.
- Composer package renamed to `l2forge/cms`.
- Administrator creation command renamed to `php artisan l2forge:admin-create`.
- Installer, diagnostics, documentation, default theme metadata and control panel branding updated.
- Existing `.env` files using the old default `APP_NAME` are migrated automatically by `setup.ps1` and `update.ps1`.

## 0.3.2 - 2026-07-12

### Fixed

- Moved administrator static assets from `public/admin` to `public/assets/admin`.
- Fixed the physical directory collision that caused PHP's development server to return its own 404 page for `/admin`.
- Added an environment diagnostic check preventing the reserved `public/admin` path from returning.

## 0.3.1 - 2026-07-12

### Changed

- `/admin` is now the single entry point for the administration panel.
- `/admin/dashboard` redirects to `/admin` for compatibility.
- The administration interface now uses a simpler, neutral and minimal built-in design.
- All implemented and planned sections remain visible in the left navigation.
- The administration home page now presents available and planned sections without optional database statistics.
- The theme management page was simplified to a compact list.

### Fixed

- Removed unnecessary dashboard queries so the main `/admin` page is less likely to fail when optional data is unavailable.
- Added feature tests for the main administration route and compatibility redirect.

## 0.3.0 - 2026-07-12

### Added

- Unified administrator layout independent from public themes.
- Persistent administrator navigation with responsive behavior.
- Theme management page at `/admin/themes`.
- Database-backed CMS settings storage for the active theme.
- Theme manifest, required-file, slug, preview-path and CMS-version validation.
- Safe activation of preinstalled themes with CSRF and administrator authentication.
- Theme activation logging through the application log.
- Automated feature tests for theme management.

### Security

- Public themes cannot replace administrator templates or administrator assets.
- Theme activation accepts only validated slugs inside the configured themes directory.
- Invalid, damaged, missing, or incompatible themes cannot be activated.
- Theme ZIP upload remains disabled until secure archive extraction is implemented.

## 0.2.0 - 2026-07-12

### Added

- Separate administrator model, database table, session guard and protected `/admin` area.
- Administrator login and logout with session regeneration and invalidation.
- `php artisan l2cms:admin-create` command for secure creation of the first administrator.
- Rate limiting by normalized email and IP address.
- Security log for successful, failed, inactive and throttled login attempts.
- Argon2id as the default CMS password hashing driver.
- Security headers and `noindex` metadata for the administration area.
- Initial administration dashboard and responsive administration design.
- Automated feature tests for administrator authentication.

### Security

- No default administrator account or password is included in seed data.
- Authentication errors do not reveal whether an administrator email exists.
- Admin pages are non-cacheable and protected from framing.

## 0.1.1 - 2026-07-12

- Fixed clean Windows installation, PowerShell encoding, required directories and Laravel 13 dependencies.
