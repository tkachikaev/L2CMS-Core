# Modules

KaevCMS 0.24.0 introduces the first stable module runtime.

## Installation

1. Copy the complete module directory into `modules/`.
2. Open **Control panel → Modules**.
3. Review manifest validity and CMS compatibility.
4. Enable the module as an owner.

Modules are not uploaded through the browser in this release. This avoids extracting untrusted archives from the web process and keeps file ownership predictable on shared hosting and Linux servers.

## Manifest

Every module requires `module.json`. The current manifest schema is `1`; its machine-readable reference is `resources/schemas/module.schema.json`.

Required fields:

- `schema` — currently `1`;
- `id` — lowercase directory identifier, equal to the directory name;
- `name`;
- `version` — semantic version;
- `author`.

Compatibility fields:

- `cms_min`;
- `cms_max`.

Optional runtime fields:

- `namespace` and `autoload` for an isolated PSR-4 namespace;
- `bootstrap` for a PHP file returning a callable;
- `views`;
- `lang`;
- `routes.web`;
- `routes.admin`.

## Runtime contracts

The bootstrap file must return a callable:

```php
<?php

use App\Support\Modules\ModuleContext;
use Illuminate\Contracts\Foundation\Application;

return static function (Application $app, ModuleContext $module): void {
    // Register module services here.
};
```

View and translation namespaces are `module-{id}`. Public module routes are grouped under `/modules/{id}`. Administrator routes are grouped under the dynamic administrator address at `/extensions/{id}` and always receive the CMS administrator authentication, security-header and access middleware.
Every module route also receives a state guard. Disabled, missing, damaged or update-pending modules cannot execute. Module routes intentionally remain outside the core Laravel route cache and are registered after cached core routes, so changing module state never leaves an executable stale route and never requires clearing the site route cache.

If bootstrap or runtime loading fails, KaevCMS records only the safe loading stage and exception class, leaves the rest of the CMS available, and retries the module after a short cooldown. The default cooldown is 60 seconds, which prevents a broken module from flooding the log on every request.

## Permissions

- Owner: view, enable and disable modules.
- Administrator: view module state in read-only mode.
- Editor: no module access.

Every state change is written to the audit log. Module state applies on the next request without rebuilding or clearing the core route cache.

## Security model

Modules are trusted PHP code and are not sandboxed. Manifest validation protects the CMS from malformed identifiers, unknown fields, path traversal, unsafe symbolic links and incompatible versions, but it cannot make malicious PHP safe.

Do not enable a module unless you trust its source and have reviewed its release archive.

## Not included yet

The first module foundation does not provide:

- browser ZIP installation;
- automatic module updates;
- module-specific database migrations;
- destructive data removal;
- a module marketplace;
- fine-grained permissions declared by each module.

These capabilities must be implemented separately with rollback and recovery tests.
