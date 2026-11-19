<?php

namespace Arris\Database\Drivers;

use Arris\Database\Config;
use PDO;

/**
 * Диалект/платформа конкретной СУБД.
 *
 * Драйвер не хранит соединение: он умеет собрать DSN, создать и
 * сконфигурировать PDO, а также предоставляет диалект-специфичные
 * операции (квотирование идентификаторов и т.п.).
 */
interface DriverInterface
{
    public function getName(): string;

    /**
     * DSN без создания соединения.
     */
    public function getDsn(): string;

    /**
     * Создаёт PDO и применяет настройки диалекта.
     */
    public function connect(): PDO;

    /**
     * Применяет настройки диалекта к уже созданному PDO.
     */
    public function configure(PDO $pdo): void;

    /**
     * Квотирует идентификатор (таблицу/колонку), включая dotted-нотацию.
     */
    public function quoteIdentifier(string $identifier): string;

    public function getDefaultPort(): int;

    public function getDefaultCharset(): string;

    public function getDefaultCollation(): ?string;

    /**
     * Поддерживает ли диалект REPLACE INTO.
     */
    public function supportsReplace(): bool;

    /**
     * Поддерживает ли диалект MySQL-синтаксис INSERT ... SET.
     */
    public function supportsInsertSet(): bool;

    public function lastInsertId(PDO $pdo, ?string $name = null): string|false;
}
