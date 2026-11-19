<?php

namespace Arris\Database\Helper;

use InvalidArgumentException;
use stdClass;

class QueryMaker
{
    /**
     * Build UPDATE query by dataset for given table.
     *
     * ВНИМАНИЕ: значения WHERE подставляются как есть. Для пользовательских
     * данных используйте buildUpdateQuery().
     *
     * @throws \JsonException
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition, bool $pretty = true): string
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $nl = $pretty ? "\r\n" : '';
        $set = [];

        foreach ($dataset as $index => $value) {
            $expression = self::sqlExpression($value);

            if ($expression !== null) {
                $set[] = "{$nl} {$index} = {$expression}";
                unset($dataset[$index]);
                continue;
            }

            $set[] = "{$nl} {$index} = :{$index}";
            $dataset[$index] = self::cast($value);
        }

        $query = "UPDATE {$table} SET " . \implode(', ', $set);

        if (\is_array($where_condition)) {
            $where_condition = \key($where_condition) . ' = ' . \current($where_condition);
        }

        if (\is_string($where_condition) && !self::hasWhereKeyword($where_condition)) {
            $where_condition = " WHERE {$where_condition} ";
        }

        if ($where_condition === null) {
            $where_condition = '';
        }

        return $query . " {$nl} {$where_condition} ;";
    }

    /**
     * Возвращает SQL INSERT и модифицирует переданный датасет
     * (выражения NOW()/UUID() удаляются, остальные значения кастятся).
     *
     * MySQL-специфичный синтаксис INSERT ... SET. Для переносимого
     * варианта используйте buildInsertQuery().
     *
     * @throws \JsonException
     */
    public static function makeInsertQuery(string $table, &$dataset, bool $pretty = true): string
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $nl = $pretty ? "\r\n" : '';
        $fields = [];

        foreach ($dataset as $index => $value) {
            $expression = self::sqlExpression($value);

            if ($expression !== null) {
                $fields[] = "{$nl} {$index} = {$expression}";
                unset($dataset[$index]);
                continue;
            }

            $fields[] = "{$nl} {$index} = :{$index}";
            $dataset[$index] = self::cast($value);
        }

        return "INSERT INTO {$table} SET " . \implode(', ', $fields) . ' ;';
    }

    /**
     * MySQL-специфичный REPLACE ... SET.
     *
     * @throws \JsonException
     */
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '', bool $pretty = true): string
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $nl = $pretty ? "\r\n" : '';
        $fields = [];

        foreach ($dataset as $index => $value) {
            $expression = self::sqlExpression($value);

            if ($expression !== null) {
                $fields[] = "{$index} = {$expression}";
                unset($dataset[$index]);
                continue;
            }

            $fields[] = " {$index} = :{$index} ";
            $dataset[$index] = self::cast($value);
        }

        return "REPLACE {$table} SET " . \implode(', ', $fields) . " {$nl} {$where} ;";
    }

    /**
     * Переносимый INSERT: named placeholders, значения возвращаются отдельно.
     *
     * @throws \JsonException
     */
    public static function buildInsertQuery(string $table, array &$dataset): stdClass
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $result = new stdClass();
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($dataset as $column => $value) {
            $columns[] = $column;

            $expression = self::sqlExpression($value);
            if ($expression !== null) {
                $placeholders[] = $expression;
                continue;
            }

            $param = ':' . $column;
            $placeholders[] = $param;
            $params[$param] = self::cast($value);
        }

        $result->query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result->params = $params;
        return $result;
    }

    /**
     * Переносимый UPDATE: set- и where-параметры получают разные префиксы.
     *
     * @throws \JsonException
     */
    public static function buildUpdateQuery(string $table, array &$dataset, array &$whereConditions): stdClass
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $result = new stdClass();
        $params = [];

        $setParts = [];
        foreach ($dataset as $column => $value) {
            $expression = self::sqlExpression($value);

            if ($expression !== null) {
                $setParts[] = "$column = $expression";
                continue;
            }

            $param = ':set_' . $column;
            $setParts[] = "$column = $param";
            $params[$param] = self::cast($value);
        }

        $whereParts = [];
        foreach ($whereConditions as $column => $value) {
            $param = ':where_' . $column;
            $whereParts[] = "$column = $param";
            $params[$param] = self::cast($value);
        }

        $result->query = sprintf(
            "UPDATE %s SET %s %s",
            $table,
            implode(', ', $setParts),
            $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : ''
        );

        $result->params = $params;
        return $result;
    }

    /**
     * Применять как:
     *
     * list($update_query, $newdataset) = BuildReplaceQueryMVA($table, $original_dataset, $mva_attributes_list);
     * $update_statement = $sphinx->prepare($update_query);
     * $update_statement->execute($newdataset);
     *
     * @param string $table         -- имя таблицы
     * @param array  $dataset       -- сет данных
     * @param array  $mva_attributes-- имена MVA-атрибутов (вставляются значениями)
     * @return stdClass             -- query + dataset без MVA-атрибутов
     */
    public static function buildReplaceQueryMVA(string $table, array $dataset, array $mva_attributes): stdClass
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException(__METHOD__ . " => Dataset cannot be empty");
        }

        $dataset_keys = \array_keys($dataset);

        $query = "REPLACE INTO {$table} (";
        $query .= \implode(', ', $dataset_keys);
        $query .= " ) VALUES ( ";
        $query .= \implode(', ', \array_map(static function ($i) use ($mva_attributes, $dataset) {
            return \in_array($i, $mva_attributes, true) ? "({$dataset[$i]})" : ":{$i}";
        }, $dataset_keys));
        $query .= " ) ";

        $new_dataset = \array_filter($dataset, static function ($value, $key) use ($mva_attributes) {
            return !\in_array($key, $mva_attributes, true);
        }, ARRAY_FILTER_USE_BOTH);

        $result = new stdClass();
        $result->query = $query;
        $result->dataset = $new_dataset;

        return $result;
    }

    /**
     * Преобразует значение к виду, пригодному для PDO-параметра.
     *
     * null остаётся null (SQL NULL), а не строкой 'NULL'.
     *
     * @throws \JsonException
     */
    public static function cast(mixed $value): mixed
    {
        return match (gettype($value)) {
            'NULL'              => null,
            'boolean'           => (int)$value,
            'double'            => (string)$value,
            'array'             => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'object'            => json_encode((array)$value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'resource'          => null,
            'resource (closed)' => null,
            default             => $value,
        };
    }

    /**
     * NOW()/UUID() распознаются только как строки; для прочих типов — null,
     * чтобы не вызывать trim(array) и подобные TypeError.
     */
    private static function sqlExpression(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $normalized = \strtoupper(\trim($value));

        return \in_array($normalized, ['NOW()', 'UUID()'], true) ? $normalized : null;
    }

    private static function hasWhereKeyword(string $condition): bool
    {
        return \preg_match('/\bWHERE\b/i', $condition) === 1;
    }
}
