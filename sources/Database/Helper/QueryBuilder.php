<?php

namespace Arris\Database\Helper;

use Closure;
use InvalidArgumentException;
use PDO;
use Arris\Database\Connector;
use Arris\Database\Statement;
use RuntimeException;

class QueryBuilder
{
    private string $type = 'select';
    private array $columns = ['*'];
    private string $table = '';
    private string $alias = '';
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $sets = [];
    private array $values = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?string $having = null;
    private array $havingBindings = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private bool $distinct = false;
    private array $unions = [];
    private bool $isUnionAll = false;

    public function __construct(
        private readonly Connector $connector
    ) {}

    /**
     * Создает новый экземпляр билдера
     */
    public static function create(Connector $connector): self
    {
        return new self($connector);
    }

    /**
     * SELECT query
     */
    public function select(string|array ...$columns): self
    {
        $this->type = 'select';
        $this->columns = empty($columns) ? ['*'] :
            (is_array($columns[0]) ? $columns[0] : $columns);
        return $this;
    }

    /**
     * Добавляет DISTINCT
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * FROM table
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->table = $table;
        $this->alias = $alias ?? '';
        return $this;
    }

    /**
     * UPDATE query
     */
    public function update(string $table): self
    {
        $this->type = 'update';
        $this->table = $table;
        return $this;
    }

    /**
     * INSERT query
     */
    public function insert(string $table): self
    {
        $this->type = 'insert';
        $this->table = $table;
        return $this;
    }

    /**
     * REPLACE query
     */
    public function replace(string $table): self
    {
        $this->type = 'replace';
        $this->table = $table;
        return $this;
    }

    /**
     * DELETE query
     */
    public function delete(string $table): self
    {
        $this->type = 'delete';
        $this->table = $table;
        return $this;
    }

    /**
     * Устанавливает данные для INSERT/UPDATE/REPLACE
     */
    public function data(array $data): self
    {
        return match($this->type) {
            'insert', 'replace' => $this->values($data),
            'update' => $this->set($data),
            default => throw new InvalidArgumentException("Method data() not available for {$this->type} queries")
        };
    }

    /**
     * SET для UPDATE (может принимать массив или отдельные значения)
     */
    public function set(string|array $column, mixed $value = null): self
    {
        if (is_array($column)) {
            $this->sets = array_merge($this->sets, $column);
        } else {
            $this->sets[$column] = $value;
        }
        return $this;
    }

    /**
     * VALUES для INSERT/REPLACE
     */
    public function values(array $data): self
    {
        $this->values = $data;
        return $this;
    }

    /**
     * WHERE condition
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof Closure) {
            return $this->whereGroup($column, $boolean);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * OR WHERE condition
     */
    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE with raw SQL
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean
        ];

        $this->bindings = [...$this->bindings, ...$bindings];
        return $this;
    }

    /**
     * WHERE NULL
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not
        ];
        return $this;
    }

    /**
     * WHERE NOT NULL
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * WHERE IN
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (empty($values)) {
            throw new InvalidArgumentException('WHERE IN values cannot be empty');
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not
        ];

        $this->bindings = [...$this->bindings, ...$values];
        return $this;
    }

    /**
     * WHERE NOT IN
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * WHERE BETWEEN
     */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean,
            'not' => $not
        ];

        $this->bindings[] = $min;
        $this->bindings[] = $max;
        return $this;
    }

    /**
     * WHERE NOT BETWEEN
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): self
    {
        return $this->whereBetween($column, $min, $max, $boolean, true);
    }

    /**
     * Сгруппированное условие WHERE
     */
    private function whereGroup(\Closure $callback, string $boolean = 'AND'): self
    {
        $query = new self($this->connector);
        $callback($query);

        $this->wheres[] = [
            'type' => 'group',
            'query' => $query,
            'boolean' => $boolean
        ];

        $this->bindings = [...$this->bindings, ...$query->getBindings()];
        return $this;
    }

    /**
     * JOIN helper
     */
    private function addJoin(string $type, string $table, string $first, ?string $operator, ?string $second): self
    {
        if (func_num_args() === 4) {
            $second = $operator;
            $operator = '=';
        }

        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator ?? '=',
            'second' => $second
        ];

        return $this;
    }

    /**
     * INNER JOIN
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * INNER JOIN (алиас)
     */
    public function innerJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    /**
     * RIGHT JOIN
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    /**
     * CROSS JOIN
     */
    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table,
            'first' => null,
            'operator' => null,
            'second' => null
        ];
        return $this;
    }

    /**
     * GROUP BY
     */
    public function groupBy(string|array ...$columns): self
    {
        $cols = empty($columns) ? [] :
            (is_array($columns[0]) ? $columns[0] : $columns);
        $this->groupBy = [...$this->groupBy, ...$cols];
        return $this;
    }

    /**
     * HAVING
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having = "$column $operator ?";
        $this->havingBindings[] = $value;
        return $this;
    }

    /**
     * ORDER BY
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Invalid order direction: $direction");
        }

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction
        ];
        return $this;
    }

    /**
     * LIMIT
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be non-negative');
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }
        $this->offset = $offset;
        return $this;
    }

    /**
     * UNION
     */
    public function union(self $query, bool $all = false): self
    {
        $this->unions[] = $query;
        $this->isUnionAll = $all;
        return $this;
    }

    /**
     * UNION ALL
     */
    public function unionAll(self $query): self
    {
        return $this->union($query, true);
    }

    /**
     * Получить все bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Компилирует SQL запрос
     */
    public function toSQL(): string
    {
        return match($this->type) {
            'select' => $this->compileSelect(),
            'insert' => $this->compileInsert(),
            'update' => $this->compileUpdate(),
            'delete' => $this->compileDelete(),
            'replace' => $this->compileReplace(),
            default => throw new RuntimeException("Unknown query type: {$this->type}")
        };
    }

    /**
     * Компилирует SELECT
     */
    private function compileSelect(): string
    {
        $parts = ['SELECT'];

        if ($this->distinct) {
            $parts[] = 'DISTINCT';
        }

        $parts[] = implode(', ', $this->columns);
        $parts[] = 'FROM ' . $this->table;

        if ($this->alias) {
            $parts[] = 'AS ' . $this->alias;
        }

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                if ($join['type'] === 'CROSS') {
                    $parts[] = "CROSS JOIN {$join['table']}";
                } else {
                    $parts[] = sprintf(
                        '%s JOIN %s ON %s %s %s',
                        $join['type'],
                        $join['table'],
                        $join['first'],
                        $join['operator'],
                        $join['second']
                    );
                }
            }
        }

        if (!empty($this->wheres)) {
            $parts[] = 'WHERE ' . $this->compileWheres();
        }

        if (!empty($this->groupBy)) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having) {
            $parts[] = 'HAVING ' . $this->having;
        }

        if (!empty($this->orderBy)) {
            $orderParts = array_map(
                fn($order) => "{$order['column']} {$order['direction']}",
                $this->orderBy
            );
            $parts[] = 'ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $parts[] = 'LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $parts[] = 'OFFSET ' . $this->offset;
        }

        if (!empty($this->unions)) {
            foreach ($this->unions as $union) {
                $keyword = $this->isUnionAll ? 'UNION ALL' : 'UNION';
                $parts[] = "$keyword {$union->toSql()}";
                $this->bindings = [...$this->bindings, ...$union->getBindings()];
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Компилирует INSERT
     */
    private function compileInsert(): string
    {
        if (empty($this->values)) {
            throw new RuntimeException('No data provided for INSERT');
        }

        $columns = array_keys($this->values);
        $placeholders = array_fill(0, count($columns), '?');
        $this->bindings = array_values($this->values);

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * Компилирует UPDATE
     */
    private function compileUpdate(): string
    {
        if (empty($this->sets)) {
            throw new RuntimeException('No data provided for UPDATE');
        }

        $setParts = [];
        $setBindings = [];

        foreach ($this->sets as $column => $value) {
            $setParts[] = "$column = ?";
            $setBindings[] = $value;
        }

        $this->bindings = [...$setBindings, ...$this->bindings];

        $parts = [
            'UPDATE',
            $this->table,
            'SET',
            implode(', ', $setParts)
        ];

        if (!empty($this->wheres)) {
            $parts[] = 'WHERE ' . $this->compileWheres();
        }

        return implode(' ', $parts);
    }

    /**
     * Компилирует DELETE
     */
    private function compileDelete(): string
    {
        $parts = ['DELETE FROM', $this->table];

        if (!empty($this->wheres)) {
            $parts[] = 'WHERE ' . $this->compileWheres();
        }

        return implode(' ', $parts);
    }

    /**
     * Компилирует REPLACE
     */
    private function compileReplace(): string
    {
        if (empty($this->values)) {
            throw new RuntimeException('No data provided for REPLACE');
        }

        $columns = array_keys($this->values);
        $placeholders = array_fill(0, count($columns), '?');
        $this->bindings = array_values($this->values);

        return sprintf(
            'REPLACE INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * Компилирует WHERE условия
     */
    private function compileWheres(): string
    {
        $parts = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index > 0 ? "{$where['boolean']} " : '';

            $parts[] = match($where['type']) {
                'basic' => sprintf(
                    '%s%s %s ?',
                    $boolean,
                    $where['column'],
                    $where['operator']
                ),
                'raw' => $boolean . $where['sql'],
                'null' => sprintf(
                    '%s%s IS %sNULL',
                    $boolean,
                    $where['column'],
                    $where['not'] ? 'NOT ' : ''
                ),
                'in' => sprintf(
                    '%s%s %sIN (%s)',
                    $boolean,
                    $where['column'],
                    $where['not'] ? 'NOT ' : '',
                    implode(', ', array_fill(0, count($where['values']), '?'))
                ),
                'between' => sprintf(
                    '%s%s %sBETWEEN ? AND ?',
                    $boolean,
                    $where['column'],
                    $where['not'] ? 'NOT ' : ''
                ),
                'group' => sprintf(
                    '%s(%s)',
                    $boolean,
                    $where['query']->compileWheres()
                ),
                default => throw new RuntimeException("Unknown WHERE type: {$where['type']}")
            };
        }

        return implode(' ', $parts);
    }

    /**
     * Подготавливает statement с bindings
     */
    private function prepareStatement(): Statement
    {
        $sql = $this->toSQL();
        $stmt = $this->connector->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement');
        }

        // Добавляем HAVING bindings после основных
        $allBindings = [...$this->bindings, ...$this->havingBindings];

        foreach ($allBindings as $index => $value) {
            $type = match(true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };
            $stmt->bindValue($index + 1, $value, $type);
        }

        return $stmt;
    }

    /**
     * Выполняет запрос
     */
    public function execute(): Statement
    {
        $stmt = $this->prepareStatement();
        $stmt->execute();
        return $stmt;
    }

    /**
     * Выполняет SELECT и возвращает все результаты
     */
    public function get(): array
    {
        return $this->execute()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Алиас для get()
     */
    public function run(): array
    {
        return $this->get();
    }

    /**
     * Получает первую запись
     */
    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->execute()->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Получает значение одной колонки
     */
    public function value(string $column): mixed
    {
        $result = $this->first();
        return $result[$column] ?? null;
    }

    /**
     * Получает список значений колонки
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();

        if (empty($results)) {
            return [];
        }

        if ($key === null) {
            return array_column($results, $column);
        }

        $values = [];
        foreach ($results as $row) {
            if (isset($row[$key])) {
                $values[$row[$key]] = $row[$column] ?? null;
            }
        }

        return $values;
    }

    /**
     * Количество затронутых строк (для UPDATE/DELETE)
     */
    public function rowCount(): int
    {
        return $this->execute()->rowCount();
    }

    /**
     * Получает ID последней вставленной записи
     */
    public function insertGetId(): string|false
    {
        $this->execute();
        return $this->connector->lastInsertId();
    }

    /**
     * Проверяет существование записей
     */
    public function exists(): bool
    {
        $original = $this->columns;
        $this->columns = ['1'];
        $this->limit(1);

        $result = !empty($this->get());

        $this->columns = $original;
        return $result;
    }

    /**
     * Подсчет записей
     */
    public function count(string $column = '*'): int
    {
        $this->columns = ["COUNT($column) as aggregate"];
        $result = $this->first();
        return (int)($result['aggregate'] ?? 0);
    }

    /**
     * Максимальное значение
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Минимальное значение
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Среднее значение
     */
    public function avg(string $column): mixed
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Сумма значений
     */
    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Выполняет агрегатную функцию
     */
    private function aggregate(string $function, string $column): mixed
    {
        $this->columns = ["{$function}($column) as aggregate"];
        $result = $this->first();
        return $result['aggregate'] ?? null;
    }

    /**
     * Сбрасывает билдер к начальному состоянию
     */
    public function reset(): self
    {
        $this->type = 'select';
        $this->columns = ['*'];
        $this->table = '';
        $this->alias = '';
        $this->joins = [];
        $this->wheres = [];
        $this->bindings = [];
        $this->sets = [];
        $this->values = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = null;
        $this->havingBindings = [];
        $this->limit = null;
        $this->offset = null;
        $this->distinct = false;
        $this->unions = [];
        $this->isUnionAll = false;

        return $this;
    }

    /**
     * Отладочная информация
     */
    public function debug(): array
    {
        return [
            'sql' => $this->toSQL(),
            'bindings' => [...$this->bindings, ...$this->havingBindings],
            'type' => $this->type
        ];
    }

    /**
     * Вывод SQL для дебага
     */
    public function dd(): never
    {
        $debug = $this->debug();
        echo "SQL: {$debug['sql']}\n";
        echo "Bindings: " . json_encode($debug['bindings'], JSON_PRETTY_PRINT) . "\n";
        echo "Type: {$debug['type']}\n";
        exit(1);
    }
}