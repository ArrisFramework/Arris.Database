<?php

namespace Arris\Database;

use stdClass;

interface QueryMakerInterface
{
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition, bool $pretty = true):string;
    public static function makeInsertQuery(string $table, &$dataset, bool $pretty = true):string;
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '', bool $pretty = true): string;

    public static function buildInsertQuery(string $table, array &$dataset): stdClass;
    public static function buildUpdateQuery(string $table, array &$dataset, array &$whereConditions): stdClass;

    public static function buildReplaceQueryMVA(string $table, array $dataset, array $mva_attributes):array;

}