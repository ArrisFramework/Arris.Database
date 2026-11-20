# Как использовать

`Connector` — ленивая обёртка: реальное соединение с БД создаётся только
при первом запросе (`prepare`, `query`, `exec`, `lastInsertId`, ...).

```php
require_once __DIR__ . '/vendor/autoload.php';

$config = new \Arris\Database\Config([
    'driver'   => 'mysql',        // mysql | pgsql | sqlite
    'hostname' => 'localhost',
    'database' => '47news',
    'username' => 'wombat',
    'password' => 'wombatsql',
]);

$config->setSlowQueryThreshold(15); // 15 мс

$db = $config->connect();            // соединения ещё нет

// ИЛИ

$db = new \Arris\Database\Connector($config);

$sth = $db->prepare("SELECT COUNT(*) FROM articles");
$sth->execute();
var_dump($sth->fetchColumn());

// или

$sth = $db->query("SELECT COUNT(*) FROM articles");
var_dump($sth->fetchColumn());

// debug
var_dump($db->stats()->getSlowQueries());
var_dump($db->stats()->getLastQuery());

// настоящее PDO (инициализирует соединение)
$pdo = $db->pdo();

// текущий PDO-хэндл без принудительного коннекта (null до первого запроса)
$pdo = $db->getPDOConnector();
```

## Совместимость с legacy-кодом

В этой ветке `Connector` наследует `PDO`, поэтому его можно передавать в
классы, которые требуют `PDO $pdo`:

```php
final class LegacyRepository
{
    public function __construct(private PDO $pdo) {}
}

$repository = new LegacyRepository($config->connect()); // работает
```

При этом соединение остаётся ленивым: `parent::__construct()` не вызывается,
а все core-методы `PDO` делегируют в реальное PDO, создаваемое при первом
запросе. Для driver-specific методов (`sqliteCreateFunction`,
`pgsqlCopyFromArray` и т.п.) используйте `$connector->pdo()`.

## Драйверы

Работа с конкретной СУБД вынесена в `Arris\Database\Drivers\`:

- `MySQLDriver` — backticks, `SET NAMES ... COLLATE`, `REPLACE`, `INSERT ... SET`;
- `PgSQLDriver` — double quotes, `client_encoding`, без `charset` в DSN;
- `SqliteDriver` — путь к файлу/`:memory:`, double quotes, `REPLACE`.

```php
$driver = \Arris\Database\Drivers\DriverFactory::make($config);

$driver->getDsn();
$driver->quoteIdentifier('users.id'); // `users`.`id` или "users"."id"
```

`Connector::driver()` возвращает драйвер без установки соединения — это база
для будущего Query Builder/DBAL.

# Опции

Как установить, например, `PDO::ATTR_EMULATE_PREPARES`?

```php
$config = new \Arris\Database\Config();
$config->option(PDO::ATTR_EMULATE_PREPARES, true);
```

# Статистика

- getQueryCount - количество простых запросов (query, exec)
- getPreparedQueryCount - количество подготовленных запросов (prepare, execute)
- getTotalQueryCount - количество всего запросов (подготовленные и простые)
- getTotalQueryTime - время, потраченное всеми запросами
- getQueries - список запросов
- getSlowQueries - список медленных запросов
- getLastQuery - статистика по последнему запросу
- reset - обнуление статистики

`Config::$slowQueryThreshold` хранится в секундах; `setSlowQueryThreshold($ms)`
принимает миллисекунды, `setSlowQueryThreshold(null)` отключает сбор медленных
запросов. `Config::$maxStoredQueries` ограничивает размер истории (по умолчанию 500).
