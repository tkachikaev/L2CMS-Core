# Установка на обычный хостинг

Разные хостинги по-разному называют публичный каталог домена. KaevCMS не пытается его угадать: передайте точное имя каталога ключом сборки.

## Сборка production-пакета

Стандартный каталог `public_html`:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1
```

Другое имя публичного каталога:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName example.hosting.test
```

Собственное имя закрытого ядра и каталог результата:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName public_html `
    -CoreDirectoryName private-kaevcms `
    -OutputDirectory D:\Releases
```

Скрипт удаляет скопированный `vendor` и заново создаёт чистый production-only набор зависимостей командой `composer install --no-dev --optimize-autoloader`. После этого он формирует переносимый ZIP с разделителями `/`. Ключ `-IncludeDevelopmentDependencies` предназначен только для временной диагностики и не должен использоваться на публичном сайте.

Рабочие данные из `public/uploads`, `public/storage` и файл `public/hot` в пакет не попадают. В архив создаётся чистый каталог `uploads` с защитным `.htaccess`, запрещающим выполнение PHP-подобных файлов. Поэтому production-пакет можно собирать даже из уже установленной тестовой копии без переноса пользовательских изображений.

## Примеры хостингов

### Beget и распространённые панели cPanel

В каталоге сайта обычно находится `public_html`. Используйте команду без параметров и распакуйте ZIP в родительский каталог сайта:

```text
site-root/
├── kaevcms-core/
└── public_html/
```

Не распаковывайте весь пакет внутри `public_html`.

### Jino

Технический домен может одновременно быть именем публичного каталога. Соберите пакет с точным именем:

```powershell
.\deployment\windows\build-shared-hosting-package.ps1 `
    -PublicDirectoryName a860dbbcc70b.hosting.myjino.ru
```

Распакуйте пакет туда, где панель Jino ожидает каталог домена, чтобы он и закрытое ядро находились рядом.

### Неизвестный хостинг

В настройках домена найдите `Document Root`, «Корень сайта», «Публичная папка» или «Рабочая директория». В `-PublicDirectoryName` передавайте только имя конечного каталога. После распаковки файл `index.php` должен лежать непосредственно в этой публичной папке.

## Правильная структура

```text
parent-directory/
├── kaevcms-core/
│   ├── app/
│   ├── bootstrap/
│   ├── storage/
│   └── vendor/
└── public_html-или-каталог-домена/
    ├── index.php
    ├── .htaccess
    ├── kaevcms-path.php
    ├── install/
    └── uploads/
```

Домен никогда не должен быть направлен на `kaevcms-core`.


## HTTPS во время установки

Страницу проверки окружения можно открыть по HTTP, но Web Installer не отправит пароль MySQL и пароль владельца по незашифрованному соединению. До проверки базы и финального шага настройте HTTPS в панели хостинга и продолжайте установку по адресу `https://.../install/`.

Исключение разрешено только для локального loopback-тестирования (`127.0.0.1` или `::1`). Заголовок `X-Forwarded-Proto` учитывается только от локального или частного reverse proxy.

## Обновление

На shared-hosting используйте кумулятивный Web Update из панели администратора. Один актуальный пакет обновляет любую указанную в его манифесте поддерживаемую версию — промежуточные архивы устанавливать не требуется.
