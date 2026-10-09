<?php

namespace Arris\Database;

interface TablesInterface
{
    public function __construct(
        string $prefix = '',
        array $tables = []
    );

    public function addTable(
        string $key,
        ?string $tableName = null,
        ?string $replacement = null,
        bool $withPrefix = false,
        ?string $alias = null
    ): Tables;

    public function getTable(?string $key = null):array|string;

}