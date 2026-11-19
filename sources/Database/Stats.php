<?php

namespace Arris\Database;

class Stats
{
    public const ROUND_PRECISION = 8;

    private ?float $slowQueryThreshold;
    private float $totalQueryTime = 0.0;
    private int $queryCount = 0;
    private int $preparedQueryCount = 0;
    private int $maxStoredQueries;

    private array $queries = [];
    private array $slowQueries = [];

    private float $initTimestamp;

    public function __construct(private readonly Config $config)
    {
        $this->slowQueryThreshold = $config->slowQueryThreshold;
        $this->maxStoredQueries = max(0, $config->maxStoredQueries);
        $this->initTimestamp = microtime(true);
    }

    public function recordQuery(string $type, string $query, ?array $params, float $startTime, bool $isError = false): void
    {
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        if ($type === 'prepared') {
            $this->preparedQueryCount++;
        } else {
            $this->queryCount++;
        }

        $this->totalQueryTime += $duration;

        $backtrace = [];

        if ($this->config->collectBacktrace) {
            $trace = debug_backtrace(limit: 2);
            if (count($trace) > 1) {
                $trace = $trace[1];
            }

            $backtrace = [
                'file'     => $trace['file'] ?? __FILE__,
                'line'     => $trace['line'] ?? __LINE__,
                'function' => $trace['function'] ?? __METHOD__,
            ];
        }

        $queryData = [
            'state'     => $isError ? 'ERROR' : 'SUCCESS',
            'type'      => $type,
            'query'     => $query,
            'params'    => $params ?? [],
            'duration'  => $duration,
            'timestamp' => $endTime - $this->initTimestamp,
            'backtrace' => $backtrace,
        ];

        $this->queries[] = $queryData;
        $this->trim($this->queries);

        if ($this->slowQueryThreshold !== null && $duration >= $this->slowQueryThreshold) {
            $this->slowQueries[] = $queryData;
            $this->trim($this->slowQueries);
        }
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getPreparedQueryCount(): int
    {
        return $this->preparedQueryCount;
    }

    public function getTotalQueryCount(): int
    {
        return $this->queryCount + $this->preparedQueryCount;
    }

    public function getTotalQueryTime(): float
    {
        return $this->totalQueryTime;
    }

    public function getQueries(): array
    {
        return $this->formatRows($this->queries);
    }

    public function getSlowQueries(): array
    {
        return $this->formatRows($this->slowQueries);
    }

    public function getLastQuery(): ?array
    {
        $row = end($this->queries) ?: null;

        if ($row !== null && array_key_exists('duration', $row)) {
            $row['duration'] = number_format($row['duration'], self::ROUND_PRECISION);
        }

        return $row;
    }

    public function reset(): void
    {
        $this->queryCount = 0;
        $this->preparedQueryCount = 0;
        $this->totalQueryTime = 0.0;
        $this->queries = [];
        $this->slowQueries = [];
        $this->slowQueryThreshold = $this->config->slowQueryThreshold;
        $this->maxStoredQueries = max(0, $this->config->maxStoredQueries);
        $this->initTimestamp = microtime(true);
    }

    public static function formatTime($time = 0, int $decimals = 6, string $decimal_separator = '.', string $thousands_separator = ''): string
    {
        return \number_format($time, $decimals, $decimal_separator, $thousands_separator);
    }

    private function formatRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            $row['duration'] = number_format($row['duration'], self::ROUND_PRECISION);
            return $row;
        }, $rows);
    }

    private function trim(array &$rows): void
    {
        if ($this->maxStoredQueries > 0 && count($rows) > $this->maxStoredQueries) {
            array_shift($rows);
        }
    }
}
