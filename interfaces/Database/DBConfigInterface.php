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

}