<?php

namespace Ekok\Cosiler\Sql;

use function Ekok\Cosiler\cast;
use function Ekok\Cosiler\Utils\Arr\map;
use function Ekok\Cosiler\Utils\Arr\each;
use function Ekok\Cosiler\Utils\Arr\ensure;
use function Ekok\Cosiler\Utils\Arr\first;
use function Ekok\Cosiler\Utils\Arr\merge;
use function Ekok\Cosiler\Utils\Arr\reduce;
use function Ekok\Cosiler\Utils\Str\case_snake;

class Mapper implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    /** @var int */
    protected $ptr = -1;

    /** @var array */
    protected $rows = array();

    /** @var array */
    protected $updates = array();

    /** @var array */
    protected $keys = array();

    /** @var array */
    protected $columnsLoad = array();

    /** @var array */
    protected $columnsIgnore = array();

    /** @var array */
    protected $getters;

    public function __construct(
        protected Connection $db,
        protected string|null $table = null,
        string|array|null $keys = null,
        protected array|null $casts = null,
        protected bool|null $readonly = null,
        string|array|null $columnsLoad = null,
        string|array|null $columnsIgnore = null,
    ) {
        if (!$table) {
            $this->table = case_snake(ltrim(strrchr('\\' . static::class, '\\'), '\\'));
        }

        if ($columnsLoad) {
            $this->columnsLoad = array_fill_keys(ensure($columnsLoad), true);
        }

        if ($columnsIgnore) {
            $this->columnsIgnore = array_fill_keys(ensure($columnsIgnore), true);
        }

        if ($keys) {
            $this->keys = map(ensure($keys), fn($auto, $key) => is_numeric($key) ? array($auto, true) : array($key, !!$auto));
        }
    }

    public function table(): string
    {
        return $this->db->getHelper()->isRaw($this->table, $table) ? $table : $this->table;
    }

    public function countRow(array|string $criteria = null, array $options = null): int
    {
        return $this->db->count($this->table, $criteria, $options);
    }

    public function simplePaginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        return $this->paginate($page, $criteria, merge($options, array('full' => false)));
    }

    public function paginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $result = $this->db->paginate($this->table, $page, $criteria, merge($options, array('columns' => array_keys($this->columnsLoad))));

        if ($result['count']) {
            $result['subset'] = $this->castOutAll($result['subset']);
        }

        return $result;
    }

    public function select(array|string $criteria = null, array $options = null): array|null
    {
        $result = $this->db->select($this->table, $criteria, merge($options, array('columns' => array_keys($this->columnsLoad))));

        return $result ? $this->castOutAll($result) : null;
    }

    public function selectOne(array|string $criteria = null, array $options = null): array|object|null
    {
        return $this->select($criteria, merge($options, array('limit' => 1)))[0] ?? null;
    }

    public function insert(array $data, array|string $options = null): bool|int|array|object|null
    {
        $this->writeCheck();

        return $this->db->insert($this->table, $this->toSave($data), $options);
    }

    public function update(array $data, array|string $criteria, array|bool|null $options = false): bool|int|array|object|null
    {
        $this->writeCheck();

        return $this->db->update($this->table, $this->toSave($data), $criteria, $options);
    }

    public function delete(array|string $criteria): bool|int
    {
        $this->writeCheck();

        return $this->db->delete($this->table, $criteria);
    }

    public function insertBatch(array $data, array|string $criteria = null, array|string $options = null): bool|int|array|null
    {
        $this->writeCheck();

        return $this->db->insertBatch($this->table, $this->toSaveAll($data), $criteria, $options);
    }

    public function findAll(array|string $criteria = null, array $options = null): static
    {
        $this->rows = $this->db->select($this->table, $criteria, merge($options, array('columns' => array_keys($this->columnsLoad))));
        $this->updates = array();
        $this->rewind();

        return $this;
    }

    public function findOne(array|string $criteria = null, array $options = null): static
    {
        return $this->findAll($criteria, merge($options, array('limit' => 1)));
    }

    public function find(string|int ...$ids): static
    {
        $this->keysCheck($ids);

        $helper = $this->db->getHelper();
        $criteria = $ids;

        array_unshift($criteria, reduce(
            $this->keys,
            fn($prev, $key) => $prev . ($prev ? ' AND ' : '') . $helper->quote($key) . ' = ?',
            null,
            true,
        ));

        return $this->findOne($criteria);
    }

    public function save(): bool
    {
        if ($this->dry()) {
            throw new \LogicException('No data to be saved');
        }

        $this->writeCheck();

        $row = $this->toSave($this->row());
        $update = $this->toSave($this->changes());

        $this->updates = array();

        // updating?
        if ($this->valid()) {
            $this->keysCheck();

            $criteria = $this->buildLoadCriteria($row);
            $saved = $this->db->update($this->table, $update, $criteria) > 0;
        } else {
            $criteria = null;
            $saved = $this->db->insert($this->table, $update) > 0;

            if ($saved && $this->keys) {
                $auto = first($this->keys, fn($auto, $key) => $auto ? $key : null);
                $criteria = $this->buildLoadCriteria(merge($update, $auto ? array($auto => $this->db->lastId()) : array()));
            }
        }

        if ($saved && $criteria) {
            $this->findOne($criteria);
        }

        return $saved;
    }

    public function reset(): static
    {
        $this->rows = array();
        $this->updates = array();
        $this->ptr = -1;

        return $this;
    }

    public function count(): int
    {
        return $this->valid() ? count($this->rows) : 0;
    }

    public function toArray(): array
    {
        if (!$this->getters) {
            $ref = new \ReflectionClass($this);

            $this->getters = map(
                $ref->getMethods(\ReflectionMethod::IS_PUBLIC),
                fn($method) => (
                    str_starts_with($method->name, $accesor = 'get')
                    || str_starts_with($method->name, $accesor = 'has')
                    || str_starts_with($method->name, $accesor = 'is')
                ) ? array(case_snake(substr($method->name, strlen($accesor))), $method->name) : null,
            );
        }

        $row = $this->fixData(array_replace($this->castOutRow($this->row()), $this->changes()));

        if ($this->getters) {
            $row += map($this->getters, fn($method, $name) => array($name, $this->$method()));
        }

        return $row;
    }

    public function fromArray(array $data): static
    {
        $this->updates[$this->ptr()] = $this->fixData($data);

        return $this;
    }

    public function row(): array
    {
        return $this->rows[$this->ptr] ?? array();
    }

    public function changes(): array
    {
        return $this->updates[$this->ptr()] ?? array();
    }

    public function all(): array
    {
        $ptr = $this->ptr;
        $data = each($this, fn(self $self) => $self->toArray());
        $this->ptr = $ptr;

        return $data;
    }

    public function dirty(): bool
    {
        return !$this->dry();
    }

    public function dry(): bool
    {
        return !$this->changes();
    }

    public function invalid(): bool
    {
        return !$this->valid();
    }

    public function current(): mixed
    {
        return $this;
    }

    public function key(): mixed
    {
        return $this->ptr;
    }

    public function next(): void
    {
        $this->ptr++;
    }

    public function rewind(): void
    {
        $this->ptr = $this->rows ? 0 : -1;
    }

    public function valid(): bool
    {
        return isset($this->rows[$this->ptr]);
    }

    public function offsetExists(mixed $offset): bool
    {
        $row = $this->row();
        $changes = $this->changes();

        return (
            isset($row[$offset])
            || isset($changes[$offset])
            || array_key_exists($offset, $row)
            || array_key_exists($offset, $changes)
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

        $row = $this->row();
        $changes = $this->changes();

        if (isset($changes[$offset]) || array_key_exists($offset, $changes)) {
            return $changes[$offset];
        }

        if (!array_key_exists($offset, $row)) {
            throw new \LogicException(sprintf('Column not exists: %s', $offset));
        }

        return $this->castOut($offset, $row[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (method_exists($this, $set = 'set' . $offset)) {
            $this->$set($value);

            return;
        }

        $this->columnCheck($offset);

        $this->updates[$this->ptr()][$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (method_exists($this, $remove = 'remove' . $offset)) {
            $this->$remove();

            return;
        }

        $this->columnCheck($offset);

        unset($this->updates[$this->ptr()][$offset]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->all();
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

    protected function writeCheck(): void
    {
        if ($this->readonly) {
            throw new \LogicException('This mapper is readonly');
        }
    }

    protected function keysCheck(array $ids = null): void
    {
        if (!$this->keys) {
            throw new \LogicException('This mapper has no keys');
        }

        if (null !== $ids && count($ids) !== count($this->keys)) {
            throw new \LogicException('Insufficient keys');
        }
    }

    protected function fixData(array $data): array
    {
        $row = $data;

        if ($this->columnsIgnore && $row) {
            $row = array_diff_key($row, $this->columnsIgnore);
        }

        if ($this->columnsLoad && $row) {
            $row = array_intersect_key($row, $this->columnsLoad);
        }

        return $row;
    }

    protected function toSave(array $data): array
    {
        return $this->castInRow($this->fixData($data));
    }

    protected function toSaveAll(array $data): array
    {
        return array_map(array($this, 'toSave'), $data);
    }

    protected function castOutAll(array $rows): array
    {
        return array_map(array($this, 'castOutRow'), $rows);
    }

    protected function castOutRow(array $row): array
    {
        return each($row, fn($value, $key) => $this->castOut($key, $value), true, false);
    }

    protected function castOut(string $column, string|null $var): \DateTime|string|int|float|array|bool|null
    {
        $cast = $this->casts[$column] ?? null;

        return match($cast) {
            'arr', 'array' => array_map('Ekok\\Cosiler\\cast', array_filter(array_map('trim', explode(',', $var)))),
            'json' => json_decode($var, true),
            'int', 'integer' => filter_var($var, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => filter_var($var, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool', 'boolean' => filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'str', 'string' => $var,
            'date', 'datetime' => new \DateTime($var),
            default => null === $var ? null : cast($var),
        };
    }

    protected function castInRow(array $row): array
    {
        return each($row, fn($value, $key) => $this->castIn($key, $value), true, false);
    }

    protected function castIn(string $column, string|int|float|bool|array|object|null $var): string|int|float|bool|null
    {
        $cast = $this->casts[$column] ?? null;

        return match($cast) {
            'arr', 'array' => is_array($var) ? implode(',', $var) : null,
            'json' => is_string($var) ? $var : (is_scalar($var) || null === $var ? null : json_encode($var)),
            'bool', 'boolean' => filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0,
            'date' => $var instanceof \DateTime ? $var->format('Y-m-d') : (is_string($var) ? date('Y-m-d', strtotime($var)) : null),
            'datetime' => $var instanceof \DateTime ? $var->format('Y-m-d H:i:s') : (is_string($var) ? date('Y-m-d H:i:s', strtotime($var)) : null),
            default => is_scalar($var) || null === $var ? $var : (string) $var,
        };
    }

    protected function ptr(): int
    {
        return max(0, $this->ptr);
    }

    protected function buildLoadCriteria(array $row): array
    {
        $helper = $this->db->getHelper();

        return reduce($this->keys, static function ($prev, $key) use ($helper, $row) {
            if ($prev[0]) {
                $prev[0] .= ' AND ';
            }

            $prev[0] .= $helper->quote($key) . ' = ?';
            $prev[] = $row[$key] ?? null;

            return $prev;
        }, array(''), true);
    }
}
