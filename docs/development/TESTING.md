# Тестовые данные и изоляция

Документ описывает правила подготовки данных для PHPUnit-тестов L2Forge CMS.

## Фабрики моделей

Для основных учётных и серверных сущностей используются фабрики:

```text
database/factories/UserFactory.php
database/factories/AdminFactory.php
database/factories/LoginServerFactory.php
database/factories/GameServerFactory.php
database/factories/UserGameAccountFactory.php
```

Новые тесты не должны собирать большие повторяющиеся массивы через `Model::query()->create()`, когда тот же сценарий можно выразить фабрикой и небольшим набором переопределений.

Пример серверной пары:

```php
$loginServer = LoginServer::factory()->create();
$gameServer = GameServer::factory()
    ->for($loginServer)
    ->create();
```

Пример доступного игрового аккаунта:

```php
$account = UserGameAccount::factory()
    ->for($user)
    ->registeredOn($gameServer)
    ->create();
```

Пример временно скрытой связи:

```php
$account = UserGameAccount::factory()
    ->for($user)
    ->orphaned($loginServer)
    ->create();
```

## Состояния мониторинга

`LoginServerFactory` и `GameServerFactory` имеют состояния:

```text
online()
offline()
stale()
```

Для GameServer состояние `online()` принимает количество игроков:

```php
$gameServer = GameServer::factory()
    ->for($loginServer)
    ->online(42)
    ->create();
```

Это предпочтительнее ручного заполнения всех полей `monitor_*` и `online_*` в каждом тесте.

## Начальная строка GameServer

Историческая миграция создания `game_servers` добавляет начальный игровой мир для совместимости старых установок. `RefreshDatabase` выполняет эту миграцию и в тестовой базе.

Тесты, которые проверяют точное количество серверов, срок мониторинга, переназначение или удаление последнего мира, не должны полагаться на наличие либо отсутствие этой строки.

Используйте concern:

```php
use Tests\Concerns\InteractsWithServerFixtures;

class ExampleTest extends TestCase
{
    use InteractsWithServerFixtures, RefreshDatabase;

    public function test_example(): void
    {
        [$loginServer, $gameServer] = $this->freshMobiusServerPair();
    }
}
```

`freshMobiusServerPair()` сначала удаляет связи игровых аккаунтов и серверные записи, затем создаёт ровно одну согласованную пару LoginServer/GameServer через фабрики.

## Проверка конкурентных сценариев

SQLite в памяти не воспроизводит полноценную конкурентную блокировку MySQL. Для проверки повторной валидации внутри транзакции допустимо внедрять контролируемое изменение состояния между предварительной и финальной проверками.

`RaceInjectingGameAccountGateway` создаёт конкурирующую связь во время предварительного обращения к внешнему LoginServer. Регрессионный тест подтверждает, что финальная проверка квоты внутри транзакции отклоняет второй аккаунт.

Такая проверка не заменяет интеграционный тест на целевой СУБД, но защищает сам инвариант бизнес-логики.

## Правила регрессионных тестов

Каждый исправленный дефект должен иметь тест, который:

1. воспроизводит исходный сценарий;
2. проверяет пользовательский или бизнес-результат, а не внутреннюю реализацию;
3. создаёт только необходимые данные;
4. не зависит от порядка запуска других тестов;
5. не зависит от локального `.env`, seeders или демонстрационных строк миграций.

Перед релизом выполняется полный шлюз:

```powershell
composer quality
```

Подробности: `docs/development/QUALITY.md`.
