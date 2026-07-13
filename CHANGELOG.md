# Changelog

## 0.9.6 - 2026-07-13

### Исправлено

- `doctor.ps1` определяет корень проекта по собственному расположению и использует абсолютные пути для всех проверок каталогов.
- Проверка загрузочных каталогов больше не зависит от текущей папки PowerShell, например `C:\Users\1`.
- В сообщении о реально отсутствующем каталоге дополнительно выводится его абсолютный путь.

### Совместимость

- Миграции и зависимости Composer не изменялись.
- Исправление затрагивает только диагностический скрипт и документацию релиза.

## 0.9.5 - 2026-07-13

### Исправлено

- Исправлена ошибка компиляции Blade-шаблона на странице редактирования почтовых шаблонов.
- Переменные вида `{{site_name}}` теперь формируются без вложенных Blade-выражений, из-за которых страница возвращала HTTP 500.
- Вкладки подтверждения email, восстановления пароля и уведомления о смене пароля снова открываются корректно.

### Совместимость

- Миграции и зависимости Composer не изменялись.
- Пользовательские SMTP-настройки и тексты шаблонов не затрагиваются.

## 0.9.4 - 2026-07-13

### Добавлено

- Внутренние вкладки почтовых настроек: подключение, подтверждение email, восстановление пароля и уведомление о смене пароля.
- Готовые стандартные шаблоны писем, работающие сразу после установки.
- Безопасное редактирование темы, заголовка, основного текста, текста кнопки и дополнительного текста без написания HTML.
- Разрешённые переменные шаблонов с проверкой неизвестных значений.
- Предпросмотр письма и вставка переменных по нажатию.
- Тестовая отправка каждого сохранённого шаблона с демонстрационными данными.
- Восстановление стандартного шаблона без потери SMTP-настроек.
- Отдельное уведомление после успешной смены пароля.
- События журнала для изменения, сброса и тестирования шаблонов.

### Безопасность

- HTML-теги в редактируемых полях шаблонов блокируются.
- Ссылки кнопок подтверждения и восстановления создаются CMS и не редактируются администратором.
- Тестовые письма не создают настоящие токены.
- Полное содержимое писем и персональные ссылки не записываются в журнал.

### Совместимость

- Новая миграция не требуется: пользовательские шаблоны сохраняются в существующей таблице `cms_settings`.
- Зависимости Composer не изменялись.

## 0.9.3 - 2026-07-13

### Исправлено

- `doctor.ps1` больше не считает каталог недоступным для записи только из-за кратковременной ошибки удаления диагностического файла.
- Проверка каталогов загрузки выводит конкретный путь и текст ошибки, когда запись действительно невозможна.
- Вкладка «Система» использует ту же логику: успешная запись определяется отдельно от очистки временного файла.
- Очистка диагностических файлов выполняется в режиме best effort и не маскирует фактический результат проверки записи.

### Совместимость

- Миграции и зависимости Composer не изменялись.
- Исправление рассчитано на Windows и сохраняет совместимость с Linux.

## 0.9.2 - 2026-07-13

### Исправлено

- Активная новая учётная запись больше не распознаётся middleware как отключённая до повторной загрузки модели из базы.
- Проверка статуса пользователя блокирует доступ только при явном значении `is_active = false`.
- Тестовые пользователи корректно получают `email_verified_at`, поэтому поиск, фильтры и карточка пользователя проверяют реальное подтверждение email.
- Проверка подтверждения email по подписанной ссылке снова проходит вместе с middleware активной учётной записи.

### Тестирование

- Исправлены три ложных сбоя в `UserManagementTest` и `PublicUserAuthenticationTest`.
- Миграции и зависимости Composer не изменялись.

## 0.9.1 - 2026-07-13

### Добавлено

- Раздел «Пользователи» в административной панели по адресу `/admin/users`.
- Поиск пользователей CMS по логину и email.
- Фильтры по состоянию учётной записи и подтверждению email.
- Список с датой регистрации, датой последнего успешного входа и пагинацией по 50 записей.
- Подробная карточка пользователя с основными сведениями и последними связанными событиями журнала.
- Включение и отключение пользовательской учётной записи без физического удаления.
- Повторная отправка письма подтверждения email.
- Отправка стандартной ссылки восстановления пароля без доступа администратора к паролю пользователя.
- Автоматические тесты управления пользователями CMS.

### Изменено

- После успешного публичного входа и автоматического входа после регистрации сохраняется `last_login_at`.
- Отключённый пользователь не может войти, а существующая авторизованная сессия завершается при следующем защищённом запросе.
- Меню и главная страница админки содержат рабочую ссылку на раздел пользователей.
- Документация пользователей, панели, безопасности, журнала и дорожной карты обновлена.

### Безопасность

- Пароли, их хэши, токены подтверждения, токены восстановления и идентификаторы сессий не выводятся в панели.
- Отключение пользователя инвалидирует remember-токен и удаляет его серверные сессии из стандартной таблицы Laravel.
- Игровые аккаунты и персонажи не смешиваются с учётными записями CMS и остаются отдельным будущим этапом.

## 0.9.0 - 2026-07-13

### Добавлено

- Раздел «Администраторы» в панели управления.
- Список администраторов с датой создания, датой последнего входа и состоянием учётной записи.
- Создание дополнительных администраторов через веб-интерфейс.
- Изменение имени и email администратора.
- Смена собственного пароля с обязательным подтверждением текущего пароля.
- Установка нового пароля другому администратору.
- Включение и отключение административных учётных записей без физического удаления.
- Журналирование создания, изменения, смены пароля, включения и отключения администраторов.
- Документация раздела в `docs/ADMINISTRATORS.md`.
- Автоматические тесты управления административными учётными записями.

### Безопасность

- Нельзя отключить собственную учётную запись.
- Нельзя отключить последнего активного администратора.
- Сессия отключённого администратора завершается при следующем запросе к панели.
- Пароли не попадают в журнал действий.
- Все администраторы пока имеют одинаковые права; преждевременная система ролей не добавлялась.

## 0.8.4 - 2026-07-13

### Добавлено

- Вкладка «Система» в настройках администратора с версиями L2Forge CMS, PHP, Laravel и Composer.
- Сведения об операционной системе, PHP SAPI, архитектуре, окружении Laravel, драйверах кэша, сессий, очередей, почты и логов.
- Информация о подключении и версии базы CMS, относительном пути и размере SQLite-файла.
- Проверка базы данных, почты и реальной возможности записи в служебные каталоги.
- Список обязательных и дополнительных расширений PHP.
- Безопасный отчёт для поддержки с кнопкой копирования без паролей, ключей, токенов и абсолютных путей.
- Документация системной вкладки в `docs/SYSTEM.md`.

### Изменено

- Единственным источником версии установленной CMS стал корневой файл `VERSION`.
- Подвал админки, версия ресурсов, совместимость тем и PowerShell-скрипты используют номер из `VERSION`.
- `setup.ps1`, `update.ps1` и `doctor.ps1` проверяют наличие и формат файла версии.

## 0.8.3 - 2026-07-13

### Исправлено

- Команда очистки изображений новостей больше не вызывает ошибку `SplFileInfo::getMTime(): stat failed` на Windows, если файл исчез между получением списка и обработкой.
- Публичная авторизация теперь всегда использует guard `web` и не зависит от guard, который был активен ранее в том же сеансе или тесте.
- Существующие автоматические тесты журнала действий и очистки изображений теперь проходят после указанных исправлений.

### Установка

- В релиз добавлен `composer.lock`: чистая установка использует зафиксированные версии пакетов вместо разрешения последних доступных зависимостей.
- В `composer.json` зафиксирована расчётная платформа PHP 8.3.0, поэтому lock-файл остаётся совместимым с заявленным минимумом PHP 8.3 даже при сборке релиза на PHP 8.5.
- `setup.ps1` и `update.ps1` останавливаются с понятной ошибкой, если `composer.lock` отсутствует, и не устанавливают непредсказуемые версии зависимостей.
- Для первого скачивания пакетов по-прежнему требуется доступ к интернету либо заполненный локальный кэш Composer.

## 0.8.2 - 2026-07-13

### Добавлено

- Раздел «Журнал действий» в административной панели с категориями, пагинацией и подробным просмотром записи.
- Универсальная таблица `audit_logs` и сервис `AuditLogger` для ядра, адаптеров и будущих модулей.
- События входа и выхода администраторов и пользователей, управления новостями, настройками, игровыми серверами и темами.
- События регистрации, подтверждения email, смены пароля и отправки почтовых уведомлений.
- Фиксация результата, инициатора, объекта, IP-адреса, User-Agent и безопасных дополнительных данных.
- Команда `l2forge:logs-clean` с режимом `--dry-run`, настраиваемым сроком хранения и ежедневным заданием Laravel Scheduler.
- Документация журнала для администраторов и разработчиков модулей.
- Автоматические тесты интерфейса, событий, очистки и маскировки секретных данных.

### Безопасность

- Пароли, токены, cookies, ключи и другие секретные поля рекурсивно заменяются отметкой `[СКРЫТО]`.
- Сбой записи журнала не прерывает основную операцию CMS и попадает только в технический Laravel-log.
- Записи журнала доступны только администраторам и не редактируются через веб-интерфейс.
- При удалении исходного объекта сохраняются снимки его имени и идентификатора, без хранения секретного содержимого.

## 0.8.1 - 2026-07-13

### Добавлено

- В настройках регистрации отображаются требования к логину и паролю.
- Добавлен базовый русский файл сообщений валидации для публичных форм.
- Добавлены тесты правил регистрации и русских сообщений проверки пароля.

### Исправлено

- Сообщения о необходимости буквы, цифры и минимальной длине пароля теперь выводятся на русском языке.
- Те же русские сообщения применяются при установке нового пароля через восстановление доступа.

## 0.8.0 - 2026-07-13

### Added

- Separate public website user accounts, independent from CMS administrators and future Lineage II game accounts.
- Administrator settings tabs for registration and SMTP mail.
- Registration enable/disable switch and optional mandatory email verification.
- SMTP host, port, encryption, username, encrypted password, sender identity and notification email settings.
- Test-email action with a persisted successful verification state.
- Public registration, login, logout and minimal account pages.
- Signed email-verification links with resend support.
- Password-reset request and reset flows using one-time database tokens.
- Custom Russian verification and password-reset notifications.
- Rate limits for login, registration, verification email resend and password recovery.
- Automated coverage for registration settings, encrypted SMTP storage, user registration, login, verification and password recovery.

### Security

- SMTP passwords are encrypted with the application `APP_KEY`, never rendered back into forms and excluded from application logs.
- Registration with mandatory email verification cannot be enabled until a test email succeeds.
- Changing SMTP settings invalidates the previous successful mail test.
- Public registration is unavailable while required email delivery is not ready.
- User logins and emails are normalized to lowercase; login receives a database unique index.
- Passwords use the configured Argon2id hashing driver.
- Password recovery returns the same public response for known and unknown email addresses.

## 0.7.2 - 2026-07-12

### Added

- Database-backed list of game servers with create, edit and delete actions in the administrator settings.
- Automatic migration of existing 0.7.1 `server.*` settings into the first game-server record.
- Public rendering of multiple game servers in the default theme and on the basic About page.
- Confirmation dialog before deleting a game server.
- Automated coverage for optional fields, multiple servers, deletion and legacy migration.

### Changed

- Server rates and chronicles are now optional and disappear from the public theme when empty.
- The public label `Версия` was renamed to `Хроники`.
- Future database connection placeholders are grouped separately for each saved game server.

### Fixed

- The default hero background is aligned to the top so the character head is no longer cropped behind the upper page area.

## 0.7.1 - 2026-07-12

### Added

- Working **Game Server** settings tab at `/admin/settings/game-server`.
- Display settings for server name, rates, chronicle and mode.
- Public-theme integration for the hero block, server status panel and the basic information page.
- Special `None` or empty mode value that hides the Mode item from the public server panel.
- Prepared disabled fields for the future game-database host, port, database name, user and password.
- Automated coverage for access control, validation, public rendering, hidden mode and protection against storing placeholder connection values.

### Security

- Database connection placeholders are disabled, have no submitted names and are not stored in `cms_settings`.
- Game-server display values are validated and escaped by Blade before public rendering.

### Fixed

- `doctor.ps1` now checks the real writable leaf directories used for news covers, news content, logos and favicons instead of only their parent folders.

## 0.7.0 - 2026-07-12

### Added

- Administrator settings section at `/admin/settings` with top-level tabs for General, Game Server and Login Server.
- General settings for site name, short description, logo, favicon, timezone, administrator email and footer text.
- Default footer text: `© 2026 L2Forge-CMS`.
- Secure logo and favicon uploads with random filenames, strict format checks and automatic cleanup when images are replaced or removed.
- Public-theme integration for page titles, meta description, logo, favicon, hero description and footer text.
- Runtime application of the configured timezone with `.env` as the safe fallback.
- Environment diagnostics for the settings upload directory.
- Automated feature coverage for settings access, saving, uploads, replacement, removal, unsafe SVG rejection and placeholder tabs.

### Security

- SVG files are rejected for both logo and favicon uploads.
- Site images are stored only inside `public/uploads/settings` and database values are normalized before use or deletion.
- Database, SMTP and application secrets remain outside the administrator settings and continue to live in `.env`.

## 0.6.2 - 2026-07-12

### Changed

- Moved the red news deletion action from the editor to each item in the administrator news list.
- Replaced the `На сайте` action with `Удалить` so the primary list actions are grouped together.
- Kept the existing confirmation dialog and safe media cleanup behavior.

## 0.6.1 - 2026-07-12

### Added

- Permanent news deletion with a red confirmation action in the editor.
- Administrator news pagination with 10 items per page.
- Unsaved news preview rendered through the active public theme in a separate tab.
- Automatic removal of inline images removed while editing when no other news item references them.
- Cleanup of abandoned cover images in addition to inline images.
- Automated coverage for deletion, shared-image protection, preview, pagination, media cleanup and 0.5.0 plain-text migration.

### Security

- Preview routes require administrator authentication, CSRF validation and rate limiting.
- Preview responses are private, non-cacheable and excluded from indexing.
- Newly selected preview covers are embedded only in the one-time response and are not saved to disk.
- Media deletion accepts only validated paths inside `public/uploads/news` and rechecks database references before deleting files.

## 0.6.0 - 2026-07-12

### Added

- Visual news editor with headings, bold, italic, underline, strike-through, lists, quotes, links, alignment, text colors and separators.
- Cover image upload with preview, replacement and removal.
- Authenticated inline image upload for news content.
- Cover images on the home page, public news list and full news page.
- Responsive rich-content styling in the default theme.
- Migration converting existing 0.5.0 plain-text news bodies to safe HTML paragraphs.
- `l2forge:news-media-clean` command for safely removing old unreferenced inline images.

### Security

- Server-side allow-list HTML sanitizer based on DOM parsing.
- Scripts, styles, iframes, forms, SVG, event handlers and unknown attributes are removed.
- Inline image sources are restricted to files uploaded through L2Forge CMS.
- Uploads accept only validated JPEG, PNG and WebP images up to 5 MB and 6000×6000 pixels.
- Random UUID filenames prevent trusting original client filenames.
- Inline image uploads are authenticated, CSRF-protected and rate-limited.

## 0.5.0 - 2026-07-12

### Added

- News management section at `/admin/news`.
- Empty state with a direct action for creating the first news item.
- News creation and editing forms.
- Draft, scheduled and published states.
- Automatic unique slug generation with stable URLs after title edits.
- Publication counters and links to live news pages.
- Feature tests for administrator news management and public visibility.
- Clean installations now start without demo news, so the administrator sees the first-news empty state.

### Security

- News body is rendered as escaped plain text with preserved line breaks.
- Draft and scheduled news cannot be opened through public routes.
- All administrator write operations remain protected by authentication and CSRF.

## 0.4.0 - 2026-07-12

### Changed

- Project renamed from L2CMS Core to **L2Forge CMS**.
- Default application name changed to `L2Forge CMS`.
- Composer package renamed to `l2forge/cms`.
- Administrator creation command renamed to `php artisan l2forge:admin-create`.
- Installer, diagnostics, documentation, default theme metadata and control panel branding updated.
- Existing `.env` files using the old default `APP_NAME` are migrated automatically by `setup.ps1` and `update.ps1`.

## 0.3.2 - 2026-07-12

### Fixed

- Moved administrator static assets from `public/admin` to `public/assets/admin`.
- Fixed the physical directory collision that caused PHP's development server to return its own 404 page for `/admin`.
- Added an environment diagnostic check preventing the reserved `public/admin` path from returning.

## 0.3.1 - 2026-07-12

### Changed

- `/admin` is now the single entry point for the administration panel.
- `/admin/dashboard` redirects to `/admin` for compatibility.
- The administration interface now uses a simpler, neutral and minimal built-in design.
- All implemented and planned sections remain visible in the left navigation.
- The administration home page now presents available and planned sections without optional database statistics.
- The theme management page was simplified to a compact list.

### Fixed

- Removed unnecessary dashboard queries so the main `/admin` page is less likely to fail when optional data is unavailable.
- Added feature tests for the main administration route and compatibility redirect.

## 0.3.0 - 2026-07-12

### Added

- Unified administrator layout independent from public themes.
- Persistent administrator navigation with responsive behavior.
- Theme management page at `/admin/themes`.
- Database-backed CMS settings storage for the active theme.
- Theme manifest, required-file, slug, preview-path and CMS-version validation.
- Safe activation of preinstalled themes with CSRF and administrator authentication.
- Theme activation logging through the application log.
- Automated feature tests for theme management.

### Security

- Public themes cannot replace administrator templates or administrator assets.
- Theme activation accepts only validated slugs inside the configured themes directory.
- Invalid, damaged, missing, or incompatible themes cannot be activated.
- Theme ZIP upload remains disabled until secure archive extraction is implemented.

## 0.2.0 - 2026-07-12

### Added

- Separate administrator model, database table, session guard and protected `/admin` area.
- Administrator login and logout with session regeneration and invalidation.
- `php artisan l2cms:admin-create` command for secure creation of the first administrator.
- Rate limiting by normalized email and IP address.
- Security log for successful, failed, inactive and throttled login attempts.
- Argon2id as the default CMS password hashing driver.
- Security headers and `noindex` metadata for the administration area.
- Initial administration dashboard and responsive administration design.
- Automated feature tests for administrator authentication.

### Security

- No default administrator account or password is included in seed data.
- Authentication errors do not reveal whether an administrator email exists.
- Admin pages are non-cacheable and protected from framing.

## 0.1.1 - 2026-07-12

- Fixed clean Windows installation, PowerShell encoding, required directories and Laravel 13 dependencies.
