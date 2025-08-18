```php
// Скалярный массив - ключи совпадают со значениями
$t1 = new \Arris\Database\Tables(tables: ['a', 'b']);
echo $t1['a']; // выводит: 'a'
echo $t1['b']; // выводит: 'b'

// Ассоциативный массив
$t2 = new TableRepository(tables: ['users' => 'users_table', 'orders' => 'orders_table']);
echo $t2['users'];  // выводит: 'users_table'
echo $t2['orders']; // выводит: 'orders_table'

// Добавление таблицы без указания имени - имя равно ключу
$t3 = new TableRepository(prefix: 'prefix_');
$t3->addTable('users'); // tableName = 'users' (автоматически)
$t3->addTable('orders', withPrefix: true); // tableName = 'orders', с префиксом
echo $t3['users'];   // выводит: 'users'
echo $t3['orders'];  // выводит: 'prefix_orders'

// Добавление таблицы с явным указанием имени
$t4 = new TableRepository();
$t4->addTable('users', 'user_table_name');
echo $t4['users']; // выводит: 'user_table_name'
```

Или, более жизненный пример:

```php
$this->tables = new \Arris\Database\Tables(tables: ['taverns', 'dishes']);
$this->tables
    ->addTable('users', 'auth_users')
    ->addTable('auth.confirm', 'auth_users_confirmations')
    ->addTable('auth.remember','auth_users_remembered')
    ->addTable( 'auth.resents','auth_users_resets')
    ->addTable('auth.throttling', 'auth_users_throttling')
    ->addTable('taverns')
;

// позже, где-то в коде:

$sth = $this->pdo->prepare("SELECT * FROM {$this->tables['users']} WHERE id = ?"); // auth_users (объявлено в addTable)

$sth = $this->pdo->prepare("SELECT * FROM {$this->tables['taverns']} WHERE id = ?"); // taverns (объявлено в конструкторе)

// и так тоже работает:
$sql = "
SELECT d.*, u.email, u.username, t.title AS tavern_title 
    FROM {$this->tables['dishes']} AS d
    LEFT JOIN {$this->tables['taverns']} AS t ON d.tavern_id = t.id 
    LEFT JOIN {$this->tables['users']} AS u ON d.owner_id = u.id 
    ORDER BY t.id
    ";
// хотя можно было бы указать 

$this->tables->addTable("taverns", alias: 't'); // и получить при вызове

$sql = "SELECT * FROM {$this->tables['taverns']} WHERE id = ?"; 

// SELECT * FROM taverns AS t WHERE id = ? 



```

