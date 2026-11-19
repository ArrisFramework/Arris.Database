<?php

declare(strict_types=1);

namespace Tests\Arris\Database\Integration;

use Arris\Database\Config;
use Arris\Database\Connector;
use Arris\Database\Statement;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SqliteIntegrationTest extends TestCase
{
    private string $path;
    private Config $config;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'arris_db_');
        if ($path === false) {
            throw new RuntimeException('Cannot create temp database file');
        }

        $this->path = $path;
        $this->config = new Config([
            'driver' => 'sqlite',
            'database' => $path,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testConnectionIsLazy(): void
    {
        $db = new Connector($this->config);

        self::assertFalse($db->isConnected());
        self::assertNull($db->getPDOConnector());

        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

        self::assertTrue($db->isConnected());
        self::assertNotNull($db->getPDOConnector());
    }

    public function testPublicPdoAccessorForcesConnection(): void
    {
        $db = new Connector($this->config);

        self::assertFalse($db->isConnected());

        $pdo = $db->pdo();

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertTrue($db->isConnected());
        self::assertSame($pdo, $db->getPDOConnector());
    }

    public function testPreparedStatementExposesFullPdoStatementApi(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['one']);
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['two']);

        $sth = $db->prepare('SELECT id, name FROM t ORDER BY id');

        self::assertInstanceOf(Statement::class, $sth);

        $sth->execute();

        self::assertSame(2, $sth->columnCount());
        self::assertIsArray($sth->getColumnMeta(0));

        $row = $sth->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['id' => 1, 'name' => 'one'], $row);

        $object = $sth->fetchObject();
        self::assertNotFalse($object);
        self::assertSame('two', $object->name);
    }

    public function testStatementIsIterableAndSupportsBindColumn(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['one']);
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['two']);

        $names = [];
        foreach ($db->query('SELECT name FROM t ORDER BY id') as $row) {
            $names[] = $row['name'];
        }
        self::assertSame(['one', 'two'], $names);

        $sth = $db->prepare('SELECT name FROM t ORDER BY id');
        $sth->execute();
        $sth->bindColumn(1, $boundName);
        $sth->fetch(PDO::FETCH_BOUND);

        self::assertSame('one', $boundName);
    }

    public function testBindValueIsCapturedInStatistics(): void
    {
        $db = new Connector($this->config);

        $sth = $db->prepare('SELECT ?');
        $sth->bindValue(1, 42, PDO::PARAM_INT);
        $sth->execute();

        $last = $db->stats()->getLastQuery();

        self::assertNotNull($last);
        self::assertEquals([1 => 42], $last['params']);
    }

    public function testStatisticsCountQueriesAndPreparedStatements(): void
    {
        $db = new Connector($this->config);

        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $db->query('SELECT * FROM t');
        $db->prepare('SELECT * FROM t WHERE id = ?')->execute([1]);

        $stats = $db->stats();

        // exec + query
        self::assertSame(2, $stats->getQueryCount());
        self::assertSame(1, $stats->getPreparedQueryCount());
        self::assertSame(3, $stats->getTotalQueryCount());
        self::assertCount(3, $stats->getQueries());
        self::assertSame('prepared', $stats->getLastQuery()['type']);
        self::assertSame('SUCCESS', $stats->getLastQuery()['state']);
    }

    public function testSlowQueryCollectionCanBeEnabledAndDisabled(): void
    {
        $this->config->setSlowQueryThreshold(0);

        $db = new Connector($this->config);
        $db->query('SELECT 1');

        self::assertNotEmpty($db->stats()->getSlowQueries());

        $this->config->setSlowQueryThreshold(null);
        $db = new Connector($this->config);
        $db->query('SELECT 1');

        self::assertSame([], $db->stats()->getSlowQueries());
    }

    public function testStatsStorageIsCapped(): void
    {
        $this->config->maxStoredQueries = 2;

        $db = new Connector($this->config);

        for ($i = 0; $i < 5; $i++) {
            $db->query('SELECT 1');
        }

        self::assertCount(2, $db->stats()->getQueries());
    }

    public function testTransactionsCanBeRolledBackAndWrapped(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

        $db->beginTransaction();
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['rolled-back']);
        $db->rollBack();

        self::assertSame(0, (int)$db->query('SELECT COUNT(*) FROM t')->fetchColumn());

        $db->transaction(function (Connector $connection): void {
            $connection->prepare('INSERT INTO t (name) VALUES (?)')->execute(['committed']);
        });

        self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM t')->fetchColumn());
    }

    public function testTransactionRollsBackOnException(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            $db->transaction(function (Connector $connection): void {
                $connection->prepare('INSERT INTO t (name) VALUES (?)')->execute(['boom']);
                throw new RuntimeException('fail');
            });
            self::fail('Exception was not thrown');
        } catch (RuntimeException $e) {
            self::assertSame('fail', $e->getMessage());
        }

        self::assertSame(0, (int)$db->query('SELECT COUNT(*) FROM t')->fetchColumn());
    }

    public function testNonExistentDatabaseFileIsCreated(): void
    {
        $path = sys_get_temp_dir() . '/arris_created_' . uniqid('', true) . '.sqlite';

        $db = new Connector(new Config([
            'driver' => 'sqlite',
            'database' => $path,
        ]));

        try {
            $db->query('SELECT 1');
            self::assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    public function testMissingDirectoryIsReportedInsteadOfSilentMemoryDatabase(): void
    {
        $db = new Connector(new Config([
            'driver' => 'sqlite',
            'database' => '/definitely/not/here/' . uniqid('db_', true) . '.sqlite',
        ]));

        $this->expectException(InvalidArgumentException::class);

        $db->query('SELECT 1');
    }

    public function testMemoryDatabaseIsSupported(): void
    {
        $db = new Connector(new Config([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]));

        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['memory']);

        self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM t')->fetchColumn());
    }

    public function testDriverIsAvailableWithoutConnection(): void
    {
        $db = new Connector($this->config);

        self::assertSame('sqlite', $db->driver()->getName());
        self::assertFalse($db->isConnected());
    }

    public function testQueryBuilderWorksAgainstRealDatabase(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, role TEXT)');

        $id = $db->builder()
            ->insert('users')
            ->values(['name' => 'Alice', 'role' => 'admin'])
            ->insertGetId();

        self::assertGreaterThan(0, (int)$id);

        $db->builder()
            ->insert('users')
            ->values(['name' => 'Bob', 'role' => 'user'])
            ->execute();

        self::assertSame(2, $db->builder()->select()->from('users')->count());
        self::assertTrue($db->builder()->select()->from('users')->where('name', 'Bob')->exists());
        self::assertFalse($db->builder()->select()->from('users')->where('name', 'Nobody')->exists());

        self::assertSame('Alice', $db->builder()->select()->from('users')->where('role', 'admin')->value('name'));
        self::assertSame(['Alice'], $db->builder()->select('name')->from('users')->where('role', 'admin')->pluck('name'));

        self::assertSame(
            ['Alice', 'Bob'],
            $db->builder()->select('name')->from('users')->where('role', 'admin')->orWhere('role', 'user')->orderBy('id')->pluck('name')
        );

        $affected = $db->builder()
            ->update('users')
            ->set('role', 'guest')
            ->where('name', 'Bob')
            ->rowCount();

        self::assertSame(1, $affected);

        $db->builder()->delete('users')->where('name', 'Alice')->execute();

        self::assertSame(1, $db->builder()->select()->from('users')->count());
    }

    public function testLastInsertIdIsDelegatedToDriver(): void
    {
        $db = new Connector($this->config);
        $db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $db->prepare('INSERT INTO t (name) VALUES (?)')->execute(['x']);

        self::assertSame('1', $db->lastInsertId());
    }
}
