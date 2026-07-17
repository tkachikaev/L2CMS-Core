# Administrative panel

The administrative interface is part of L2Forge Core and is never rendered through a public theme.

## Single entry point

- `/admin` — the main administration page and the single panel entry point.
- `/admin/news` — news management inside the same administration shell.
- `/admin/themes` — theme management inside the same administration shell.
- `/admin/settings` — main public website settings; related sections remain on compatible `/admin/settings/*` routes.
- `/admin/users` — CMS user management and account details.
- `/admin/administrators` — administrator account management.
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

Current navigation groups:

- **Content** — news and pages.
- **Site** — main public website settings, languages and themes.
- **Servers** — GameServer worlds, LoginServer connections and game-account policy.
- **Users** — CMS users and registration settings.
- **System** — mail, security, system information, administrators, activity log and the planned modules entry.

The former global settings tab bar was removed. Existing `/admin/settings/*` URLs and route names are preserved for compatibility, while each page is now reached directly from the sidebar. Mail templates retain their own local tab bar because they belong to one mail module.

Navigation groups are collapsed by default. The group containing the current page opens automatically, while manually opened or closed inactive groups are remembered in browser storage. On narrow screens the links remain available in the horizontal navigation without extra expansion.

Internal panel links use Livewire `wire:navigate`, so the document body is replaced without a full browser reload. Livewire assets are loaded by the shared admin layout, and the built-in progress bar uses the panel accent color. Page-specific JavaScript must initialize immediately when its body script is evaluated and must not rely on `DOMContentLoaded`. Long-running requests, timers and temporary browser resources must be cancelled on `livewire:navigating`.

The panel language switcher is independent of the public theme. Russian and English are built in; the selected locale is stored in the administrator account.

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
