<?php

namespace Arris\Database;

interface DBHelperInterface
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
    public static function makeInsertQuery(string $table, &$dataset, bool $pretty = true):string;

    /**
     * Build UPDATE query by dataset for given table
     *
     * @param string $table
     * @param $dataset
     * @param $where_condition
     * @param bool $pretty
     * @return bool|string
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition, bool $pretty = true):string;

    /**
     * @param string $table
     * @param array $dataset
     * @param string $where
     * @param bool $pretty
     * @return false|string
     */
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '', bool $pretty = true);

    /**
     * Не поддерживает NOW() и UUID() в запросах
     *
     * @param string $table
     * @param array $dataset
     * @return string
     */
    public static function buildReplaceQuery(string $table, array $dataset):string;

    /**
     * @param string $table
     * @param array $dataset
     * @param null $where_condition - строка условия без WHERE ('x=0 AND y=0' ) или массив условий ['x=0', 'y=0']
     * @return string
     */
    public static function buildUpdateQuery(string $table, array $dataset = [], $where_condition = null):string;

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
    public static function buildReplaceQueryMVA(string $table, array $dataset, array $mva_attributes):array;

}