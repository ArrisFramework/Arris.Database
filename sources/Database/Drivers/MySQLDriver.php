<?php

namespace Arris\Database\Drivers;

use InvalidArgumentException;
use PDO;

class MySQLDriver extends AbstractDriver
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getDefaultPort(): int
    {
        return 3306;
    }

    public function getDefaultCharset(): string
    {
        return 'utf8mb4';
    }

    public function getDefaultCollation(): ?string
    {
        return 'utf8mb4_unicode_ci';
    }

    public function supportsReplace(): bool
    {
        return true;
    }

    public function supportsInsertSet(): bool
    {
        return true;
    }

    public function getDsn(): string
    {
        $parts = ['host=' . $this->config->hostname];

        $port = $this->config->port ?? $this->getDefaultPort();
        $parts[] = 'port=' . $port;

        if ($this->config->database !== null && $this->config->database !== '') {
            $parts[] = 'dbname=' . $this->config->database;
        }

        $charset = $this->config->charset ?: $this->getDefaultCharset();
        if ($charset !== '') {
            $parts[] = 'charset=' . $charset;
        }

        return 'mysql:' . implode(';', $parts);
    }

    public function configure(PDO $pdo): void
    {
        parent::configure($pdo);

        $charset = $this->config->charset ?: $this->getDefaultCharset();
        if ($charset === '') {
            return;
        }

        $this->assertIdentifierLike($charset, 'charset');

        $sql = 'SET NAMES ' . $charset;

        $collation = $this->config->charset_collation;
        if ($collation !== null && $collation !== '') {
            $this->assertIdentifierLike($collation, 'collation');
            $sql .= ' COLLATE ' . $collation;
        }

        $pdo->exec($sql);
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function assertIdentifierLike(string $value, string $what): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new InvalidArgumentException("Invalid MySQL {$what}: {$value}");
        }
    }
}
