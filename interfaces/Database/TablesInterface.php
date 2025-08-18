<?php

namespace Arris\Database;

interface TablesInterface
{
    public function __construct(
        string $prefix = '',
        array $tables = [],
        array $havePrefix = [],
        array $haveAlias = []
    );

    public function addTable(
        string $key,
        ?string $tableName = null,
        ?string $replacement = null,
        bool $withPrefix = false,
        ?string $alias = null
    ): Tables;

}