<?php

declare(strict_types=1);

namespace Tests\Arris\Database;

use Arris\Database\Tables;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TablesTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Конструктор
    // ---------------------------------------------------------------------

    public function testConstructorWithScalarList(): void
    {
        $t = new Tables('', ['users', 'posts']);

        self::assertTrue(isset($t['users']));
        self::assertTrue(isset($t['posts']));
        self::assertSame('users', $t['users']);
        self::assertSame('posts', $t['posts']);
    }

    public function testConstructorWithAssocArray(): void
    {
        $t = new Tables('', [
            'users' => 'app_users',
            'posts' => 'app_posts',
        ]);

        self::assertSame('app_users', $t['users']);
        self::assertSame('app_posts', $t['posts']);
    }

    public function testConstructorWithEmptyTables(): void
    {
        $t = new Tables('pre_');

        self::assertSame([], $t->getTable());
    }

    public function testConstructorMixedArray(): void
    {
        $t = new Tables('', [
            'users',
            'posts' => 'app_posts',
        ]);

        self::assertSame('users', $t['users']);
        self::assertSame('app_posts', $t['posts']);
    }

    // ---------------------------------------------------------------------
    // addTable
    // ---------------------------------------------------------------------

    public function testAddTableDefaults(): void
    {
        $t = new Tables('');
        $ret = $t->addTable('users');

        self::assertSame($t, $ret, 'addTable must be fluent');
        self::assertSame('users', $t['users']);
    }

    public function testAddTableWithExplicitName(): void
    {
        $t = new Tables('');
        $t->addTable('u', 'app_users');

        self::assertSame('app_users', $t['u']);
    }

    public function testAddTableWithPrefix(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', null, true);

        self::assertSame('cms_users', $t['users']);
    }

    public function testAddTableWithPrefixAndDifferentName(): void
    {
        $t = new Tables('cms_');
        $t->addTable('u', 'users', null, true);

        self::assertSame('cms_users', $t['u']);
    }

    public function testAddTableWithoutPrefixKeepsName(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', null, false);

        self::assertSame('users', $t['users']);
    }

    public function testAddTableWithReplacementIgnoresPrefix(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', 'custom_users', true);

        self::assertSame('custom_users', $t['users']);
    }

    public function testAddTableWithAlias(): void
    {
        $t = new Tables('');
        $t->addTable('users', 'users', null, false, 'u');

        self::assertSame('users AS u', $t['users']);
    }

    public function testAddTableWithEverything(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', null, true, 'u');

        self::assertSame('cms_users AS u', $t['users']);
    }

    public function testAddTableReplacementWithAlias(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', 'custom_users', true, 'u');

        self::assertSame('custom_users AS u', $t['users']);
    }

    public function testAddTableEmptyNameFallsBackToKey(): void
    {
        $t = new Tables('');
        $t->addTable('users', '');

        self::assertSame('users', $t['users']);
    }

    public function testAddTableNullNameFallsBackToKey(): void
    {
        $t = new Tables('');
        $t->addTable('users', null);

        self::assertSame('users', $t['users']);
    }

    public function testAddTableOverwritesPrevious(): void
    {
        $t = new Tables('');
        $t->addTable('users', 'old_users', null, false, 'x');
        $t->addTable('users', 'new_users');

        self::assertSame('new_users', $t['users']);
    }

    // ---------------------------------------------------------------------
    // getTable
    // ---------------------------------------------------------------------

    public function testGetTableAll(): void
    {
        $t = new Tables('cms_', [
            'users' => 'users',
            'posts' => 'posts',
        ]);
        $t->addTable('users', 'users', null, true);
        $t->addTable('posts', 'posts', null, true, 'p');

        self::assertSame(
            ['cms_users', 'cms_posts AS p'],
            $t->getTable()
        );
    }

    public function testGetTableByKey(): void
    {
        $t = new Tables('');
        $t->addTable('users', 'users', null, false, 'u');

        self::assertSame('users AS u', $t->getTable('users'));
    }

    public function testGetTableWildcard(): void
    {
        $t = new Tables('', ['users', 'posts']);

        self::assertSame(['users', 'posts'], $t->getTable('*'));
    }

    public function testGetTableNull(): void
    {
        $t = new Tables('', ['users']);

        self::assertSame(['users'], $t->getTable(null));
    }

    public function testGetTableUnknownKeyThrows(): void
    {
        $t = new Tables('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Table 'nope' not found.");

        $t->getTable('nope');
    }

    // ---------------------------------------------------------------------
    // ArrayAccess
    // ---------------------------------------------------------------------

    public function testOffsetExists(): void
    {
        $t = new Tables('', ['users']);

        self::assertTrue(isset($t['users']));
        self::assertFalse(isset($t['nope']));
    }

    public function testOffsetGetUnknownThrows(): void
    {
        $t = new Tables('');

        $this->expectException(InvalidArgumentException::class);
        $t['nope'];
    }

    public function testOffsetSetWithKey(): void
    {
        $t = new Tables('');
        $t['users'] = 'app_users';

        self::assertSame('app_users', $t['users']);
    }

    public function testOffsetSetWithKeyUpdatesExistingKeepingFlags(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', null, true, 'u');

        $t['users'] = 'customers';

        // alias и prefix-флаг сохранились
        self::assertSame('cms_customers AS u', $t['users']);
    }

    public function testOffsetSetAppend(): void
    {
        $t = new Tables('');
        $t[] = 'users';

        self::assertTrue(isset($t['users']));
        self::assertSame('users', $t['users']);
    }

    public function testOffsetUnset(): void
    {
        $t = new Tables('', ['users', 'posts']);
        unset($t['users']);

        self::assertFalse(isset($t['users']));
        self::assertTrue(isset($t['posts']));
    }

    public function testOffsetUnsetUnknownKeyDoesNotThrow(): void
    {
        $t = new Tables('');
        unset($t['nope']);

        self::assertFalse(isset($t['nope']));
    }

    // ---------------------------------------------------------------------
    // Магия
    // ---------------------------------------------------------------------

    public function testMagicGet(): void
    {
        $t = new Tables('cms_');
        $t->addTable('users', 'users', null, true, 'u');

        self::assertSame('cms_users AS u', $t->users);
    }

    public function testMagicGetMissingReturnsEmptyString(): void
    {
        $t = new Tables('');

        self::assertSame('', $t->nope);
    }

    public function testMagicSet(): void
    {
        $t = new Tables('');
        $t->users = 'app_users';

        self::assertSame('app_users', $t['users']);
    }

    public function testMagicIsset(): void
    {
        $t = new Tables('', ['users']);

        self::assertTrue(isset($t->users));
        self::assertFalse(isset($t->nope));
    }

    public function testMagicUnset(): void
    {
        $t = new Tables('', ['users']);
        unset($t->users);

        self::assertFalse(isset($t['users']));
    }

    // ---------------------------------------------------------------------
    // Интеграционный сценарий
    // ---------------------------------------------------------------------

    public function testRealisticScenario(): void
    {
        $t = new Tables('app_', [
            'users',
            'posts' => 'blog_posts',
        ]);

        $t->addTable('users', 'users', null, true, 'u');
        $t->addTable('posts', 'blog_posts', null, false, 'p');
        $t->addTable('logs', 'users', 'archive_logs', true);

        self::assertSame('app_users AS u',   $t->users);
        self::assertSame('blog_posts AS p',  $t->posts);
        self::assertSame('archive_logs',     $t->logs);
        self::assertSame('app_users AS u',   $t['users']);
        self::assertSame('blog_posts AS p',  $t['posts']);

        self::assertSame(
            ['app_users AS u', 'blog_posts AS p', 'archive_logs'],
            $t->getTable()
        );
    }
}
