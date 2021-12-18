<?php

namespace Ekok\Cosiler\Sql;

use function Ekok\Cosiler\cast;
use function Ekok\Cosiler\Utils\Str\case_snake;

class Mapper implements \ArrayAccess, \Iterator
{
    /** @var \PDOStatement */
    protected $query;

    /** @var int */
    protected $ptr = -1;

    /** @var array */
    protected $row;

    /** @var array */
    protected $update = array();

    /** @var array */
    protected $columnsLoad = array();

    /** @var array */
    protected $columnsIgnore = array();

    public function __construct(
        protected Connection $db,
        protected string|null $table = null,
        protected array|null $keys = null,
        protected array|null $casts = null,
        array|null $columnsLoad = null,
        array|null $columnsIgnore = null,
    ) {
        if (!$table) {
            $this->table = self::resolveTableName();
        }

        if ($columnsLoad) {
            $this->columnsLoad = array_fill_keys($columnsLoad, true);
        }

        if ($columnsIgnore) {
            $this->columnsIgnore = array_fill_keys($columnsIgnore, true);
        }
    }

    public static function resolveTableName(): string
    {
        return case_snake(ltrim(strrchr('\\' . static::class, '\\'), '\\'));
    }

    public static function castTo(string|null $type, string $var): string|int|float|array|null
    {
        return match(strtolower($type)) {
            'array' => array_map('Ekok\\Cosiler\\cast', array_filter(array_map('trim', explode(',', $var)))),
            'json' => json_decode($var),
            'int' => filter_var($var, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => filter_var($var, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'boolean' => filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'string' => $var,
            default => cast($var),
        };
    }

    public static function castFrom(string $type, string|int|float|array|object|null $var): string|null
    {
        return match(strtolower($type)) {
            'array' => is_array($var) ? implode(',', $var) : null,
            'json' => is_string($var) ? $var : (is_scalar($var) || null === $var ? null : json_encode($var)),
            'boolean' => filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0,
            default => null === $var ? null : (string) $var,
        };
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function simplePaginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        return $this->db->simplePaginate($this->table, $page, $criteria, $options);
    }

    public function paginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        return $this->db->paginate($this->table, $page, $criteria, $options);
    }

    public function count(array|string $criteria = null, array $options = null): int
    {
        return $this->db->count($this->table, $criteria, $options);
    }

    public function select(array|string $criteria = null, array $options = null): array|null
    {
        return $this->db->select($this->table, $criteria, $options);
    }

    public function selectOne(array|string $criteria = null, array $options = null)
    {
        return $this->db->selectOne($this->table, $criteria, $options);
    }

    public function insert(array $data, array|string $options = null)
    {
        return $this->db->insert($this->table, $data, $options);
    }

    public function update(array $data, array|string $criteria, array|bool|null $options = false)
    {
        return $this->db->update($this->table, $data, $criteria, $options);
    }

    public function delete(array|string $criteria): bool|int
    {
        return $this->db->delete($this->table, $criteria);
    }

    public function insertBatch(array $data, array|string $criteria = null, array|string $options = null): bool|array
    {
        return $this->db->insertBatch($this->table, $data, $criteria, $options);
    }

    public function findAll(array|string $criteria = null, array $options = null): static
    {
        list($sql, $values) = $this->db->getBuilder()->select($this->table, $criteria, $options);

        // probably bug will come when query failed to run
        $this->query = $this->query($sql, $values);
        $this->row = null;
        $this->ptr = -1;
        $this->update = null;

        return $success ? (false === ($result = $query->fetchAll($fetch, ...$args)) ? null : $result) : null;
        $this->query =
    }

    public function current(): mixed
    {
        $row = $this->row;

        if ($this->columnsLoad && $row) {
            $row = array_intersect_key($row, $this->columnsLoad);
        }

        if ($this->columnsIgnore && $row) {
            $row = array_diff_key($row, $this->columnsIgnore);
        }

        return $row;
    }

    public function key(): mixed
    {
        return $this->ptr;
    }

    public function next(): void
    {
        $this->row = $this->query->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT) ?: null;
        $this->ptr = $this->row ? $this->ptr + 1 : -1;
    }

    public function rewind(): void
    {
        $this->row = $this->query->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_FIRST) ?: null;
        $this->ptr = $this->row ? 1 : -1;
    }

    public function valid(): bool
    {
        return $this->ptr > -1;
    }

    public function offsetExists(mixed $offset): bool
    {
        return (
            isset($this->row[$offset])
            || isset($this->update[$offset])
            || array_key_exists($offset, $this->row)
            || array_key_exists($offset, $this->update)
            || method_exists($this, 'get' . $offset)
            || method_exists($this, 'is' . $offset)
            || method_exists($this, 'has' . $offset)
        );
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (
            method_exists($this, $get = 'get' . $offset)
            || method_exists($this, $get = 'is' . $offset)
            || method_exists($this, $get = 'has' . $offset)
        ) {
            return $this->$get();
        }

        $this->columnCheck($offset);

        if (isset($this->update[$offset]) || array_key_exists($offset, $this->update)) {
            return $this->update[$offset];
        }

        if (!array_key_exists($offset, $this->row)) {
            throw new \LogicException(sprintf('Column not exists: %s', $offset));
        }

        return static::castTo($this->casts[$offset] ?? null, $this->row[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (method_exists($this, $set = 'set' . $offset)) {
            $this->$set($value);

            return;
        }

        $this->columnCheck($offset);

        $this->update[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (method_exists($this, $remove = 'remove' . $offset)) {
            $this->$remove();

            return;
        }

        $this->columnCheck($offset);

        unset($this->update[$offset]);
    }

    protected function columnCheck($column): void
    {
        if ($this->columnsIgnore && isset($this->columnsIgnore[$column])) {
            throw new \LogicException(sprintf('Column access is forbidden: %s', $column));
        }

        if ($this->columnsLoad && !isset($this->columnsLoad[$column])) {
            throw new \LogicException(sprintf('Column not exists: %s', $column));
        }
    }
}
