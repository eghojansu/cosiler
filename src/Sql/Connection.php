<?php

namespace Ekok\Cosiler\Sql;

use PDO;

use function Ekok\Cosiler\quote;
use function Ekok\Cosiler\walk;

/**
 * PDO Sql Connection Wrapper
 */
class Connection
{
    protected $hive = array();
    protected $options = array(
        'pagination_size' => 20,
        'format_query' => null,
        'raw_identifier' => null,
        'quotes' => array(),
        'scripts' => array(),
        'options' => array(),
    );

    public function __construct(
        protected string $dsn,
        protected string|null $username = null,
        protected string|null $password = null,
        array|null $options = null,
        protected Builder|null $builder = null,
    ) {
        if ($options) {
            $this->options = $options + $this->options;
        }
    }

    public function simplePaginate(string $table, int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->getPaginationSize());
        $offset = ($current_page - 1) * $limit;
        $subset = $this->select($table, $criteria, compact('limit', 'offset') + ($options ?? array()));
        $next_page = $current_page + 1;
        $prev_page = max($current_page - 1, 0);
        $count = count($subset);

        return compact('subset', 'count', 'current_page', 'next_page', 'prev_page') + array('per_page' => $limit);
    }

    public function paginate(string $table, int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->getPaginationSize());

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

    public function count(string $table, array|string $criteria = null, array $options = null): int
    {
        $builder = $this->getBuilder();

        list($sql, $values) = $builder->select($table, $criteria, array('orders' => null) + ($options ?? array()));
        list($sqlCount) = $builder->select($sql, null, array('sub' => true, 'alias' => '_c', 'columns' => array('_d' => $builder->raw('COUNT(*)'))));

        return intval($this->query($sqlCount, $values, $success)->fetchColumn(0));
    }

    public function select(string $table, array|string $criteria = null, array $options = null): array|null
    {
        list($sql, $values) = $this->getBuilder()->select($table, $criteria, $options);

        $args = $options['fetch_args'] ?? array();
        $fetch = $options['fetch'] ?? \PDO::FETCH_ASSOC;
        $query = $this->query($sql, $values, $success);

        return $success ? (false === ($result = $query->fetchAll($fetch, ...$args)) ? null : $result) : null;
    }

    public function selectOne(string $table, array|string $criteria = null, array $options = null)
    {
        return $this->select($table, $criteria, array('limit' => 1) + ($options ?? array()))[0] ?? null;
    }

    public function insert(string $table, array $data, array|string $options = null)
    {
        list($sql, $values) = $this->getBuilder()->insert($table, $data);

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
            $criteria[] = $this->getPdo()->lastInsertId();

            return $this->selectOne($table, $criteria, $loadOptions);
        })() : false;
    }

    public function update(string $table, array $data, array|string $criteria, array|bool|null $options = false)
    {
        list($sql, $values) = $this->getBuilder()->update($table, $data, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? (false === $options ? $query->rowCount() : $this->selectOne($table, $criteria, true === $options ? null : $options)) : false;
    }

    public function delete(string $table, array|string $criteria): bool|int
    {
        list($sql, $values) = $this->getBuilder()->delete($table, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : false;
    }

    public function insertBatch(string $table, array $data, array|string $criteria = null, array|string $options = null): bool|array
    {
        list($sql, $values) = $this->getBuilder()->insertBatch($table, $data);

        $query = $this->query($sql, $values, $success);

        return $success ? ($criteria ? $this->select($table, $criteria, $options) : $query->rowCount()) : false;
    }

    public function query(string $sql, array $values = null, bool &$success = null): \PDOStatement
    {
        $query = $this->getPdo()->prepare($sql);

        if (!$query) {
            throw new \RuntimeException('Unable to prepare query');
        }

        $result = $query->execute($values);
        $success = $result && '00000' === $query->errorCode();

        return $query;
    }

    public function exec(string $sql, array $values = null): int
    {
        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : 0;
    }

    public function transact(\Closure $fn)
    {
        $pdo = $this->getPdo();
        $auto = !$pdo->inTransaction();

        if ($auto) {
            $pdo->beginTransaction();
        }

        $result = $fn($this);

        if ($auto) {
            $endTransaction = '00000' === $pdo->errorCode() ? 'commit' : 'rollBack';

            $pdo->$endTransaction();
        }

        return $result;
    }

    public function exists(string $table): bool
    {
        $pdo = $this->getPdo();
        $mode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $out = $pdo->query('SELECT 1 FROM ' . $this->quote($table) . ' LIMIT 1');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, $mode);

        return !!$out;
    }

    public function quote(string $expr): string
    {
        return quote($expr, ...$this->getQuotes());
    }

    public function getQuotes(): array
    {
        return array_slice($this->options['quotes'] ?? array(), 0, 2);
    }

    public function getPaginationSize(): int
    {
        return $this->options['pagination_size'];
    }

    public function getVersion(): string
    {
        return $this->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function getDriver(): string
    {
        return strstr($this->dsn, ':', true);
    }

    public function getName(): string|null
    {
        return preg_match('/^.+?(?:dbname|database)=(.+?)(?=;|$)/is', $this->dsn, $match) ? str_replace('\\ ', ' ', $match[1]) : null;
    }

    public function getPdo(): \PDO
    {
        return $this->hive['pdo'] ?? ($this->hive['pdo'] = $this->getFreshPdo());
    }

    public function getBuilder(): Builder
    {
        return $this->builder ?? $this->hive['builder'] ?? (
            $this->hive['builder'] = new Builder($this->getDriver(), $this->options['format_query'], $this->getQuotes(), $this->options['raw_identifier'])
        );
    }

    public function __clone()
    {
        throw new \LogicException('Cloning Connection is prohibited');
    }

    public function getFreshPdo(): \PDO
    {
        try {
            $scripts = $this->options['scripts'] ?? array();
            $options = $this->options['options'] ?? array();

            $pdo = new PDO($this->dsn, $this->username, $this->password, $options);

            walk($scripts, fn($script) => $pdo->exec($script));

            return $pdo;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to connect database', 0, $e);
        }
    }
}
