<?php

namespace Arris\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDOStatement с учётом статистики.
 *
 * Экземпляры создаёт сам PDO (PDO::ATTR_STATEMENT_CLASS), поэтому
 * внутреннее состояние statement инициализировано корректно и все
 * унаследованные методы (columnCount, fetchObject, getColumnMeta,
 * nextRowset, bindColumn, getIterator, get/setAttribute и т.д.)
 * работают без ручного проксирования.
 */
class Statement extends PDOStatement
{
    /**
     * Значения, заданные через bindValue()/bindParam(), для статистики.
     *
     * @var array<int|string, mixed>
     */
    private array $boundValues = [];

    protected function __construct(private readonly Stats $stats)
    {
    }

    public function execute(?array $params = null): bool
    {
        $startTime = microtime(true);
        $effectiveParams = $params ?? ($this->boundValues !== [] ? $this->boundValues : null);

        try {
            $result = parent::execute($params);
        } catch (PDOException $e) {
            $this->stats->recordQuery('prepared', (string)$this->queryString, $effectiveParams, $startTime, true);
            throw $e;
        }

        $this->stats->recordQuery('prepared', (string)$this->queryString, $effectiveParams, $startTime);

        return $result;
    }

    /**
     * Удобный алиас (не является методом PDOStatement).
     */
    public function exec(?array $params = null): bool
    {
        return $this->execute($params);
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }
}
