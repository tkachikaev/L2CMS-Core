# KaevCMS modules

Each module is stored in its own directory and must contain a valid `module.json` manifest.

Minimal manifest:

```json
{
  "schema": 1,
  "id": "example-module",
  "name": "Example Module",
  "version": "1.0.0",
  "author": "Module author",
  "description": "Example KaevCMS extension.",
  "cms_min": "0.24.0",
  "cms_max": null
}
```

Optional runtime entry points:

```json
{
  "namespace": "Vendor\\ExampleModule\\",
  "autoload": "src",
  "bootstrap": "bootstrap.php",
  "views": "resources/views",
  "lang": "lang",
  "routes": {
    "web": "routes/web.php",
    "admin": "routes/admin.php"
  }
}
```

An enabled module is trusted PHP code. KaevCMS validates the manifest and prevents path traversal, but it cannot sandbox PHP. Install modules only from trusted sources.
