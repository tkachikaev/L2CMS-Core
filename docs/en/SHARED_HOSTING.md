# Shared-hosting deployment

Shared-hosting providers use different names for the domain public directory. KaevCMS does not guess it: pass the exact directory name as a build key.

## Build a production package

Default public directory `public_html`:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1
```

Custom public directory:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName example.hosting.test
```

Custom private core directory and output directory:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName public_html `
    -CoreDirectoryName private-kaevcms `
    -OutputDirectory D:\Releases
```

The wrapper removes the copied `vendor` directory and rebuilds a clean production-only dependency tree with `composer install --no-dev --optimize-autoloader`. It then creates a portable ZIP with forward-slash entries. `-IncludeDevelopmentDependencies` is available only for temporary diagnostics and should not be used for a public deployment.

Runtime data from `public/uploads`, `public/storage`, and `public/hot` is excluded. The archive receives a clean `uploads` directory with a defensive `.htaccess` that blocks PHP-like executable files. A package may therefore be built from an already installed test copy without carrying user images into the release.

## Provider examples

### Beget and common cPanel hosting

The site directory usually contains `public_html`. Use the default command and extract the ZIP into the parent site directory:

```text
site-root/
├── kaevcms-core/
└── public_html/
```

Do not extract the whole package inside `public_html`.

### Jino

A technical domain may itself be the public directory name. Build with that exact name:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName a860dbbcc70b.hosting.myjino.ru
```

Extract into the directory where the hosting panel expects that domain directory and the private core to be siblings.

### Unknown provider

Open the domain settings and find `Document Root`, `Website root`, `Public directory`, or `Working directory`. Use only the final directory name as `-PublicDirectoryName`. After extraction, `index.php` must be directly inside that public directory.

## Expected package layout

```text
parent-directory/
├── kaevcms-core/
│   ├── app/
│   ├── bootstrap/
│   ├── storage/
│   └── vendor/
└── public_html-or-domain-directory/
    ├── index.php
    ├── .htaccess
    ├── kaevcms-path.php
    ├── install/
    └── uploads/
```

The domain must never point to `kaevcms-core`.


## HTTPS during installation

The environment page may be opened over HTTP, but Web Installer will not submit the MySQL password or owner password over an unencrypted connection. Configure HTTPS in the hosting panel before testing the database or completing installation, then continue at `https://.../install/`.

Only loopback development (`127.0.0.1` or `::1`) is exempt. `X-Forwarded-Proto` is accepted only from a local or private reverse proxy.

## Updating

Use the cumulative Web Update from the administrator panel on shared hosting. One current package upgrades any source version listed as supported in its manifest; intermediate archives are not required.
