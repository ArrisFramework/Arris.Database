<?php

namespace Arris\Database;

class DBHelper implements DBHelperInterface
{
    /**
     * Строит INSERT-запрос на основе массива данных для указанной таблицы.
     * В массиве допустима конструкция 'key' => 'NOW()'
     * В этом случае она будет добавлена в запрос и удалена из набора данных (он пере).
     *
     * @param $table    -- таблица
     * @param $dataset      -- передается по ссылке, мутабелен
     * @return string       -- результирующая строка запроса
     */
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
                $insert_fields[] = "{$pretty} `{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $insert_fields[] = "\r\n {$index} = UUID()";
                unset($dataset[$index]);
                continue;
            }

            $insert_fields[] = "{$pretty} `{$index}` = :{$index}";
        }

        $query .= \implode(', ', $insert_fields) . ' ;';

        return $query;
    }

    /**
     * Build UPDATE query by dataset for given table
     *
     * @param string $table
     * @param $dataset
     * @param $where_condition
     * @param bool $pretty
     * @return bool|string
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition, bool $pretty = true):string
    {
        if (empty($dataset)) {
            return false;
        }

        $set = [];
        $pretty = $pretty ? "\r\n" : '';

        $query = "UPDATE `{$table}` SET";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $set[] = "{$pretty} `{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $set[] = "{$pretty} {$index} = UUID()";
                unset($dataset[$index]);
                continue;
            }

            $set[] = "{$pretty} `{$index}` = :{$index}";
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

    /**
     * @param string $table
     * @param array $dataset
     * @param string $where
     * @param bool $pretty
     * @return false|string
     */
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '', bool $pretty = true)
    {
        if (empty($dataset)) {
            return false;
        }
        $fields = [];

        $pretty = $pretty ? "\r\n" : '';

        $query = "REPLACE `{$table}` SET ";

        foreach ($dataset as $index => $value) {
            if (\strtoupper(\trim($value)) === 'NOW()') {
                $fields[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            if (\strtoupper(\trim($value)) === 'UUID()') {
                $fields[] = " {$index} = UUID() ";
                unset($dataset[$index]);
                continue;
            }

            $fields[] = " `{$index}` = :{$index} ";
        }

        $query .= \implode(', ', $fields);

        $query .= " {$pretty} {$where} ;";

        return $query;
    }

    /**
     * Не поддерживает NOW() и UUID() в запросах
     *
     * @param string $table
     * @param array $dataset
     * @return string
     */
    public static function buildReplaceQuery(string $table, array $dataset):string
    {
        $dataset_keys = \array_keys($dataset);

        $query = "REPLACE INTO `{$table}` (";

        $query.= \implode(', ', \array_map(function ($i){
            return "`{$i}`";
        }, $dataset_keys));

        $query.= " ) VALUES ( ";

        $query.= \implode(', ', \array_map(function ($i){
            return ":{$i}";
        }, $dataset_keys));

        $query.= " ) ";

        return $query;
    }

    /**
     * @param string $table
     * @param array $dataset
     * @param null $where_condition - строка условия без WHERE ('x=0 AND y=0' ) или массив условий ['x=0', 'y=0']
     * @return string
     */
    public static function buildUpdateQuery(string $table, array $dataset = [], $where_condition = null):string
    {
        $query = "UPDATE `{$table}` SET ";

        $query.= \implode(', ', \array_map(function ($key, $value){
            return "\r\n`{$key}` = :{$key}";
        }, \array_keys($dataset), $dataset));

        $where
            = !empty($where_condition)
            ? "WHERE " . $where_condition
            : "";

        $query .= "\r\n {$where} ;";

        return $query;
    }

    /**
     * Применять как:
     *
     * list($update_query, $newdataset) = BuildReplaceQueryMVA($table, $original_dataset, $mva_attributes_list);
     * $update_statement = $sphinx->prepare($update_query);
     * $update_statement->execute($newdataset);
     *
     *
     * @param string $table             -- имя таблицы
     * @param array $dataset            -- сет данных.
     * @param array $mva_attributes     -- массив с именами ключей MVA-атрибутов (они вставятся как значения, а не как placeholder-ы)
     * @return array                    -- возвращает массив с двумя значениями. Первый ключ - запрос, сет данных, очищенный от MVA-атрибутов.
     */
    public static function buildReplaceQueryMVA(string $table, array $dataset, array $mva_attributes):array
    {
        $query = "REPLACE INTO `{$table}` (";

        $dataset_keys = \array_keys($dataset);

        $query .= \implode(', ', \array_map( static function ($i){
            return "`{$i}`";
        }, $dataset_keys));

        $query .= " ) VALUES ( ";

        $query .= \implode(', ', \array_map(static function ($i) use ($mva_attributes, $dataset){
            return \in_array($i, $mva_attributes) ? "({$dataset[$i]})" : ":{$i}";
        }, $dataset_keys));

        $query .= " ) ";

        $new_dataset = \array_filter($dataset, static function ($value, $key) use ($mva_attributes) {
            return !\in_array($key, $mva_attributes);
        }, ARRAY_FILTER_USE_BOTH);

        return [
            $query, $new_dataset
        ];
    }

}