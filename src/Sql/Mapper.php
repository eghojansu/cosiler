<?php

namespace Ekok\Cosiler\Sql;

class Mapper
{
    protected $hive = array();

    public function __construct(
        protected Connection $db,
        protected string|null $table = null,
        protected array|null $options = null,
    ) {}

    public function getTable(): string
    {
        return $this->table ?? $this->hive['table'] ?? ($this->hive['table'] = $this->resolveTable());
    }

    public function resolveTable(): string
    {

    }

    public function simplePaginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->paginationSize);
        $offset = ($current_page - 1) * $limit;
        $subset = $this->select($table, $criteria, compact('limit', 'offset') + ($options ?? array()));
        $next_page = $current_page + 1;
        $prev_page = max($current_page - 1, 0);
        $count = count($subset);

        return compact('subset', 'count', 'current_page', 'next_page', 'prev_page') + array('per_page' => $limit);
    }

    public function paginate(int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->paginationSize);

        $total = $this->count($table, $criteria, array('limit' => null) + ($options ?? array()));
        $last_page = intval(ceil($total / $limit));

        $offset = ($current_page - 1) * $limit;
        $subset = $total > 0 ? $this->select($table, $criteria, compact('limit', 'offset') + ($options ?? array())) : array();
        $next_page = min($current_page + 1, $last_page);
        $prev_page = max($current_page - 1, 0);
        $count = count($subset);
        $first = $offset + 1;
        $last = max($first, $offset + $count);

        return compact('subset', 'count', 'current_page', 'next_page', 'prev_page', 'last_page', 'total', 'first', 'last') + array('per_page' => $limit);
    }

    public function count(array|string $criteria = null, array $options = null): int
    {
        list($sql, $values) = $this->builder->select($table, $criteria, array('orders' => null) + ($options ?? array()));
        list($sqlCount) = $this->builder->select($sql, null, array('sub' => true, 'alias' => '_c', 'columns' => array('_d' => $this->builder->raw('COUNT(*)'))));

        return intval($this->query($sqlCount, $values, $success)->fetchColumn(0));
    }

    public function select(array|string $criteria = null, array $options = null): array|null
    {
        list($sql, $values) = $this->builder->select($table, $criteria, $options);

        $args = $options['fetch_args'] ?? array();
        $fetch = $options['fetch'] ?? \PDO::FETCH_ASSOC;
        $query = $this->query($sql, $values, $success);

        return $success ? (false === ($result = $query->fetchAll($fetch, ...$args)) ? null : $result) : null;
    }

    public function selectOne(array|string $criteria = null, array $options = null)
    {
        return $this->select($table, $criteria, array('limit' => 1) + ($options ?? array()))[0] ?? null;
    }

    public function insert(array $data, array|string $options = null)
    {
        list($sql, $values) = $this->builder->insert($table, $data);

        $query = $this->query($sql, $values, $success);

        return $success ? (function () use ($query, $options, $table) {
            if (!$options || (is_array($options) && !($load = $options['load'] ?? null))) {
                return $query->rowCount();
            }

            if (isset($load)) {
                $loadOptions = $options;
            } else {
                $loadOptions = null;
                $load = $options;
            }

            $criteria = is_string($load) ? array($load . ' = ?') : (array) $load;
            $criteria[] = $this->pdo->lastInsertId();

            return $this->selectOne($table, $criteria, $loadOptions);
        })() : false;
    }

    public function update(array $data, array|string $criteria, array|bool|null $options = false)
    {
        list($sql, $values) = $this->builder->update($table, $data, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? (false === $options ? $query->rowCount() : $this->selectOne($table, $criteria, true === $options ? null : $options)) : false;
    }

    public function delete(array|string $criteria): bool|int
    {
        list($sql, $values) = $this->builder->delete($table, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : false;
    }

    public function insertBatch(array $data, array|string $criteria = null, array|string $options = null): bool|array
    {
        list($sql, $values) = $this->builder->insertBatch($table, $data);

        $query = $this->query($sql, $values, $success);

        return $success ? ($criteria ? $this->select($table, $criteria, $options) : $query->rowCount()) : false;
    }
}
