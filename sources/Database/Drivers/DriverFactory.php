<?php

namespace Arris\Database\Drivers;

use Arris\Database\Config;
use InvalidArgumentException;

final class DriverFactory
{
    /**
     * @return class-string<DriverInterface>
     */
    private const DRIVERS = [
        Config::DRIVER_MYSQL  => MySQLDriver::class,
        Config::DRIVER_PGSQL  => PgSQLDriver::class,
        Config::DRIVER_SQLITE => SqliteDriver::class,
    ];

    public static function make(Config $config): DriverInterface
    {
        $class = self::DRIVERS[$config->driver] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(
                "Unsupported database driver: {$config->driver}"
            );
        }

        return new $class($config);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::DRIVERS);
    }
}
