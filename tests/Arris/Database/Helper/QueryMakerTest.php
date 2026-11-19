<?php

declare(strict_types=1);

namespace Tests\Arris\Database\Helper;

use Arris\Database\Helper\QueryMaker;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QueryMakerTest extends TestCase
{
    public function testCastKeepsNull(): void
    {
        self::assertNull(QueryMaker::cast(null));
    }

    public function testBuildInsertQueryBindsNullAsNull(): void
    {
        $dataset = ['name' => null];

        $result = QueryMaker::buildInsertQuery('t', $dataset);

        self::assertSame('INSERT INTO t (name) VALUES (:name)', $result->query);
        self::assertNull($result->params[':name']);
    }

    public function testBuildInsertQueryInlinesSqlFunctions(): void
    {
        $dataset = [
            'created_at' => 'NOW()',
            'id' => 'UUID()',
            'name' => 'x',
        ];

        $result = QueryMaker::buildInsertQuery('t', $dataset);

        self::assertSame(
            'INSERT INTO t (created_at, id, name) VALUES (NOW(), UUID(), :name)',
            $result->query
        );
        self::assertSame([':name' => 'x'], $result->params);
    }

    public function testMakeUpdateQueryAcceptsArrayAndNullValues(): void
    {
        $dataset = [
            'payload' => ['a' => 1],
            'deleted_at' => null,
        ];

        $sql = QueryMaker::makeUpdateQuery('t', $dataset, 'id = 1');

        self::assertStringContainsString('payload = :payload', $sql);
        self::assertStringContainsString('deleted_at = :deleted_at', $sql);
        self::assertSame('{"a":1}', $dataset['payload']);
        self::assertNull($dataset['deleted_at']);
    }

    public function testMakeUpdateQueryDoesNotDuplicateWhere(): void
    {
        $dataset = ['a' => 1];

        $sql = QueryMaker::makeUpdateQuery('t', $dataset, 'WHERE id = 1');

        self::assertSame(1, substr_count(strtoupper($sql), 'WHERE'));
    }

    public function testMakeInsertQueryHandlesNonStringValues(): void
    {
        $dataset = [
            'n' => 5,
            'created_at' => 'NOW()',
        ];

        $sql = QueryMaker::makeInsertQuery('t', $dataset);

        self::assertStringContainsString('n = :n', $sql);
        self::assertStringContainsString('created_at = NOW()', $sql);
        self::assertSame(['n' => 5], $dataset);
    }

    public function testMakeInsertQueryRequiresData(): void
    {
        $dataset = [];

        $this->expectException(InvalidArgumentException::class);

        QueryMaker::makeInsertQuery('t', $dataset);
    }

    public function testBuildUpdateQueryUsesSeparatePrefixes(): void
    {
        $dataset = ['name' => 'x', 'updated_at' => 'NOW()'];
        $where = ['id' => 7];

        $result = QueryMaker::buildUpdateQuery('t', $dataset, $where);

        self::assertSame(
            'UPDATE t SET name = :set_name, updated_at = NOW()  WHERE id = :where_id',
            $result->query
        );
        self::assertSame([':set_name' => 'x', ':where_id' => 7], $result->params);
    }
}
