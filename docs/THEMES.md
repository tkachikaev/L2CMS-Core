# Themes

Public themes are stored separately from the CMS core.

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
  "cms_min": "0.3.0",
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

The CMS refuses to activate a theme that is invalid or incompatible.
