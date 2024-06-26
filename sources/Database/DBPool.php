<?php

namespace Arris\Database;

use Exception;
use PDO;

class DBPool implements DBPoolInterface {

    /**
     * @var int
     */
    private int $pool_max_size = 0;

    /**
     * @var array
     */
    private array $pool = [];

    /**
     * @var string
     */
    private string $db_table;

    /**
     * @var array
     */
    private array $db_columns = [];

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * DBPool constructor
     *
     * @param PDO|DBWrapper $pdo_connection
     * @param int $pool_max_size
     * @param string $db_table
     * @param array $db_columns
     */
    public function __construct($pdo_connection, int $pool_max_size, string $db_table, array $db_columns)
    {
        $this->pdo = $pdo_connection;
        $this->pool_max_size = $pool_max_size;
        $this->db_table = $db_table;
        $this->db_columns = $db_columns;
    }

    /**
     *
     *
     * @param array $dataset
     * @throws Exception
     */
    public function push(array $dataset)
    {
        if ($this->pool_max_size === count($this->pool)) {
            $this->commit();
        }
        $this->pool[] = $dataset;
    }

    /**
     *
     * @throws Exception
     */
    public function commit()
    {
        self::insertRange($this->db_table, $this->pool, $this->db_columns, $this->pdo);
        $this->pool = [];
    }

    /**
     *
     * @param string $tableName
     * @param array $rows
     * @param array $db_columns
     * @param $pdo_connection
     */
    private static function insertRange(string $tableName, array $rows, array $db_columns, $pdo_connection)
    {
        if (empty($rows)) {
            return;
        }

        // Get column list
        $columnList = \array_keys($rows[0]);
        $numColumns = \count($columnList);
        $columnListString = \implode(",", $columnList);

        // Generate pdo param placeholders
        $placeHolders = [];

        foreach($rows as $row) {
            $placeHolders[] = "(?". \str_repeat(",?", \count($db_columns) - 1). ")";
        }

        $placeHolders = \implode(",", $placeHolders);

        // Construct the query
        $sql = "INSERT INTO {$tableName} ( {$columnListString} ) VALUES {$placeHolders}";
        $stmt = $pdo_connection->prepare($sql);

        $j = 1;
        foreach($rows as $row)
        {
            for($i = 0; $i < $numColumns; $i++)
            {
                $stmt->bindParam($j, $row[$columnList[$i]]);
                $j++;
            }
        }

        $stmt->execute();
    }
}

# -eof-
