<?php

namespace Arris\Database;

use PDO;
use PDOStatement;

interface StatementInterface
{
    public function __construct(PDOStatement $statement, string $query, ?Stats $stats);
    public function execute(?array $params = null): bool;
    public function exec(?array $params = null): bool;

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool;
    public function bindParam(
        int|string $param,
        mixed      &$var,
        int        $type = PDO::PARAM_STR,
        int|null   $maxLength = 0,
        mixed      $driverOptions = null
    ): bool;

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed;

    public function fetchAll($mode = PDO::FETCH_BOTH, $fetch_argument = null, ...$args);

    public function fetchColumn(int $column = 0): mixed;
    public function rowCount(): int;

    public function errorCode(): ?string;
    public function errorInfo(): array;

    public function setFetchMode($mode, $className = null, ...$args):bool;

    public function closeCursor(): bool;

    public function debugDumpParams(): ?bool;
}