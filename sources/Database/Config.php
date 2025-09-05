<?php

namespace Arris\Database;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Config implements ConfigInterface
{
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_CHARSET_COLLATE = 'utf8mb4_unicode_ci';

    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';

    public LoggerInterface $logger;

    public string $driver = 'mysql';
    public string $hostname = 'localhost';
    public mixed $port = 3306;
    public string $username;
    public string $password;
    public ?string $database;

    public string $charset = self::DEFAULT_CHARSET;
    public string $charset_collation = self::DEFAULT_CHARSET_COLLATE;

    private array $options = [];
    private array $driverOptions = [];

    public ?float $slowQueryThreshold = 1.0; // ms
    public bool $collectBacktrace = true;

    public Tables $tables;

    public function __construct(array $connection_config = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->driver   = $connection_config['driver'] ?? 'mysql';
        $this->hostname = $connection_config['hostname'] ?? '127.0.0.1';
        $this->port     = $connection_config['port'] ?? 3306;
        $this->username = $connection_config['username'] ?? 'root';
        $this->password = $connection_config['password'] ?? '';
        $this->database = $connection_config['database'] ?? '';

        if (isset($connection_config['charset'])) {
            $this->charset = $connection_config['charset'];

            if (isset($connection_config['charset_collate'])) {
                $this->charset_collation = $connection_config['charset_collate'];
            } else {
                $this->charset_collation = self::DEFAULT_CHARSET_COLLATE;
            }

        } else {
            $this->charset = self::DEFAULT_CHARSET;
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
                             ?string $alias = null):self
    {
        $this->tables->addTable($key, $tableName, $replacement, $withPrefix, $alias);
        return $this;
    }

    public function getTable($key = null):string|array
    {
        return $this->tables->getTable($key);
    }

    public function setDriver(?string $driver):self
    {
        $this->driver = $driver;
        return $this;
    }

    public function setDatabase(?string $database):self
    {
        $this->database = $database;
        return $this;
    }

    public function setHost(string $host = 'localhost'):self
    {
        $this->hostname = $host;
        return $this;
    }

    public function setPort(mixed $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function setCharset(?string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function setCharsetCollation(?string $charset_collation):self
    {
        $this->charset_collation = $charset_collation;
        return $this;
    }


    public function setUsername(?string $username):self
    {
        $this->username = $username;
        return $this;
    }

    public function setPassword(?string $password):self
    {
        $this->password = $password;
        return $this;
    }

    public function setCredentials(?string $username, ?string $password): self
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
     * @param int $option
     * @param mixed $value
     * @return $this
     */
    public function driverOption(int $option, mixed $value): self
    {
        $this->driverOptions[$option] = $value;
        return $this;
    }

    /**
     * Get DSN (Data Source Name)
     * Применимо к MySQL и PgSQL
     *
     * @return string
     */
    public function getDSN(): string
    {
        $dsnParts = [
            'host=' . $this->hostname,
            'dbname=' . $this->database
        ];

        if ($this->port !== null) {
            $dsnParts[] = 'port=' . $this->port;
        }

        if ($this->charset !== null) {
            $dsnParts[] = 'charset=' . $this->charset;
        }

        return $this->driver . ':' . implode(';', $dsnParts);
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options + $this->driverOptions;
    }

    /**
     * @return Connector
     */
    public function connect()
    {
        return new Connector($this);
    }

    /**
     * @param mixed $value
     * @param bool $as_ms
     * @return $this
     */
    public function setSlowQueryThreshold(mixed $value, bool $as_ms = true):self
    {
        $this->slowQueryThreshold = $as_ms ? $value / 1000 : $value;
        return $this;
    }



}
