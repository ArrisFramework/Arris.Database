<?php

declare(strict_types=1);

namespace Tests\Arris\Database;

use Arris\Database\Config;
use Arris\Database\Connector;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class PdoFacadeTest extends TestCase
{
    private function connector(): Connector
    {
        return new Connector(new Config([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]));
    }

    public function testConnectorIsInstanceOfPdo(): void
    {
        self::assertInstanceOf(PDO::class, $this->connector());
    }

    public function testConnectorCanBeInjectedIntoPdoTypedConsumerBeforeConnection(): void
    {
        $connector = $this->connector();

        $consumer = new class($connector) {
            public function __construct(private readonly PDO $pdo)
            {
            }

            public function scalar(): mixed
            {
                return $this->pdo->query('SELECT 42')->fetchColumn();
            }
        };

        self::assertFalse($connector->isConnected());
        self::assertSame(42, (int)$consumer->scalar());
        self::assertTrue($connector->isConnected());
    }

    public function testPdoAccessorReturnsRealPdo(): void
    {
        $connector = $this->connector();

        self::assertNull($connector->getPDOConnector());

        $pdo = $connector->pdo();

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame($pdo, $connector->getPDOConnector());
        self::assertNotSame($connector, $pdo);
    }

    public function testStatisticsStillWorkThroughFacade(): void
    {
        $connector = $this->connector();

        $connector->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $connector->query('SELECT * FROM t');
        $connector->prepare('SELECT * FROM t WHERE id = ?')->execute([1]);

        self::assertSame(2, $connector->stats()->getQueryCount());
        self::assertSame(1, $connector->stats()->getPreparedQueryCount());
    }

    public function testAllPdoCoreInstanceMethodsAreOverridden(): void
    {
        $pdoRef = new ReflectionClass(PDO::class);
        $connectorRef = new ReflectionClass(Connector::class);

        foreach ($pdoRef->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor()) {
                continue;
            }

            // Driver-specific методы (sqlite*/pgsql*/...) фасад не покрывает:
            // для них есть pdo().
            if (preg_match('/^(sqlite|pgsql|mysql|oci|sqlsrv|firebird|cubrid)/i', $method->getName())) {
                continue;
            }

            $name = $method->getName();

            self::assertTrue($connectorRef->hasMethod($name), "PDO::{$name}() is not declared");
            self::assertSame(
                Connector::class,
                $connectorRef->getMethod($name)->getDeclaringClass()->getName(),
                "PDO::{$name}() is not overridden by Connector"
            );
        }
    }
}
