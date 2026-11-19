<?php

namespace Arris\Database\Drivers;

use Arris\Database\Config;
use PDO;

abstract class AbstractDriver implements DriverInterface
{
    public function __construct(protected readonly Config $config)
    {
    }

    public function getDefaultCollation(): ?string
    {
        return null;
    }

    public function supportsReplace(): bool
    {
        return false;
    }

    public function supportsInsertSet(): bool
    {
        return false;
    }

    public function connect(): PDO
    {
        $pdo = new PDO(
            $this->getDsn(),
            $this->config->getUsername(),
            $this->config->getPassword(),
            $this->config->getOptions()
        );

        $this->configure($pdo);

        return $pdo;
    }

    /**
     * Базовые атрибуты соединения. Пользовательские опции не перетираются.
     */
    public function configure(PDO $pdo): void
    {
        $options = $this->config->getOptions();

        if (!array_key_exists(PDO::ATTR_ERRMODE, $options)) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        if (!array_key_exists(PDO::ATTR_DEFAULT_FETCH_MODE, $options)) {
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        if (!array_key_exists(PDO::ATTR_CASE, $options)) {
            $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '' || $identifier === '*') {
            return $identifier;
        }

        $parts = explode('.', $identifier);

        foreach ($parts as &$part) {
            if ($part === '*' || $part === '' || $this->isQuoted($part)) {
                continue;
            }
            $part = $this->wrapIdentifier($part);
        }
        unset($part);

        return implode('.', $parts);
    }

    protected function isQuoted(string $identifier): bool
    {
        $first = $identifier[0] ?? '';
        $last = $identifier[strlen($identifier) - 1] ?? '';

        return ($first === '"' && $last === '"')
            || ($first === '`' && $last === '`');
    }

    abstract protected function wrapIdentifier(string $identifier): string;

    public function lastInsertId(PDO $pdo, ?string $name = null): string|false
    {
        return $pdo->lastInsertId($name);
    }
}
