<?php

namespace Arris\Database;

use Arris\Database\Drivers\DriverFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Config
{
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_CHARSET_COLLATE = 'utf8mb4_unicode_ci';

    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';

    public LoggerInterface $logger;

    public string $driver = self::DRIVER_MYSQL;
    public string $hostname = '127.0.0.1';
    public ?int $port = null;
    public string $username = 'root';
    public string $password = '';
    public ?string $database = null;

    public string $charset = self::DEFAULT_CHARSET;
    public ?string $charset_collation = self::DEFAULT_CHARSET_COLLATE;

    private array $options = [];
    private array $driverOptions = [];

    /**
     * Порог «медленного» запроса в СЕКУНДАХ. null — не собирать медленные.
     */
    public ?float $slowQueryThreshold = 1.0;

    public bool $collectBacktrace = true;

    /**
     * Максимум хранимых записей в Stats (0 — без ограничения).
     */
    public int $maxStoredQueries = 500;

    public Tables $tables;

    public function __construct(array $connection_config = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        $this->driver   = $connection_config['driver'] ?? self::DRIVER_MYSQL;
        $this->hostname = $connection_config['hostname'] ?? $connection_config['host'] ?? '127.0.0.1';
        $this->port     = isset($connection_config['port']) ? (int)$connection_config['port'] : null;
        $this->username = $connection_config['username'] ?? 'root';
        $this->password = $connection_config['password'] ?? '';
        $this->database = $connection_config['database'] ?? $connection_config['dbname'] ?? null;

        if (isset($connection_config['charset'])) {
            $this->charset = $connection_config['charset'];
        }

        // charset_collation — актуальный ключ; charset_collate — legacy.
        $collation = $connection_config['charset_collation']
            ?? $connection_config['charset_collate']
            ?? null;

        if ($collation !== null) {
            $this->charset_collation = $collation;
        }

        if (array_key_exists('slow_query_threshold', $connection_config)) {
            $this->setSlowQueryThreshold($connection_config['slow_query_threshold'], false);
        }

        if (array_key_exists('collect_backtrace', $connection_config)) {
            $this->collectBacktrace = (bool)$connection_config['collect_backtrace'];
        }

        if (array_key_exists('max_stored_queries', $connection_config)) {
            $this->maxStoredQueries = max(0, (int)$connection_config['max_stored_queries']);
        }

        $this->tables = new Tables();
    }

    public function initTables(string $prefix = '',
                               array $tables = [],
                               array $havePrefix = [],
                               array $haveAlias = []): self
    {
        $this->tables = new Tables($prefix, $tables, $havePrefix, $haveAlias);
        return $this;
    }

    public function addTable(string $key,
                             ?string $tableName = null,
                             ?string $replacement = null,
                             bool $withPrefix = false,
                             ?string $alias = null): self
    {
        $this->tables->addTable($key, $tableName, $replacement, $withPrefix, $alias);
        return $this;
    }

    public function getTable($key = null): string|array
    {
        return $this->tables->getTable($key);
    }

    public function setDriver(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function setDatabase(string $database): self
    {
        $this->database = $database;
        return $this;
    }

    public function setHost(string $host = 'localhost'): self
    {
        $this->hostname = $host;
        return $this;
    }

    public function setPort(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function setCharsetCollation(?string $charset_collation): self
    {
        $this->charset_collation = $charset_collation;
        return $this;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    public function option(int $option, mixed $value): self
    {
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Драйверная опция имеет приоритет над общей option() с тем же ключом.
     */
    public function driverOption(int $option, mixed $value): self
    {
        $this->driverOptions[$option] = $value;
        return $this;
    }

    /**
     * Get DSN (Data Source Name). Конкретный формат — за драйвером.
     */
    public function getDSN(): string
    {
        return DriverFactory::make($this)->getDsn();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getOptions(): array
    {
        return array_replace($this->options, $this->driverOptions);
    }

    public function getSlowQueryThreshold(): ?float
    {
        return $this->slowQueryThreshold;
    }

    public function connect(): Connector
    {
        return new Connector($this);
    }

    /**
     * @param float|int|null $value  null — отключить сбор медленных запросов
     * @param bool $as_ms            true: $value в миллисекундах
     */
    public function setSlowQueryThreshold(float|int|null $value, bool $as_ms = true): self
    {
        $this->slowQueryThreshold = $value === null
            ? null
            : ($as_ms ? ((float)$value) / 1000 : (float)$value);

        return $this;
    }
}
