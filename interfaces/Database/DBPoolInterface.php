<?php

namespace Arris\Database;

use Exception;
use PDO;

interface DBPoolInterface
{
    /**
     * DBPool constructor
     *
     * @param PDO|DBWrapper $pdo_connection
     * @param int $pool_max_size
     * @param string $db_table
     * @param array $db_columns
     */
    public function __construct($pdo_connection, int $pool_max_size, string $db_table, array $db_columns);

    /**
     *
     * @param array $dataset
     * @throws Exception
     */
    public function push(array $dataset);

    /**
     *
     * @throws Exception
     */
    public function commit();
}