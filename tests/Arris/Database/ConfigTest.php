<?php

declare(strict_types=1);

namespace Tests\Arris\Database;

use Arris\Database\Config;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testConstructorAppliesCharsetCollation(): void
    {
        $config = new Config([
            'charset' => 'latin1',
            'charset_collation' => 'latin1_swedish_ci',
        ]);

        self::assertSame('latin1', $config->charset);
        self::assertSame('latin1_swedish_ci', $config->charset_collation);
    }

    public function testConstructorSupportsLegacyCharsetCollateKey(): void
    {
        $config = new Config(['charset_collate' => 'latin1_general_ci']);

        self::assertSame('latin1_general_ci', $config->charset_collation);
    }

    public function testNullableSettersDoNotThrow(): void
    {
        $config = new Config();

        $config->setCharsetCollation(null);
        self::assertNull($config->charset_collation);

        $config->setSlowQueryThreshold(null);
        self::assertNull($config->slowQueryThreshold);

        self::assertSame($config, $config->setCharsetCollation(null));
    }

    public function testSlowQueryThresholdConvertsMillisecondsToSeconds(): void
    {
        $config = new Config();

        $config->setSlowQueryThreshold(15);
        self::assertEqualsWithDelta(0.015, $config->slowQueryThreshold, 1e-12);

        $config->setSlowQueryThreshold(2, false);
        self::assertSame(2.0, $config->slowQueryThreshold);
    }

    public function testDriverOptionsOverrideRegularOptions(): void
    {
        $config = new Config();

        $config->option(PDO::ATTR_TIMEOUT, 1);
        $config->driverOption(PDO::ATTR_TIMEOUT, 5);

        self::assertSame(5, $config->getOptions()[PDO::ATTR_TIMEOUT]);
    }

    public function testInitTablesAppliesPrefixAndAlias(): void
    {
        $config = new Config();

        $config->initTables(
            'pre_',
            ['users', 'posts' => 'app_posts'],
            ['users'],
            ['posts' => 'p']
        );

        self::assertSame('pre_users', $config->getTable('users'));
        self::assertSame('app_posts AS p', $config->getTable('posts'));
    }
}
