<?php

namespace Arris\Database\Helper;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Arris\Database\Connector;
use Arris\Database\Statement;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class QueryBuilderTest extends TestCase
{
    private Connector|MockObject $connector;
    private Statement|MockObject $statement;
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(Connector::class);
        $this->statement = $this->createMock(Statement::class);
        $this->qb = new QueryBuilder($this->connector);
    }

    // ==================== SELECT Tests ====================

    public function testSimpleSelect(): void
    {
        $sql = $this->qb->select('id', 'name')
            ->from('users')
            ->toSQL();

        $this->assertEquals('SELECT id, name FROM users', $sql);
    }

    public function testSelectWithArray(): void
    {
        $sql = $this->qb->select(['id', 'name', 'email'])
            ->from('users')
            ->toSQL();

        $this->assertEquals('SELECT id, name, email FROM users', $sql);
    }

    public function testSelectAll(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users', $sql);
    }

    public function testSelectDistinct(): void
    {
        $sql = $this->qb->select('country')
            ->distinct()
            ->from('users')
            ->toSQL();

        $this->assertEquals('SELECT DISTINCT country FROM users', $sql);
    }

    public function testSelectWithAlias(): void
    {
        $sql = $this->qb->select('u.id', 'u.name')
            ->from('users', 'u')
            ->toSQL();

        $this->assertEquals('SELECT u.id, u.name FROM users AS u', $sql);
    }

    // ==================== WHERE Tests ====================

    public function testSimpleWhere(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->where('id', 5)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $sql);
        $this->assertEquals([5], $this->qb->getBindings());
    }

    public function testWhereWithOperator(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->where('age', '>=', 18)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE age >= ?', $sql);
        $this->assertEquals([18], $this->qb->getBindings());
    }

    public function testMultipleWhere(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->where('status', 'active')
            ->where('age', '>', 21)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND age > ?', $sql);
        $this->assertEquals(['active', 21], $this->qb->getBindings());
    }

    public function testOrWhere(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->where('role', 'admin')
            ->orWhere('role', '=', 'moderator')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE role = ? OR role = ?', $sql);
        $this->assertEquals(['admin', 'moderator'], $this->qb->getBindings());
    }

    public function testWhereNull(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->whereNull('deleted_at')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE deleted_at IS NULL', $sql);
    }

    public function testWhereNotNull(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->whereNotNull('email_verified_at')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE email_verified_at IS NOT NULL', $sql);
    }

    public function testWhereIn(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->whereIn('id', [1, 2, 3])
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE id IN (?, ?, ?)', $sql);
        $this->assertEquals([1, 2, 3], $this->qb->getBindings());
    }

    public function testWhereNotIn(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->whereNotIn('status', ['banned', 'deleted'])
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE status NOT IN (?, ?)', $sql);
        $this->assertEquals(['banned', 'deleted'], $this->qb->getBindings());
    }

    public function testWhereInEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WHERE IN values cannot be empty');

        $this->qb->select()
            ->from('users')
            ->whereIn('id', [])
            ->toSQL();
    }

    public function testWhereBetween(): void
    {
        $sql = $this->qb->select()
            ->from('products')
            ->whereBetween('price', 100, 500)
            ->toSQL();

        $this->assertEquals('SELECT * FROM products WHERE price BETWEEN ? AND ?', $sql);
        $this->assertEquals([100, 500], $this->qb->getBindings());
    }

    public function testWhereNotBetween(): void
    {
        $sql = $this->qb->select()
            ->from('products')
            ->whereNotBetween('stock', 0, 10)
            ->toSQL();

        $this->assertEquals('SELECT * FROM products WHERE stock NOT BETWEEN ? AND ?', $sql);
        $this->assertEquals([0, 10], $this->qb->getBindings());
    }

    public function testWhereRaw(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->whereRaw('YEAR(created_at) = ?', [2024])
            ->toSQL();

        $this->assertEquals('SELECT * FROM users WHERE YEAR(created_at) = ?', $sql);
        $this->assertEquals([2024], $this->qb->getBindings());
    }

    public function testWhereGroup(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->where('status', 'active')
            ->where(function($q) {
                $q->where('role', 'admin')
                    ->orWhere('role', '=', 'moderator');
            })
            ->toSQL();

        $this->assertEquals(
            'SELECT * FROM users WHERE status = ? AND (role = ? OR role = ?)',
            $sql
        );
        $this->assertEquals(['active', 'admin', 'moderator'], $this->qb->getBindings());
    }

    // ==================== JOIN Tests ====================

    public function testInnerJoin(): void
    {
        $sql = $this->qb->select('users.name', 'orders.total')
            ->from('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->toSQL();

        $this->assertEquals(
            'SELECT users.name, orders.total FROM users INNER JOIN orders ON users.id = orders.user_id',
            $sql
        );
    }

    public function testLeftJoin(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->toSQL();

        $this->assertEquals(
            'SELECT * FROM users LEFT JOIN orders ON users.id = orders.user_id',
            $sql
        );
    }

    public function testRightJoin(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->rightJoin('orders', 'users.id', '=', 'orders.user_id')
            ->toSQL();

        $this->assertEquals(
            'SELECT * FROM users RIGHT JOIN orders ON users.id = orders.user_id',
            $sql
        );
    }

    public function testCrossJoin(): void
    {
        $sql = $this->qb->select()
            ->from('colors')
            ->crossJoin('sizes')
            ->toSQL();

        $this->assertEquals('SELECT * FROM colors CROSS JOIN sizes', $sql);
    }

    public function testMultipleJoins(): void
    {
        $sql = $this->qb->select()
            ->from('users', 'u')
            ->join('orders o', 'u.id', '=', 'o.user_id')
            ->leftJoin('products p', 'o.product_id', '=', 'p.id')
            ->toSQL();

        $this->assertStringContainsString('FROM users AS u', $sql);
        $this->assertStringContainsString('INNER JOIN orders o ON u.id = o.user_id', $sql);
        $this->assertStringContainsString('LEFT JOIN products p ON o.product_id = p.id', $sql);
    }

    // ==================== ORDER BY, LIMIT, OFFSET Tests ====================

    public function testOrderBy(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users ORDER BY created_at DESC', $sql);
    }

    public function testMultipleOrderBy(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->orderBy('status', 'ASC')
            ->orderBy('name', 'DESC')
            ->toSQL();

        $this->assertEquals('SELECT * FROM users ORDER BY status ASC, name DESC', $sql);
    }

    public function testInvalidOrderDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order direction: INVALID');

        $this->qb->select()
            ->from('users')
            ->orderBy('name', 'INVALID');
    }

    public function testLimit(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->limit(10)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->offset(20)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users OFFSET 20', $sql);
    }

    public function testLimitAndOffset(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->limit(10)
            ->offset(20)
            ->toSQL();

        $this->assertEquals('SELECT * FROM users LIMIT 10 OFFSET 20', $sql);
    }

    public function testNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be non-negative');

        $this->qb->limit(-1);
    }

    public function testNegativeOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be non-negative');

        $this->qb->offset(-1);
    }

    // ==================== GROUP BY and HAVING Tests ====================

    public function testGroupBy(): void
    {
        $sql = $this->qb->select('country', 'COUNT(*) as total')
            ->from('users')
            ->groupBy('country')
            ->toSQL();

        $this->assertEquals('SELECT country, COUNT(*) as total FROM users GROUP BY country', $sql);
    }

    public function testMultipleGroupBy(): void
    {
        $sql = $this->qb->select('country', 'city', 'COUNT(*) as total')
            ->from('users')
            ->groupBy('country', 'city')
            ->toSQL();

        $this->assertEquals(
            'SELECT country, city, COUNT(*) as total FROM users GROUP BY country, city',
            $sql
        );
    }

    public function testGroupByWithArray(): void
    {
        $sql = $this->qb->select()
            ->from('users')
            ->groupBy(['country', 'city'])
            ->toSQL();

        $this->assertEquals('SELECT * FROM users GROUP BY country, city', $sql);
    }

    public function testHaving(): void
    {
        $sql = $this->qb->select('category', 'COUNT(*) as total')
            ->from('products')
            ->groupBy('category')
            ->having('total', '>', 10)
            ->toSQL();

        $this->assertEquals(
            'SELECT category, COUNT(*) as total FROM products GROUP BY category HAVING total > ?',
            $sql
        );
    }

    // ==================== UNION Tests ====================

    public function testUnion(): void
    {
        $qb1 = new QueryBuilder($this->connector);
        $qb2 = new QueryBuilder($this->connector);

        $qb2->select('id', 'name')->from('inactive_users');

        $sql = $qb1->select('id', 'name')
            ->from('active_users')
            ->union($qb2)
            ->toSQL();

        $this->assertStringContainsString('UNION SELECT id, name FROM inactive_users', $sql);
    }

    public function testUnionAll(): void
    {
        $qb1 = new QueryBuilder($this->connector);
        $qb2 = new QueryBuilder($this->connector);

        $qb2->select('id')->from('table2');

        $sql = $qb1->select('id')
            ->from('table1')
            ->unionAll($qb2)
            ->toSQL();

        $this->assertStringContainsString('UNION ALL', $sql);
    }

    // ==================== INSERT Tests ====================

    public function testInsert(): void
    {
        $sql = $this->qb->insert('users')
            ->values([
                'name' => 'John',
                'email' => 'john@example.com',
                'age' => 30
            ])
            ->toSQL();

        $this->assertEquals('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', $sql);
        $this->assertEquals(['John', 'john@example.com', 30], $this->qb->getBindings());
    }

    public function testInsertWithData(): void
    {
        $sql = $this->qb->insert('users')
            ->data([
                'name' => 'Jane',
                'email' => 'jane@example.com'
            ])
            ->toSQL();

        $this->assertEquals('INSERT INTO users (name, email) VALUES (?, ?)', $sql);
        $this->assertEquals(['Jane', 'jane@example.com'], $this->qb->getBindings());
    }

    public function testInsertEmptyData(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No data provided for INSERT');

        $this->qb->insert('users')->toSQL();
    }

    // ==================== UPDATE Tests ====================

    public function testUpdate(): void
    {
        $sql = $this->qb->update('users')
            ->set('status', 'inactive')
            ->where('id', 5)
            ->toSQL();

        $this->assertEquals('UPDATE users SET status = ? WHERE id = ?', $sql);
        $this->assertEquals(['inactive', 5], $this->qb->getBindings());
    }

    public function testUpdateWithArray(): void
    {
        $sql = $this->qb->update('users')
            ->set([
                'status' => 'active',
                'updated_at' => '2024-01-01'
            ])
            ->where('id', 10)
            ->toSQL();

        $this->assertEquals('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', $sql);
        $this->assertEquals(['active', '2024-01-01', 10], $this->qb->getBindings());
    }

    public function testUpdateWithData(): void
    {
        $sql = $this->qb->update('users')
            ->data([
                'name' => 'Updated',
                'email' => 'updated@example.com'
            ])
            ->where('id', 1)
            ->toSQL();

        $this->assertEquals('UPDATE users SET name = ?, email = ? WHERE id = ?', $sql);
    }

    public function testUpdateEmptyData(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No data provided for UPDATE');

        $this->qb->update('users')->toSQL();
    }

    // ==================== DELETE Tests ====================

    public function testDelete(): void
    {
        $sql = $this->qb->delete('users')
            ->where('status', 'deleted')
            ->toSQL();

        $this->assertEquals('DELETE FROM users WHERE status = ?', $sql);
        $this->assertEquals(['deleted'], $this->qb->getBindings());
    }

    public function testDeleteWithMultipleWhere(): void
    {
        $sql = $this->qb->delete('users')
            ->where('status', 'inactive')
            ->where('created_at', '<', '2020-01-01')
            ->toSQL();

        $this->assertEquals('DELETE FROM users WHERE status = ? AND created_at < ?', $sql);
    }

    // ==================== REPLACE Tests ====================

    public function testReplace(): void
    {
        $sql = $this->qb->replace('settings')
            ->values([
                'key' => 'theme',
                'value' => 'dark'
            ])
            ->toSQL();

        $this->assertEquals('REPLACE INTO settings (key, value) VALUES (?, ?)', $sql);
        $this->assertEquals(['theme', 'dark'], $this->qb->getBindings());
    }

    public function testReplaceEmptyData(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No data provided for REPLACE');

        $this->qb->replace('settings')->toSQL();
    }

    // ==================== Execution Tests ====================

    public function testGet(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->qb->select()->from('users')->get();

        $this->assertEquals($expectedData, $result);
    }

    public function testFirst(): void
    {
        $expectedData = ['id' => 1, 'name' => 'John'];

        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->qb->select()->from('users')->first();

        $this->assertEquals($expectedData, $result);
    }

    public function testFirstReturnsNull(): void
    {
        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->qb->select()->from('users')->first();

        $this->assertNull($result);
    }

    public function testValue(): void
    {
        $expectedData = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];

        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->qb->select()->from('users')->value('email');

        $this->assertEquals('john@example.com', $result);
    }

    public function testPluck(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob']
        ];

        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->qb->select()->from('users')->pluck('name');

        $this->assertEquals(['John', 'Jane', 'Bob'], $result);
    }

    public function testPluckWithKey(): void
    {
        $expectedData = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->qb->select()->from('users')->pluck('name', 'id');

        $this->assertEquals([1 => 'John', 2 => 'Jane'], $result);
    }

    public function testExists(): void
    {
        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([['1' => 1]]);

        $result = $this->qb->select()->from('users')->where('id', 1)->exists();

        $this->assertTrue($result);
    }

    public function testCount(): void
    {
        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(['aggregate' => 42]);

        $result = $this->qb->select()->from('users')->count();

        $this->assertEquals(42, $result);
    }

    public function testMax(): void
    {
        $this->connector->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(['aggregate' => 1000]);

        $result = $this->qb->select()->from('products')->max('price');

        $this->assertEquals(1000, $result);
    }

    // ==================== Helper Methods Tests ====================

    public function testReset(): void
    {
        $this->qb->select('id', 'name')
            ->from('users')
            ->where('status', 'active')
            ->orderBy('created_at', 'DESC')
            ->limit(10);

        $this->qb->reset();

        $sql = $this->qb->select()->from('products')->toSQL();

        $this->assertEquals('SELECT * FROM products', $sql);
        $this->assertEmpty($this->qb->getBindings());
    }

    public function testDebug(): void
    {
        $this->qb->select('*')
            ->from('users')
            ->where('age', '>', 18);

        $debug = $this->qb->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('sql', $debug);
        $this->assertArrayHasKey('bindings', $debug);
        $this->assertArrayHasKey('type', $debug);
        $this->assertEquals('SELECT * FROM users WHERE age > ?', $debug['sql']);
        $this->assertEquals([18], $debug['bindings']);
        $this->assertEquals('select', $debug['type']);
    }

    public function testCreate(): void
    {
        $qb = QueryBuilder::create($this->connector);

        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    // ==================== Complex Query Tests ====================

    public function testComplexQuery(): void
    {
        $sql = $this->qb->select(['u.id', 'u.name', 'COUNT(o.id) as order_count'])
            ->from('users', 'u')
            ->leftJoin('orders o', 'u.id', '=', 'o.user_id')
            ->where('u.status', 'active')
            ->where(function($q) {
                $q->where('u.role', 'admin')
                    ->orWhere('u.role', '=', 'moderator');
            })
            ->whereNotNull('u.email_verified_at')
            ->whereBetween('u.age', 18, 65)
            ->groupBy('u.id')
            ->having('order_count', '>', 5)
            ->orderBy('order_count', 'DESC')
            ->limit(10)
            ->offset(0)
            ->toSQL();

        $this->assertStringContainsString('SELECT u.id, u.name, COUNT(o.id) as order_count', $sql);
        $this->assertStringContainsString('FROM users AS u', $sql);
        $this->assertStringContainsString('LEFT JOIN orders o ON u.id = o.user_id', $sql);
        $this->assertStringContainsString('WHERE u.status = ?', $sql);
        $this->assertStringContainsString('GROUP BY u.id', $sql);
        $this->assertStringContainsString('HAVING order_count > ?', $sql);
        $this->assertStringContainsString('ORDER BY order_count DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 0', $sql);
    }

    public function testDataMethodOnSelectThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Method data() not available for select queries');

        $this->qb->select()
            ->from('users')
            ->data(['name' => 'test']);
    }
}