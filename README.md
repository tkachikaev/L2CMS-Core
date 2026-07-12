# L2CMS Core 0.3.1

Первый рабочий каркас открытой CMS для серверов Lineage II. Это основа для последовательной разработки, а не готовый публичный релиз.

## Уже есть

- Laravel 13 и PHP 8.3+.
- Отдельная база CMS и отдельное подключение `game` к базе L2J Mobius.
- Система сменных тем `themes/<theme>`.
- Безопасный демонстрационный адаптер `mock` и начальный адаптер `mobius`.
- Главная страница, новости, статус, рейтинг, страницы входа и регистрации.
- SQLite для простого локального запуска.
- Адаптивная тёмная тема в стиле Lineage II.
- Статический предпросмотр в `preview/index.html`.

## Что изменено в 0.3.1

- `/admin` закреплён как единая точка входа в административную панель.
- Все реализованные и запланированные разделы видны в левом меню.
- Административный интерфейс упрощён и больше не использует оформление в стиле публичной L2-темы.
- Главная страница админки показывает доступные и будущие разделы.
- Раздел `/admin/themes` переведён на компактный минималистичный список.
- `/admin/dashboard` перенаправляется на `/admin`.
- Убраны необязательные запросы статистики с главной страницы админки.

## Первый запуск в Windows

Откройте PowerShell в папке проекта:

```powershell
Set-ExecutionPolicy Bypass -Scope Process
.\setup.ps1
```

После успешной установки:

```powershell
.\serve.ps1
```

Откройте:

```text
http://127.0.0.1:8000
```

`setup.ps1` выполняется при первой установке. Для обычного запуска используется только `serve.ps1`.

## Диагностика

```powershell
.\doctor.ps1
```

Скрипт проверяет PHP, Composer, расширения PHP, `.env`, зависимости, SQLite и служебные каталоги Laravel.

## Обновление проекта

После получения новой версии файлов:

```powershell
.\update.ps1
```

Скрипт сохраняет существующий `.env` и `APP_KEY`, устанавливает зависимости, выполняет миграции и тесты.

## Важно

`GAME_ADAPTER=mock` установлен намеренно. Регистрация и вход пока не записывают ничего в игровую БД. Сначала нужно зафиксировать конкретную версию Mobius, структуру таблиц и алгоритм хранения пароля.

Фоновое изображение темы используется как макет. Перед публичным релизом его нужно заменить на собственный или лицензированный арт.


## Administrator panel

After setup or update, create an administrator:

```powershell
php artisan l2cms:admin-create
```

Then start the project and open:

```text
http://127.0.0.1:8000/admin/login
```

Administrator accounts are separate from game accounts. See `docs/ADMIN_AUTH.md`.


## Theme management

Open:

```text
http://127.0.0.1:8000/admin/themes
```

The panel can inspect and activate themes already installed in `themes/<slug>`. Theme archive upload is intentionally postponed until secure archive validation is implemented. See `docs/THEMES.md` and `docs/ADMIN_PANEL.md`.
