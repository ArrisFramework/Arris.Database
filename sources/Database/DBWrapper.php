<?php

namespace Arris\Database;

use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
class DBWrapper implements DBWrapperInterface
{
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var PDO
     */
    public $pdo;

    public array $last_state = [
        'method'    =>  '',
        'query'     =>  '',
        'time'      =>  0,
        'comment'   =>  ''
    ];

    /**
     * @var DBConfig
     */
    private DBConfig $config;

    /**
     * @param array|DBConfig $connection_config
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct($connection_config, array $options = [], LoggerInterface $logger = null)
    {
        if (is_array($connection_config)) {
            $this->config = new DBConfig($connection_config, $options, $logger);
        } elseif ($connection_config instanceof DBConfig) {
            $this->config = $connection_config;
        } else {
            throw new \RuntimeException("Unsupported connection config given. Required array or DBConfig instance, given " . gettype($connection_config));
        }

        $this->logger = \is_null($logger) ? new NullLogger() : $logger;

        if ($this->config->is_lazy === false) {
            $this->initConnection();
        }
    }

    /**
     * Init connection
     *
     * @return void
     */
    private function initConnection()
    {
        switch ($this->config->driver) {
            case 'mysql': {
                $dsl = \sprintf("mysql:host=%s;port=%s;dbname=%s",
                    $this->config->hostname,
                    $this->config->port,
                    $this->config->database);

                $this->pdo = new \PDO($dsl, $this->config->username, $this->config->password);

                if ($this->config->charset) {
                    $sql_collate = "SET NAMES {$this->config->charset}";
                    if ($this->config->charset_collate) {
                        $sql_collate .= " COLLATE {$this->config->charset_collate}";
                    }
                    $this->pdo->exec($sql_collate);
                }

                break;
            }
            case 'pgsql': {
                $dsl = \sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
                    $this->config->hostname,
                    $this->config->port,
                    $this->config->database,
                    $this->config->username,
                    $this->config->password);

                $this->pdo = new \PDO($dsl);

                if ($this->config->charset) {
                    $sql_collate = "SET NAMES {$this->config->charset}";
                    $this->pdo->exec($sql_collate);
                }

                break;
            }
            case 'sqlite': {
                $dsl = \sprintf("sqlite:%s", realpath($this->config->hostname));
                $this->pdo = new \PDO($dsl);

                break;
            }
            default: {
                throw new \RuntimeException('Unknown database driver : ' . $this->config->driver);
            }
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    private function ensureConnection()
    {
        if (empty($this->pdo)) {
            $this->initConnection();
        }
    }

    public function __call($function, $args)
    {
        $this->ensureConnection();

        $this->last_state['method'] = $function;

        /*
        // старый код, проверяет вхождение имени функции в массив. Рационален, если > 1 элемента в массиве
        if (in_array(strtolower($function), [ 'prepare' ])) {
            $this->updateLastState($args);
        }
        */
        if (\strtolower($function) == 'prepare') {
            $this->updateLastState($args);
        }

        // invoke the original method & calc time cost
        $before_call = \microtime(true);
        $result = \call_user_func_array([$this->pdo, $function], $args);
        $after_call = \microtime(true);

        $this->config->total_time += $this->last_state['time'] = $after_call - $before_call;
        $this->config->total_queries++;

        if ($this->last_state['time'] >= $this->config->slow_query_threshold && $this->config->slow_query_threshold > 0) {
            $debug = \debug_backtrace();
            $debug = $debug[1] ?? $debug[0];
            $caller = \sprintf("%s%s%s", ($debug['class'] ?? ''), ($debug['type'] ?? ''), ($debug['function'] ?? ''));

            $this->logger->info("PDO::{$function}() slow: ", [
                $this->last_state['time'],
                $caller,
                ((PHP_SAPI === "cli") ? __FILE__ : ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])),
                $args
            ]);
        }

        return $result;
    }

    /**
     * @param string $query
     * @param int $fetchMode = null
     *
     * @return \Arris\Database\PDOStatement
     */
    public function query()
    {
        $this->ensureConnection();

        $args = \func_get_args();

        $this->updateLastState($args);

        $time_start = \microtime(true);
        $result = \call_user_func_array([$this->pdo, 'query'], $args);
        $time_consumed = \microtime(true) - $time_start;

        if ($time_consumed >= $this->config->slow_query_threshold && $this->config->slow_query_threshold > 0) {
            $debug = \debug_backtrace();
            $debug = $debug[1] ?? $debug[0];
            $caller = \sprintf("%s%s%s", ($debug['class'] ?? ''), ($debug['type'] ?? ''), ($debug['function'] ?? ''));
            $this->config->logger->info("PDO::query() slow: ", [
                $time_consumed,
                $caller,
                ((PHP_SAPI === "cli") ? __FILE__ : ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])),
                $args
            ]);
        }
        $this->config->total_queries++;

        return new \Arris\Database\PDOStatement($result, $this->config);
    }

    /**
     * @param string $query
     * @param array $options = []
     *
     * @return \Arris\Database\PDOStatement
     */
    public function prepare()
    {
        $this->ensureConnection();

        $args = \func_get_args();

        $this->updateLastState($args);
        $result = \call_user_func_array([$this->pdo, 'prepare'], $args);
        return new \Arris\Database\PDOStatement($result, $this->config);
    }

    /**
     * @inheritDoc
     */
    public function getLastQueryTime(): string
    {
        return $this->config->formatTime($this->last_state['time']);
    }

    /**
     * @inheritDoc
     */
    public function getLastState():array
    {
        $result = $this->last_state;
        $result['time'] = $this->config->formatTime($result['time']);
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getStats(int $precision = 6):array
    {
        /*$object = (new class() implements ArrayAccess {
            public int    $total_queries;
            public string  $total_time;

            #[ReturnTypeWillChange] public function offsetExists($offset): bool { return isset($this->$offset); }
            #[ReturnTypeWillChange] public function offsetGet($offset) { return $this->$offset ?? null; }
            #[ReturnTypeWillChange] public function offsetSet($offset, $value):void { $this->$offset = $value; }
            #[ReturnTypeWillChange] public function offsetUnset($offset):void { unset($this->$offset);}
        });
        $object->total_queries = $this->config->total_queries;
        $object->total_time = $this->config->formatTime($this->config->total_time, $precision);

        return $object;*/

        return [
            'total_queries' =>  $this->config->total_queries,
            'total_time'    =>  $this->config->formatTime($this->config->total_time, $precision)
        ];
    }

    private function updateLastState($args)
    {
        $this->last_state['query'] = $args[0];
        if (\preg_match('#^\/\*\s(.+)\s\*\/#', $args[0], $matches)) {
            $this->last_state['comment'] = $matches[0];
        }
    }
}

