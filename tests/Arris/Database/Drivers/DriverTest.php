<?php

declare(strict_types=1);

namespace Tests\Arris\Database\Drivers;

use Arris\Database\Config;
use Arris\Database\Drivers\DriverFactory;
use Arris\Database\Drivers\MySQLDriver;
use Arris\Database\Drivers\PgSQLDriver;
use Arris\Database\Drivers\SqliteDriver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DriverTest extends TestCase
{
    public function testFactoryReturnsMatchingDriver(): void
    {
        self::assertInstanceOf(MySQLDriver::class, DriverFactory::make(new Config(['driver' => 'mysql'])));
        self::assertInstanceOf(PgSQLDriver::class, DriverFactory::make(new Config(['driver' => 'pgsql'])));
        self::assertInstanceOf(SqliteDriver::class, DriverFactory::make(new Config(['driver' => 'sqlite'])));
    }

    public function testFactoryRejectsUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DriverFactory::make(new Config(['driver' => 'oracle']));
    }

    public function testMysqlDsn(): void
    {
        $config = new Config([
            'driver' => 'mysql',
            'hostname' => 'db.local',
            'port' => 3307,
            'database' => 'app',
            'charset' => 'utf8mb4',
        ]);

        self::assertSame(
            'mysql:host=db.local;port=3307;dbname=app;charset=utf8mb4',
            $config->getDSN()
        );
    }

    public function testPgsqlDsnHasNoCharsetParameter(): void
    {
        $config = new Config([
            'driver' => 'pgsql',
            'hostname' => 'db.local',
            'database' => 'app',
        ]);

        $dsn = $config->getDSN();

        self::assertSame('pgsql:host=db.local;port=5432;dbname=app', $dsn);
        self::assertStringNotContainsString('charset=', $dsn);
    }

    public function testSqliteMemoryDsn(): void
    {
        $config = new Config(['driver' => 'sqlite', 'database' => ':memory:']);

        self::assertSame('sqlite::memory:', $config->getDSN());
    }

    public function testSqliteRequiresPath(): void
    {
        $config = new Config(['driver' => 'sqlite']);

        $this->expectException(InvalidArgumentException::class);

        $config->getDSN();
    }

    public function testSqliteRejectsMissingDirectory(): void
    {
        $config = new Config([
            'driver' => 'sqlite',
            'database' => '/definitely/not/here/' . uniqid('db_', true) . '.sqlite',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $config->getDSN();
    }

    public function testMysqlQuotesIdentifiersWithBackticks(): void
    {
        $driver = new MySQLDriver(new Config(['driver' => 'mysql']));

        self::assertSame('`users`.`id`', $driver->quoteIdentifier('users.id'));
        self::assertSame('`users`.*', $driver->quoteIdentifier('users.*'));
        self::assertSame('*', $driver->quoteIdentifier('*'));
        self::assertSame('`weird``name`', $driver->quoteIdentifier('weird`name'));
        self::assertSame('`already`', $driver->quoteIdentifier('`already`'));
    }

    public function testPgsqlAndSqliteQuoteIdentifiersWithDoubleQuotes(): void
    {
        foreach ([new PgSQLDriver(new Config(['driver' => 'pgsql'])), new SqliteDriver(new Config(['driver' => 'sqlite']))] as $driver) {
            self::assertSame('"users"."id"', $driver->quoteIdentifier('users.id'));
            self::assertSame('"a""b"', $driver->quoteIdentifier('a"b'));
        }
    }

    public function testCapabilities(): void
    {
        $mysql = new MySQLDriver(new Config(['driver' => 'mysql']));
        $pgsql = new PgSQLDriver(new Config(['driver' => 'pgsql']));
        $sqlite = new SqliteDriver(new Config(['driver' => 'sqlite']));

        self::assertTrue($mysql->supportsReplace());
        self::assertTrue($mysql->supportsInsertSet());

        self::assertFalse($pgsql->supportsReplace());
        self::assertFalse($pgsql->supportsInsertSet());

        self::assertTrue($sqlite->supportsReplace());
        self::assertFalse($sqlite->supportsInsertSet());
    }
}
