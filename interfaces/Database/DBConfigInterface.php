<?php

namespace Arris\Database;

use Psr\Log\LoggerInterface;

interface DBConfigInterface
{
    /**
     *
     * @param array $connection_config
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $connection_config, array $options = [], LoggerInterface $logger = null);

    /**
     * @param $time
     * @param int $decimals
     * @param string $decimal_separator
     * @param string $thousands_separator
     * @return string
     */
    public function formatTime($time = 0, int $decimals = 6, string $decimal_separator = '.', string $thousands_separator = ''): string;

    public function setDriver(string $driver = 'mysql'):self;

    public function setHost(string $host = '127.0.0.1'):self;

    public function setPort($port = 3306):self;

    public function setDatabase(string $database = ''):self;

    public function setUser(string $user = 'root'):self;

    public function setPassword(string $password = ''):self;

    public function setCharset(string $charset = DBConfig::DEFAULT_CHARSET):self;

    public function setCollate(string $collate = DBConfig::DEFAULT_CHARSET_COLLATE):self;

}