<?php

namespace Arris\Database\Drivers;

use PDO;

class PgSQLDriver extends AbstractDriver
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function getDefaultPort(): int
    {
        return 5432;
    }

    public function getDefaultCharset(): string
    {
        return 'UTF8';
    }

    public function getDsn(): string
    {
        $parts = ['host=' . $this->config->hostname];

        $port = $this->config->port ?? $this->getDefaultPort();
        $parts[] = 'port=' . $port;

        if ($this->config->database !== null && $this->config->database !== '') {
            $parts[] = 'dbname=' . $this->config->database;
        }

        // libpq не знает параметра charset: кодировка задаётся через
        // client_encoding / options. DSN оставляем чистым.
        return 'pgsql:' . implode(';', $parts);
    }

    public function configure(PDO $pdo): void
    {
        parent::configure($pdo);

        $charset = $this->normalizeCharset($this->config->charset);
        if ($charset !== '') {
            $pdo->exec('SET client_encoding TO ' . $pdo->quote($charset));
        }
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function normalizeCharset(string $charset): string
    {
        return match (strtolower($charset)) {
            '', 'utf8mb4', 'utf-8', 'utf8' => 'UTF8',
            default => $charset,
        };
    }
}
