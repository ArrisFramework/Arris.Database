<?php

namespace Arris\Database;

use PDO;
use PDOStatement;

interface ConnectorInterface
{
    public function __construct(Config $config);

    public function prepare(string $query, array $options = []): PDOStatement|false;
    public function query($query, $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetchModeArgs): PDOStatement|false;
    public function exec(string $statement): int|false;

    public function lastInsertId(?string $name = null): string|false;

    public function beginTransaction(): bool;
    public function inTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;

    public function errorCode(): ?string;
    public function errorInfo(): array;

    public function setAttribute(int $attribute, mixed $value): bool;
    public function getAttribute(int $attribute): mixed;
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false;

    public function stats():Stats;
}