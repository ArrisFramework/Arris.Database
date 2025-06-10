<?php

namespace Arris\Database;

interface StatsInterface
{
    const ROUND_PRECISION = 8;

    public function __construct(Config $config);
    public function recordQuery(string $type, string $query, ?array $params, float $startTime, bool $isError = false): void;

    public function getQueryCount(): int;
    public function getPreparedQueryCount(): int;
    public function getTotalQueryCount(): int;

    public function getTotalQueryTime(): float;

    public function getQueries(): array;
    public function getSlowQueries():array;

    public function getLastQuery(): ?array;

    public function reset(): void;
}