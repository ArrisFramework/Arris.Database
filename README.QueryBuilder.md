# QueryBuilder - Примеры использования

NB: Класс сгенерирован моделью Claude Sonnet 4.5

## Основные операции

### SELECT запросы

```php
use Arris\Database\Helper\QueryBuilder;
use Arris\Database\Connector;

$connector = new Connector($pdo);
$qb = QueryBuilder::create($connector);

// Простой SELECT
$users = $qb->select('id', 'name', 'email')
    ->from('users')
    ->get();

// SELECT с алиасом таблицы
$users = $qb->select('u.id', 'u.name')
    ->from('users', 'u')
    ->get();

// SELECT с массивом колонок
$users = $qb->select(['id', 'name', 'email'])
    ->from('users')
    ->get();

// SELECT DISTINCT
$countries = $qb->select('country')
    ->distinct()
    ->from('users')
    ->get();
```

### WHERE условия

```php
// Простое WHERE
$user = $qb->select()
    ->from('users')
    ->where('id', 5)
    ->first();

// WHERE с оператором
$adults = $qb->select()
    ->from('users')
    ->where('age', '>=', 18)
    ->get();

// Множественные WHERE (AND)
$result = $qb->select()
    ->from('users')
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->get();

// OR WHERE
$result = $qb->select()
    ->from('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// WHERE NULL / NOT NULL
$inactive = $qb->select()
    ->from('users')
    ->whereNull('deleted_at')
    ->get();

$deleted = $qb->select()
    ->from('users')
    ->whereNotNull('deleted_at')
    ->get();

// WHERE IN / NOT IN
$users = $qb->select()
    ->from('users')
    ->whereIn('id', [1, 2, 3, 5, 8])
    ->get();

$excluded = $qb->select()
    ->from('users')
    ->whereNotIn('status', ['banned', 'deleted'])
    ->get();

// WHERE BETWEEN / NOT BETWEEN
$ranged = $qb->select()
    ->from('products')
    ->whereBetween('price', 100, 500)
    ->get();

$outside = $qb->select()
    ->from('products')
    ->whereNotBetween('stock', 0, 10)
    ->get();

// WHERE RAW (сырой SQL)
$result = $qb->select()
    ->from('users')
    ->whereRaw('YEAR(created_at) = ?', [2024])
    ->get();
```

### Группировка условий

```php
// Сложные условия с группировкой
$users = $qb->select()
    ->from('users')
    ->where('status', 'active')
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->get();

// Эквивалент SQL:
// WHERE status = 'active' AND (role = 'admin' OR role = 'moderator')
```

### JOIN операции

```php
// INNER JOIN
$result = $qb->select('users.name', 'orders.total')
    ->from('users')
    ->join('orders', 'users.id', 'orders.user_id')
    ->get();

// LEFT JOIN
$result = $qb->select('users.name', 'orders.total')
    ->from('users')
    ->leftJoin('orders', 'users.id', 'orders.user_id')
    ->get();

// RIGHT JOIN
$result = $qb->select()
    ->from('orders')
    ->rightJoin('users', 'orders.user_id', 'users.id')
    ->get();

// Множественные JOIN
$result = $qb->select('u.name', 'o.total', 'p.name as product')
    ->from('users', 'u')
    ->join('orders', 'o', 'u.id', 'o.user_id')
    ->join('products', 'p', 'o.product_id', 'p.id')
    ->get();

// CROSS JOIN
$result = $qb->select()
    ->from('colors')
    ->crossJoin('sizes')
    ->get();
```

### ORDER BY, LIMIT, OFFSET

```php
// ORDER BY
$users = $qb->select()
    ->from('users')
    ->orderBy('created_at', 'DESC')
    ->get();

// Множественная сортировка
$users = $qb->select()
    ->from('users')
    ->orderBy('status', 'ASC')
    ->orderBy('name', 'ASC')
    ->get();

// LIMIT и OFFSET (пагинация)
$users = $qb->select()
    ->from('users')
    ->orderBy('id')
    ->limit(10)
    ->offset(20)
    ->get();
```

### GROUP BY и HAVING

```php
// GROUP BY
$stats = $qb->select('country', 'COUNT(*) as total')
    ->from('users')
    ->groupBy('country')
    ->get();

// GROUP BY с множественными колонками
$stats = $qb->select('country', 'city', 'COUNT(*) as total')
    ->from('users')
    ->groupBy('country', 'city')
    ->get();

// HAVING
$popular = $qb->select('category', 'COUNT(*) as total')
    ->from('products')
    ->groupBy('category')
    ->having('total', '>', 10)
    ->get();
```

### UNION запросы

```php
// UNION
$active = $qb->select('id', 'name')->from('active_users');
$inactive = $qb->select('id', 'name')->from('inactive_users');

$all = $active->union($inactive)->get();

// UNION ALL
$result = $active->unionAll($inactive)->get();
```

## INSERT операции

```php
// INSERT одной записи
$qb->insert('users')
    ->values([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30
    ])
    ->execute();

// INSERT с получением ID
$userId = $qb->insert('users')
    ->values([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com'
    ])
    ->insertGetId();

// Короткий синтаксис с data()
$qb->insert('users')
    ->data([
        'name' => 'Bob Wilson',
        'email' => 'bob@example.com'
    ])
    ->execute();
```

## UPDATE операции

```php
// UPDATE с WHERE
$qb->update('users')
    ->set('status', 'inactive')
    ->where('last_login', '<', '2024-01-01')
    ->execute();

// UPDATE множественных полей
$qb->update('users')
    ->set([
        'status' => 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ])
    ->where('id', 5)
    ->execute();

// Короткий синтаксис с data()
$affected = $qb->update('users')
    ->data([
        'name' => 'Updated Name',
        'email' => 'new@example.com'
    ])
    ->where('id', 10)
    ->rowCount();

// UPDATE с несколькими условиями
$qb->update('products')
    ->set('discount', 10)
    ->where('category', 'electronics')
    ->where('stock', '>', 0)
    ->execute();
```

## DELETE операции

```php
// DELETE с WHERE
$qb->delete('users')
    ->where('status', 'deleted')
    ->execute();

// DELETE с множественными условиями
$deleted = $qb->delete('old_logs')
    ->where('created_at', '<', '2023-01-01')
    ->whereNull('archived_at')
    ->rowCount();

// DELETE с IN
$qb->delete('spam_messages')
    ->whereIn('id', [1, 5, 10, 15])
    ->execute();
```

## REPLACE операции

```php
// REPLACE (MySQL)
$qb->replace('settings')
    ->values([
        'key' => 'theme',
        'value' => 'dark'
    ])
    ->execute();

// Короткий синтаксис
$qb->replace('cache')
    ->data([
        'key' => 'user_123',
        'value' => serialize($data),
        'expires' => time() + 3600
    ])
    ->execute();
```

## Методы получения результатов

```php
// get() - все записи
$all = $qb->select()->from('users')->get();

// run() - алиас для get()
$all = $qb->select()->from('users')->run();

// first() - первая запись
$user = $qb->select()
    ->from('users')
    ->where('email', 'test@example.com')
    ->first();

// value() - значение одной колонки
$email = $qb->select()
    ->from('users')
    ->where('id', 5)
    ->value('email');

// pluck() - массив значений колонки
$names = $qb->select()
    ->from('users')
    ->pluck('name');
// ['John', 'Jane', 'Bob']

// pluck() с ключом
$emailsByName = $qb->select()
    ->from('users')
    ->pluck('email', 'name');
// ['John' => 'john@example.com', 'Jane' => 'jane@example.com']

// exists() - проверка существования
$hasActive = $qb->select()
    ->from('users')
    ->where('status', 'active')
    ->exists();
```

## Агрегатные функции

```php
// count() - подсчет записей
$total = $qb->select()
    ->from('users')
    ->count();

$activeCount = $qb->select()
    ->from('users')
    ->where('status', 'active')
    ->count();

// max() - максимальное значение
$maxPrice = $qb->select()
    ->from('products')
    ->max('price');

// min() - минимальное значение
$minAge = $qb->select()
    ->from('users')
    ->min('age');

// avg() - среднее значение
$avgRating = $qb->select()
    ->from('reviews')
    ->avg('rating');

// sum() - сумма значений
$totalSales = $qb->select()
    ->from('orders')
    ->where('status', 'completed')
    ->sum('total');
```

## Отладка

```php
// debug() - информация о запросе
$info = $qb->select('*')
    ->from('users')
    ->where('age', '>', 18)
    ->debug();

print_r($info);
// [
//     'sql' => 'SELECT * FROM users WHERE age > ?',
//     'bindings' => [18],
//     'type' => 'select'
// ]

// dd() - вывод и остановка
$qb->select()
    ->from('users')
    ->where('status', 'active')
    ->dd();
// SQL: SELECT * FROM users WHERE status = ?
// Bindings: ["active"]
// Type: select
// (скрипт завершается)

// toSQL() - только SQL без выполнения
$sql = $qb->select('name', 'email')
    ->from('users')
    ->where('age', '>=', 18)
    ->toSQL();
echo $sql;
// SELECT name, email FROM users WHERE age >= ?
```

## Повторное использование

```php
// reset() - сброс к начальному состоянию
$qb->select()->from('users')->where('status', 'active')->get();

$qb->reset(); // Очистка всех настроек

$qb->select()->from('products')->get(); // Новый запрос
```

## Сложные примеры

```php
// Комплексный SELECT с JOIN, WHERE, GROUP BY
$report = $qb->select([
        'users.country',
        'COUNT(orders.id) as order_count',
        'SUM(orders.total) as revenue'
    ])
    ->from('users')
    ->leftJoin('orders', 'users.id', 'orders.user_id')
    ->where('users.status', 'active')
    ->whereBetween('orders.created_at', '2024-01-01', '2024-12-31')
    ->groupBy('users.country')
    ->having('order_count', '>', 10)
    ->orderBy('revenue', 'DESC')
    ->get();

// Пагинация с фильтрами
$page = 2;
$perPage = 20;

$products = $qb->select('id', 'name', 'price', 'category')
    ->from('products')
    ->where('status', 'available')
    ->where(function($q) {
        $q->where('category', 'electronics')
          ->orWhere('category', 'computers');
    })
    ->whereBetween('price', 100, 1000)
    ->orderBy('price', 'ASC')
    ->limit($perPage)
    ->offset(($page - 1) * $perPage)
    ->get();

// Подзапрос через группировку
$premiumUsers = $qb->select('users.*')
    ->from('users')
    ->where(function($q) {
        $q->where('subscription', 'premium')
          ->where('status', 'active');
    })
    ->orWhere(function($q) {
        $q->where('lifetime_purchases', '>', 1000)
          ->whereNotNull('verified_at');
    })
    ->orderBy('created_at', 'DESC')
    ->get();
```

## Обработка ошибок

```php
try {
    $user = $qb->insert('users')
        ->values([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ])
        ->insertGetId();
    
    echo "Created user with ID: $user";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```