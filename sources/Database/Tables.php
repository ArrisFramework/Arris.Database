<?php

namespace Arris\Database;

use InvalidArgumentException;

class Tables implements \ArrayAccess, TablesInterface
{
    private string $prefix;

    /**
     * Репозиторий таблиц.
     *
     * Формат:
     *   key => [
     *       'tableName'   => string,       // имя таблицы в БД
     *       'replacement' => string|null,  // явная подмена (перебивает prefix)
     *       'prefix'      => bool,         // нужно ли добавлять префикс
     *       'alias'       => string|null,  // алиас
     *   ]
     *
     * @var array<string, array{tableName: string, replacement: ?string, prefix: bool, alias: ?string}>
     */
    private array $tables = [];

    /**
     * @param string $prefix
     * @param array  $tables Скалярный список ['users', 'posts']
     *                       или ассоциативный ['users' => 'users_table']
     */
    public function __construct(string $prefix = '', array $tables = [])
    {
        $this->prefix = $prefix;

        foreach ($tables as $key => $value) {
            if (is_int($key)) {
                // ['users', 'posts'] — ключ = значение = имя таблицы
                $this->addTable($value, $value);
            } else {
                // ['users' => 'users_table']
                $this->addTable($key, $value);
            }
        }
    }

    /**
     * Добавляет таблицу в репозиторий.
     *
     * @param string      $key         Ключ для обращения
     * @param string|null $tableName   Имя таблицы в БД (по умолчанию = $key)
     * @param string|null $replacement Явная подмена (игнорирует prefix)
     * @param bool        $withPrefix  Добавлять ли префикс
     * @param string|null $alias       Алиас
     */
    public function addTable(
        string $key,
        ?string $tableName = null,
        ?string $replacement = null,
        bool $withPrefix = false,
        ?string $alias = null
    ): self {
        if ($tableName === null || $tableName === '') {
            $tableName = $key;
        }

        $this->tables[$key] = [
            'tableName'   => $tableName,
            'replacement' => $replacement,
            'prefix'      => $withPrefix,
            'alias'       => $alias,
        ];

        return $this;
    }

    /**
     * Возвращает список таблиц (с учётом правил) либо одну по ключу.
     *
     * @param string|null $key
     *
     * @return array|string
     */
    public function getTable(?string $key = null): array|string
    {
        if ($key === null || $key === '*') {
            $result = [];
            foreach (array_keys($this->tables) as $k) {
                $result[] = $this->offsetGet($k);
            }
            return $result;
        }

        return $this->offsetGet($key);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->tables[$offset]);
    }

    /**
     * Строит итоговое имя таблицы на основе структуры.
     */
    public function offsetGet(mixed $offset): string
    {
        if (!$this->offsetExists($offset)) {
            throw new InvalidArgumentException("Table '$offset' not found.");
        }

        $row = $this->tables[$offset];

        // replacement имеет приоритет над prefix
        if ($row['replacement'] !== null) {
            $result = $row['replacement'];
        } elseif ($row['prefix']) {
            $result = $this->prefix . $row['tableName'];
        } else {
            $result = $row['tableName'];
        }

        // алиас
        if ($row['alias'] !== null) {
            $result .= ' AS ' . $row['alias'];
        }

        return $result;
    }

    public function offsetSet($offset, mixed $value): void
    {
        if ($offset === null) {
            // $tables[] = 'name' — трактуем как новую таблицу
            $this->addTable((string)$value, (string)$value);
            return;
        }

        // Просто перезаписываем имя таблицы, сохраняя прочие атрибуты,
        // если запись уже существует; иначе создаём новую.
        if (isset($this->tables[$offset])) {
            $this->tables[$offset]['tableName'] = (string)$value;
        } else {
            $this->addTable((string)$offset, (string)$value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->tables[$offset]);
    }

    public function __get(string $name): ?string
    {
        return $this->offsetExists($name) ? $this->offsetGet($name) : '';
    }

    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }

    public function __unset(string $name): void
    {
        $this->offsetUnset($name);
    }
}