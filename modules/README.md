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
  "cms_min": "0.24.1",
  "cms_max": null
}
```

Optional runtime and database entry points:

```json
{
  "namespace": "Vendor\\ExampleModule\\",
  "autoload": "src",
  "bootstrap": "bootstrap.php",
  "views": "resources/views",
  "lang": "lang",
  "migrations": "database/migrations",
  "routes": {
    "web": "routes/web.php",
    "admin": "routes/admin.php"
  }
}
```

Migration files must use names such as `2026_07_21_000001_create_example_table.php`, return a Laravel migration instance, and provide both `up()` and `down()` methods. Applied files are immutable: publish a new migration instead of editing or deleting an old one.

Disabling a module stops its runtime code but preserves all database tables and data.

An enabled module is trusted PHP code. KaevCMS validates the manifest and prevents path traversal, but it cannot sandbox PHP. Install modules only from trusted sources.

See `docs/MODULES.md` for the complete lifecycle and security contract.
