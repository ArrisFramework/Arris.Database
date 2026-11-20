<?php

namespace Arris\Database;

use Arris\Database\Drivers\DriverFactory;
use Arris\Database\Drivers\DriverInterface;
use Arris\Database\Helper\QueryBuilder;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * PDO-совместимый фасад над ленивым соединением.
 *
 * Класс наследует PDO, чтобы его можно было передавать в legacy-код,
 * который требует аргумент `PDO $pdo`. При этом parent::__construct()
 * НЕ вызывается: реальное PDO создаётся лениво внутри и все core-методы
 * делегируют в него.
 *
 * Ограничения фасада:
 * - driver-specific методы (sqliteCreateFunction, pgsqlCopyFromArray и т.п.)
 *   нужно вызывать на реальном PDO: `$connector->pdo()`;
 * - clone/serialize не поддерживаются (как и у обычного PDO).
 */
class Connector extends PDO
{
    private ?PDO $pdo = null;
    private ?DriverInterface $driver = null;
    private Stats $stats;

    public Tables $tables;
    public bool $is_connected = false;

    public function __construct(private readonly Config $config)
    {
        $this->stats = new Stats($config);
        $this->tables = $config->tables;
    }

    /**
     * Возвращает (при необходимости создаёт) реальное соединение.
     */
    private function connection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        // initTables() мог быть вызван уже после создания коннектора
        $this->tables = $this->config->tables;

        $this->driver = DriverFactory::make($this->config);
        $pdo = $this->driver->connect();

        // PDO сам будет создавать Arris\Database\Statement с уже
        // инициализированным внутренним состоянием.
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$this->stats]]);

        $this->pdo = $pdo;
        $this->is_connected = true;

        return $pdo;
    }

    /**
     * Публичный доступ к реальному PDO с форсированием lazy-коннекта.
     */
    public function pdo(): PDO
    {
        return $this->connection();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $startTime = microtime(true);

        try {
            return $this->connection()->prepare($query, $options);
        } catch (PDOException $e) {
            $this->stats->recordQuery('prepare', $query, null, $startTime, true);
            throw $e;
        }
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $pdo = $this->connection();
        $startTime = microtime(true);

        try {
            $result = $fetchMode === null
                ? $pdo->query($query)
                : $pdo->query($query, $fetchMode, ...$fetchModeArgs);

            $this->stats->recordQuery('query', $query, null, $startTime);

            return $result;
        } catch (PDOException $e) {
            $this->stats->recordQuery('query', $query, null, $startTime, true);
            throw $e;
        }
    }

    public function exec(string $statement): int|false
    {
        $pdo = $this->connection();
        $startTime = microtime(true);

        try {
            $result = $pdo->exec($statement);
            $this->stats->recordQuery('exec', $statement, null, $startTime);
            return $result;
        } catch (PDOException $e) {
            $this->stats->recordQuery('exec', $statement, null, $startTime, true);
            throw $e;
        }
    }

    public function beginTransaction(): bool
    {
        return $this->connection()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection()->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->connection()->inTransaction();
    }

    /**
     * Выполняет callback внутри транзакции с автоматическим rollback при ошибке.
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->inTransaction()) {
            return $callback($this);
        }

        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    public function errorCode(): ?string
    {
        return $this->connection()->errorCode() ?: null;
    }

    public function errorInfo(): array
    {
        return $this->connection()->errorInfo();
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->connection()->getAttribute($attribute);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->connection()->setAttribute($attribute, $value);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->driver()->lastInsertId($this->connection(), $name);
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->connection()->quote($string, $type);
    }

    public function stats(): Stats
    {
        return $this->stats;
    }

    public function metrics(): Stats
    {
        return $this->stats;
    }

    public function driver(): DriverInterface
    {
        return $this->driver ??= DriverFactory::make($this->config);
    }

    /**
     * Текущий PDO-хэндл без принудительного соединения.
     */
    public function getPDOConnector(): ?PDO
    {
        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->is_connected = false;
    }

    public function builder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }
}
