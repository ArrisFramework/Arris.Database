<?php

namespace Arris\Database;

use Psr\Log\LoggerInterface;

interface ConfigInterface
{
    public function __construct(array $connection_config = [], ?LoggerInterface $logger = null);

    /**
     * Инициализирует репозиторий таблиц
     *
     * @param string $prefix
     * @param array $tables
     * @param array $havePrefix
     * @param array $haveAlias
     * @return self
     */
    public function initTables(string $prefix = '',
                               array $tables = [],
                               array $havePrefix = [],
                               array $haveAlias = []): self;

    /**
     * Добавляет таблицу в репозиторий. Хелпер.
     *
     * @param string $key
     * @param string|null $tableName
     * @param string|null $replacement
     * @param bool $withPrefix
     * @param string|null $alias
     * @return self
     */
    public function addTable(string $key,
                             ?string $tableName = null,
                             ?string $replacement = null,
                             bool $withPrefix = false,
                             ?string $alias = null):self;

    /**
     * Возвращает таблицу по ключу с учетом всех правил
     * Если ключ не указан - вернет все таблицы
     *
     * @param $key
     * @return string|array
     */
    public function getTable($key = null):string|array;

    public function setDriver(?string $driver):self;
    public function setHost(string $host = 'localhost'):self;
    public function setPort(mixed $port): self;

    public function setUsername(?string $username):self;
    public function setPassword(?string $password):self;
    public function setDatabase(?string $database):self;

    public function setCredentials(?string $username, ?string $password): self;

    public function setCharset(?string $charset): self;
    public function setCharsetCollation(?string $charset_collation):self;

    public function option(int $option, mixed $value): self;
    public function driverOption(int $option, mixed $value): self;

    public function getDSN(): string;
    public function getUsername(): ?string;
    public function getPassword(): ?string;
    public function getOptions(): array;

    public function connect();

    public function setSlowQueryThreshold(mixed $value, bool $as_ms = true):self;
}