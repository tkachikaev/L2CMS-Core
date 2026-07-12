# News management

L2Forge CMS 0.5.0 includes the first content-management section.

## Administrator routes

- `GET /admin/news` — list all news items;
- `GET /admin/news/create` — create form;
- `POST /admin/news` — save a new item;
- `GET /admin/news/{news}/edit` — edit form;
- `PUT /admin/news/{news}` — save changes.

All routes require an authenticated administrator and use Laravel CSRF protection.

## Publication states

- **Draft** — `is_published` is disabled; the item is invisible publicly.
- **Scheduled** — publication is enabled and `published_at` is in the future.
- **Published** — publication is enabled and `published_at` is current or past.

The public site only returns published items.

## Text format

The editor stores ordinary text. Public templates escape the content and preserve line breaks. Arbitrary HTML and JavaScript entered into the editor are not executed.

## Slugs

A unique URL slug is generated when the item is created. It remains unchanged when the title is edited, preventing existing links from breaking.
