<?php

namespace Arris\Database\Drivers;

use InvalidArgumentException;
use PDO;

class SqliteDriver extends AbstractDriver
{
    public function getName(): string
    {
        return 'sqlite';
    }

    public function getDefaultPort(): int
    {
        return 0;
    }

    public function getDefaultCharset(): string
    {
        return 'UTF-8';
    }

    public function supportsReplace(): bool
    {
        return true;
    }

    public function getDsn(): string
    {
        $path = $this->resolvePath();

        if ($path === ':memory:') {
            return 'sqlite::memory:';
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("SQLite directory does not exist: {$directory}");
        }

        return 'sqlite:' . $path;
    }

    public function configure(PDO $pdo): void
    {
        parent::configure($pdo);
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * SQLite не использует host/port. Основной путь — Config::$database,
     * но для обратной совместимости допускаем hostname, если он не
     * является обычным сетевым адресом.
     */
    private function resolvePath(): string
    {
        $path = $this->config->database;

        if ($path === null || $path === '') {
            $host = $this->config->hostname;
            if ($host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
                $path = $host;
            }
        }

        if ($path === null || $path === '') {
            throw new InvalidArgumentException(
                'SQLite database path is required: set Config::$database (or hostname)'
            );
        }

        return $path;
    }
}
