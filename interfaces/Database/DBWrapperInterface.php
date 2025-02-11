<?php

namespace Arris\Database;

use Psr\Log\LoggerInterface;

/**
 * @method int|false            exec(string $statement = '')
 * @method bool                 beginTransaction()
 * @method bool                 commit()
 * @method bool                 rollback()
 * @method bool                 inTransaction()
 *
 * @method mixed                getAttribute($attribute = '')
 * @method bool                 setAttribute($attribute, $value)
 *
 * @method string|false         lastInsertId($name = null)
 *
 * @method string               errorCode()
 * @method array                errorInfo()
 *
 * PDOStatement|false           _prepare($query = '', array $options = [])
 * PDOStatement|false           _query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args)
 */
interface DBWrapperInterface
{
    /**
     * @param array|DBConfig $connection_config
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct($connection_config, array $options = [], LoggerInterface $logger = null);


    /**
     * @param string $query
     * @param int $fetchMode = null
     *
     * @return \Arris\Database\PDOStatement
     */
    public function query(/*string $query, int $fetchMode = null*/);

    /**
     * @param string $query
     * @param array $options = []
     *
     * @return \Arris\Database\PDOStatement
     */
    public function prepare(/*string $query, array $options = []*/);


    /**
     * @return string
     */
    public function getLastQueryTime(): string;

    /**
     *
     *
     * @return array
     */
    public function getLastState():array;

    /**
     * @param int $precision
     * @return array{total_queries: int, total_time: string}
     */
    public function getStats(int $precision = 6):array;
}