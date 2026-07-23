# Web Update packages / Пакеты Web Update

## English

A Web Update ZIP contains `kaevcms-update.json` at the archive root and payload files under `payload/core/` and `payload/public/`.

- `core/` targets the private application root.
- `public/` targets the active public path in standard and split layouts.
- `.env`, `storage`, SQLite runtime files, user uploads, and split-path configuration are protected. Only the release-owned `public/uploads/.gitignore` and `.htaccess` control files may be updated.
- Every payload file has a SHA256 hash.
- Packages with changed Composer dependencies are rejected and require a full deployment.

Example builder command:

```powershell
php deployment/updates/build-package.php `
    --root="C:\Releases\KaevCMS-0.32.13" `
    --output="C:\Releases\KaevCMS-update-to-0.32.13.zip" `
    --minimum=0.32.0 `
    --maximum=0.32.12 `
    --target=0.32.13 `
    --delete-file=deployment/updates/deletions.json `
    --previous-root="C:\Releases\KaevCMS-0.32.12" `
    --update-history
```

## Русский

Web Update ZIP содержит `kaevcms-update.json` в корне и файлы в `payload/core/` и `payload/public/`.

- `core/` применяется к закрытому корню приложения.
- `public/` применяется к активному публичному каталогу в стандартной и split-схеме.
- `.env`, `storage`, runtime SQLite, пользовательские uploads и конфигурация split-пути защищены. Обновляться могут только служебные `public/uploads/.gitignore` и `.htaccess`, принадлежащие релизу.
- Для каждого файла проверяется SHA256.
- Изменение Composer-зависимостей требует полного развёртывания и блокируется Web Updater.

`deletions.json` хранит историю удалений по версиям. `--previous-root` добавляет пути, которые существовали в предыдущем релизе и отсутствуют в новом. При прерванном обновлении владелец использует сохранённое состояние и резервные копии для восстановления.
