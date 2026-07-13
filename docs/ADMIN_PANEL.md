# Administrative panel

The administrative interface is part of L2Forge Core and is never rendered through a public theme.

## Single entry point

- `/admin` — the main administration page and the single panel entry point.
- `/admin/news` — news management inside the same administration shell.
- `/admin/themes` — theme management inside the same administration shell.
- `/admin/settings` — site, game-server, registration and SMTP settings.
- `/admin/logs` — human-readable audit log with categories and event details.
- `/admin/login` — administrator authentication.
- `/admin/dashboard` — compatibility redirect to `/admin`.

All panel pages use:

- `resources/views/admin/layouts/panel.blade.php`
- `resources/views/admin/partials/navigation.blade.php`
- `public/assets/admin/css/app.css`

Public theme files cannot replace these resources.

## Navigation

The left menu always shows both implemented and planned sections. Planned entries are disabled and have no writable route until their functionality is implemented.

Current sections:

- Main page — implemented.
- News — implemented.
- Themes — implemented.
- Settings — general, game-server, registration and SMTP settings implemented; login-server tab prepared.
- Modules — planned.
- Administrators — planned.
- Activity log — implemented.

## Theme activation

The active theme is stored in the CMS database under the key `theme.active`. The `.env` value `CMS_THEME` remains the safe fallback used when the settings table is unavailable or the selected theme becomes invalid.

Before activation, the core checks:

- safe directory slug;
- readable `theme.json`;
- required manifest fields;
- matching manifest and directory slugs;
- required Blade files;
- minimum and maximum CMS versions when declared.

Theme ZIP upload is deliberately not implemented yet. Archive extraction requires a separate security layer against path traversal, executable files, oversized archives, and unsafe symbolic links.
