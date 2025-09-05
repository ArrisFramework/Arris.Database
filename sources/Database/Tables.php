<?php

namespace Arris\Database;

use InvalidArgumentException;

class Tables implements \ArrayAccess, TablesInterface
{
    private string $prefix;
    private array $tables;
    private array $havePrefix;
    private array $haveAlias;

    /**
     * Инициализируется репозиторий таблиц
     *
     * @param string $prefix
     * @param array $tables
     * @param array $havePrefix
     * @param array $haveAlias
     */
    public function __construct(
        string $prefix = '',
        array $tables = [],
        array $havePrefix = [],
        array $haveAlias = []
    ) {
        $this->prefix = $prefix;
        $this->tables = $tables;
        $this->havePrefix = $havePrefix;
        $this->haveAlias = $haveAlias;

        // Если в конструктор переданы строки (не ассоциативный массив),
        // то каждая строка становится и ключом, и значением
        foreach ($tables as $key => $value) {
            if (is_int($key)) {
                // Это скалярный массив типа ['a', 'b']
                $this->addTable($value, $value);
            } else {
                // Это ассоциативный массив типа ['users' => 'users_table']
                $this->addTable($key, $value);
            }
        }
    }

    /**
     * Добавляет таблицу в репозиторий
     *
     * @param string $key Ключ для обращения к таблице
     * @param string|null $tableName Имя таблицы в БД
     * @param string|null $replacement Если задан, будет использовано вместо tableName
     * @param bool $withPrefix Добавлять ли префикс (игнорируется если задан replacement)
     * @param string|null $alias Алиас для таблицы
     * @return Tables
     */
    public function addTable(
        string $key,
        ?string $tableName = null,
        ?string $replacement = null,
        bool $withPrefix = false,
        ?string $alias = null
    ): self {
        if (empty($tableName)) {
            $tableName = $key;
        }

        $this->tables[$key] = $tableName;

        if ($replacement !== null) {
            $this->havePrefix[$key] = $replacement;
        } elseif ($withPrefix) {
            $this->havePrefix[] = $key;
        }

        if ($alias !== null) {
            $this->haveAlias[$key] = $alias;
        }

        return $this;
    }

    /**
     * Возвращает список таблиц (в репозитории) с учетом правил замен
     * Или одну таблицу по ключу.
     *
     * @param string|null $key
     * @return array|string
     */
    public function getTable(?string $key = null):array|string
    {
        if (is_null($key)) {
            $tables = [];
            foreach ($this->tables as $key => $v) {
                $tables[] = $this->offsetGet($key);
            }

            return $tables;
        }

        return $this->offsetGet($key);
    }

    /**
     * Проверяет, существует ли ключ
     * (таблица в репозитории)
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->tables[$offset]);
    }

    /**
     * Возвращает значение по ключу с учетом логики префиксов и алиасов
     *
     * @param mixed $offset
     * @return string
     */
    public function offsetGet(mixed $offset): string
    {
        if (!$this->offsetExists($offset)) {
            throw new InvalidArgumentException("Table '$offset' not found.");
        }

        $tableName = $this->tables[$offset];
        $result = $tableName;

        // Если аргумент есть в таблице замен - подставляем значение из have_prefix
        if (isset($this->havePrefix[$offset])) {
            $result = $this->havePrefix[$offset];
        } elseif (in_array($offset, $this->havePrefix, true)) {
            // Иначе если есть в таблице have_prefix - ставим префикс
            $result = $this->prefix . $tableName;
        }
        // Иначе ставим значение ключа (уже в $tableName)

        // Если аргумент есть в таблице have_alias - добавляем AS alias
        if (isset($this->haveAlias[$offset])) {
            $alias = $this->haveAlias[$offset];
            $result .= ' AS ' . $alias;
        }

        return $result;
    }

    /**
     * Устанавливает значение
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->tables[] = $value;
        } else {
            $this->tables[$offset] = $value;
        }
    }

    /**
     * Удаляет значение по ключу
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->tables[$offset]);
        unset($this->havePrefix[$offset]);
        unset($this->haveAlias[$offset]);

        // Удаляем из массива скалярных значений, если есть
        $key = array_search($offset, $this->havePrefix, true);
        if ($key !== false) {
            unset($this->havePrefix[$key]);
        }
    }


}

# -eof- #