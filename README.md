# Как использовать

```php
require_once __DIR__ . '/vendor/autoload.php';

$config = new \Arris\Database\Config();

$config->setUsername('wombat')
    ->setPassword('wombatsql')
    ->setDatabase('47news')
    ->setSlowQueryThreshold(15);

$pdo = $config->connect();

// ИЛИ

$pdo = new \Arris\Database\Connector($config);

$sth = $pdo->prepare("SELECT COUNT(*) FROM articles");
$sth->execute();
var_dump($sth->fetchColumn());

// или

$sth = $pdo->query("SELECT COUNT(*) FROM articles");
var_dump($sth->fetchColumn());

// debug
var_dump($pdo->stats()->getSlowQueries());

var_dump($pdo->stats()->getLastQuery());
```

# Опции

Как установить, например, `PDO::ATTR_EMULATE_PREPARES`?

```php
$config = new \Arris\Database\Config();
$config->option(PDO::ATTR_EMULATE_PREPARES, true);
```

# Статистика

- getQueryCount - количество простых запросов (query)
- getPreparedQueryCount - количество подготовленных запросов (prepare, execute)
- getTotalQueryCount - количество всего запросов (подготовленные и простые)
- getTotalQueryTime - время, потраченное всеми запросами
- getQueries - список запросов
- getSlowQueries - список медленных запросов
- getLastQuery - статистика по последнему запросу
- reset - обнуление статистики
