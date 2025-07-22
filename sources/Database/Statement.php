<?php

namespace Arris\Database;

use PDO;
use PDOException;
use PDOStatement;
use ReturnTypeWillChange;

class Statement extends PDOStatement implements StatementInterface
{
    private PDOStatement $pdo_statement;
    private ConnectorInterface $parent;
    private Stats $stats;

    private string $query;
    private ?array $params = null;

    public function __construct(PDOStatement $statement, string $query, ?Stats $stats)
    {
        $this->pdo_statement = $statement;
        $this->query = $query;
        $this->stats = $stats;
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params;
        $startTime = microtime(true);

        try {
            $result = $this->pdo_statement->execute($params);
            $this->stats->recordQuery('prepared', $this->query, $params, $startTime);
            return $result;

        } catch (PDOException $e) {
            $this->stats->recordQuery('prepared', $this->query, $params, $startTime, true);
            throw $e;
        }
    }

    public function exec(?array $params = null): bool
    {
        return $this->execute($params);
    }

    // Проксируем основные методы PDOStatement
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->pdo_statement->bindValue($param, $value, $type);
    }

    public function bindParam(
        int|string $param,
        mixed      &$var,
        int        $type = PDO::PARAM_STR,
        int|null   $maxLength = 0,
        mixed      $driverOptions = null
    ): bool {
        return $this->pdo_statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->pdo_statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    #[ReturnTypeWillChange]
    public function fetchAll(
        $mode = PDO::FETCH_BOTH,
        $fetch_argument = null,
        ...$args
    )
    {
        return $this->pdo_statement->fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->pdo_statement->fetchColumn($column);
    }

    public function rowCount(): int
    {
        return $this->pdo_statement->rowCount();
    }

    public function errorCode(): ?string
    {
        return $this->pdo_statement->errorCode() ?: null;
    }

    public function errorInfo(): array
    {
        return $this->pdo_statement->errorInfo();
    }

    public function setFetchMode($mode, $className = null, ...$args):bool
    {
        return $this->pdo_statement->setFetchMode($mode, ...$args);
    }

    public function closeCursor(): bool
    {
        return $this->pdo_statement->closeCursor();
    }

    public function debugDumpParams(): ?bool
    {
        return $this->pdo_statement->debugDumpParams();
    }
}