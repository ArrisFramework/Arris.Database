<?php

namespace Arris\Database\Helper;

use Arris\Database\QueryMakerInterface;
use InvalidArgumentException;
use stdClass;

class QueryMaker implements QueryMakerInterface
{
    /**
     * Build UPDATE query by dataset for given table
     *
     * @param string $table
     * @param $dataset
     * @param $where_condition
     * @param bool $pretty
     * @return string
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition, bool $pretty = true):string
    {
        if (empty($dataset)) {
            return false;
        }

        $set = [];
        $pretty = $pretty ? "\r\n" : '';

        $query = "UPDATE {$table} SET";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $set[] = "{$pretty} {$index} = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $set[] = "{$pretty} {$index} = UUID()";
                unset($dataset[$index]);
                continue;
            }

            $set[] = "{$pretty} {$index} = :{$index}";
        }

        $query .= \implode(', ', $set);

        if (\is_array($where_condition)) {
            $where_condition = \key($where_condition) . ' = ' . \current($where_condition);
        }

        if (\is_string($where_condition ) && !\strpos($where_condition, 'WHERE')) {
            $where_condition = " WHERE {$where_condition} ";
        }

        if (\is_null($where_condition)) {
            $where_condition = '';
        }

        $query .= " {$pretty} {$where_condition} ;";

        return $query;
    }

    public static function makeInsertQuery(string $table, &$dataset, bool $pretty = true):string
    {
        if (empty($dataset)) {
            return "INSERT INTO {$table} () VALUES (); ";
        }

        $pretty = $pretty ? "\r\n" : '';

        $insert_fields = [];

        $query = "INSERT INTO {$table} SET ";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $insert_fields[] = "{$pretty} {$index} = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $insert_fields[] = "{$pretty} {$index} = UUID()";
                unset($dataset[$index]);
                continue;
            }

            $insert_fields[] = "{$pretty} {$index} = :{$index}";
        }

        $query .= \implode(', ', $insert_fields) . ' ;';

        return $query;
    }

    /**
     * @param string $table
     * @param array $dataset
     * @param string $where
     * @param bool $pretty
     * @return string
     */
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '', bool $pretty = true): string
    {
        if (empty($dataset)) {
            return false;
        }
        $fields = [];

        $pretty = $pretty ? "\r\n" : '';

        $query = "REPLACE {$table} SET ";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $fields[] = "{$index} = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $fields[] = " {$index} = UUID() ";
                unset($dataset[$index]);
                continue;
            }

            $fields[] = " {$index} = :{$index} ";
        }

        $query .= \implode(', ', $fields);

        $query .= " {$pretty} {$where} ;";

        return $query;
    }

    public static function buildInsertQuery(string $table, array &$dataset): stdClass
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException("Dataset cannot be empty");
        }

        $result = new stdClass();
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($dataset as $column => $value) {
            $param = ':' . $column;
            $columns[] = $column;
            $placeholders[] = $param;
            $params[$param] = $value;
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

    public static function buildUpdateQuery(string $table, array &$dataset, array &$whereConditions): stdClass
    {
        if (empty($dataset)) {
            throw new InvalidArgumentException("Dataset cannot be empty");
        }

        $result = new stdClass();
        $params = [];

        // Формируем SET часть
        $setParts = [];
        foreach ($dataset as $column => $value) {
            $param = ':set_' . $column;
            $setParts[] = "$column = $param";
            $params[$param] = $value;
        }

        // Формируем WHERE часть
        $whereParts = [];
        foreach ($whereConditions as $column => $value) {
            $param = ':where_' . $column;
            $whereParts[] = "$column = $param";
            $params[$param] = $value;
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
     *
     * @param string $table         -- имя таблицы
     * @param array $dataset        -- сет данных
     * @param array $mva_attributes -- массив с именами ключей MVA-атрибутов (они вставятся как значения, а не как placeholder-ы)
     * @return stdClass             -- Класс, query -> запрос, dataset -> сет данных, очищенный от MVA-атрибутов.
     */
    public static function buildReplaceQueryMVA(string $table, array $dataset, array $mva_attributes):stdClass
    {
        $query = "REPLACE INTO {$table} (";

        $dataset_keys = \array_keys($dataset);

        $query .= \implode(', ', \array_map( static function ($i){
            return "{$i}";
        }, $dataset_keys));

        $query .= " ) VALUES ( ";

        $query .= \implode(', ', \array_map(static function ($i) use ($mva_attributes, $dataset){
            return \in_array($i, $mva_attributes) ? "({$dataset[$i]})" : ":{$i}";
        }, $dataset_keys));

        $query .= " ) ";

        $new_dataset = \array_filter($dataset, static function ($value, $key) use ($mva_attributes) {
            return !\in_array($key, $mva_attributes);
        }, ARRAY_FILTER_USE_BOTH);

        $result = new stdClass();
        $result->query = $query;
        $result->dataset = $new_dataset;

        return $result;
    }



}