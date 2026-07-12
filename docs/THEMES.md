# L2Forge Themes

Public themes are stored separately from L2Forge Core.

```text
themes/<slug>/
├─ theme.json
└─ views/
   ├─ layouts/app.blade.php
   └─ home.blade.php

public/themes/<slug>/
└─ assets/
```

The administrator interface does not use public themes.

## Manifest

```json
{
  "name": "Theme name",
  "slug": "theme-slug",
  "version": "1.0.0",
  "author": "Author",
  "cms_min": "0.6.0",
  "cms_max": "1.5.0",
  "description": "Theme description",
  "preview": "assets/images/preview.webp"
}
```

Required fields:

- `name`
- `slug`
- `version`
- `author`

Optional fields:

- `cms_min`
- `cms_max`
- `description`
- `preview`

The `slug` must match the directory name and may contain lowercase Latin letters, digits, hyphens, and underscores.

## Activation

Themes are activated in `/admin/themes`. The selected slug is written to the `cms_settings` table. `CMS_THEME` in `.env` is only a fallback.

L2Forge CMS refuses to activate a theme that is invalid or incompatible.


## News templates

A theme may define:

```text
themes/<slug>/views/news/index.blade.php
themes/<slug>/views/news/show.blade.php
```

Available news data includes:

- `$news->title` — title;
- `$news->excerpt` — short description;
- `$news->published_at` — publication date;
- `$news->coverUrl()` — public cover URL or `null`;
- `$news->safeBodyHtml()` — server-sanitized rich HTML.

Render the full body only as trusted output from `safeBodyHtml()`:

```blade
<article class="news-content">{!! $news->safeBodyHtml() !!}</article>
```

Do not render `$news->body` directly. Themes are responsible only for visual styling of the allowed HTML elements and `data-color` / `data-align` attributes.
