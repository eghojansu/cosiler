<?php

namespace Ekok\Cosiler\Sql;

use function Ekok\Cosiler\Utils\Arr\merge;
use function Ekok\Cosiler\Utils\Arr\walk;
use function Ekok\Cosiler\Utils\Arr\without;

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
        'table_prefix' => null,
        'quotes' => array(),
        'scripts' => array(),
        'options' => array(),
    );

    /** @var string */
    private $driver;

    /** @var string|null */
    private $name;

    /** @var Helper */
    private $helper;

    /** @var Builder */
    private $builder;

    public function __construct(
        protected string $dsn,
        protected string|null $username = null,
        protected string|null $password = null,
        array|null $options = null,
    ) {
        if ($options) {
            $this->options = $options + $this->options;
        }

        $this->driver = strstr($this->dsn, ':', true);
        $this->name = preg_match('/^.+?(?:dbname|database)=(.+?)(?=;|$)/is', $this->dsn, $match) ? str_replace('\\ ', ' ', $match[1]) : null;
        $this->helper = new Helper($this->options['quotes'], $this->options['raw_identifier'], $this->options['table_prefix']);
        $this->builder = new Builder($this->helper, $this->driver, $this->options['format_query']);
    }

    public function simplePaginate(string $table, int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->options['pagination_size']);
        $offset = ($current_page - 1) * $limit;
        $subset = $this->select($table, $criteria, merge($options, compact('limit', 'offset')));
        $next_page = $current_page + 1;
        $prev_page = max($current_page - 1, 0);
        $count = count($subset);

        return compact('subset', 'count', 'current_page', 'next_page', 'prev_page') + array('per_page' => $limit);
    }

    public function paginate(string $table, int $page = 1, array|string $criteria = null, array $options = null): array
    {
        $current_page = max($page, 1);
        $limit = intval($options['limit'] ?? $this->options['pagination_size']);

        $total = $this->count($table, $criteria, without($options, 'limit'));
        $last_page = intval(ceil($total / $limit));

        $offset = ($current_page - 1) * $limit;
        $subset = $total > 0 ? $this->select($table, $criteria, merge($options, compact('limit', 'offset'))) : array();
        $next_page = min($current_page + 1, $last_page);
        $prev_page = max($current_page - 1, 0);
        $count = count($subset);
        $first = $offset + 1;
        $last = max($first, $offset + $count);

        return compact('subset', 'count', 'current_page', 'next_page', 'prev_page', 'last_page', 'total', 'first', 'last') + array('per_page' => $limit);
    }

    public function count(string $table, array|string $criteria = null, array $options = null): int
    {
        list($sql, $values) = $this->builder->select($table, $criteria, without($options, 'orders'));
        list($sqlCount) = $this->builder->select($sql, null, array('sub' => true, 'alias' => '_c', 'columns' => array('_d' => $this->helper->raw('COUNT(*)'))));

        return intval($this->query($sqlCount, $values, $success)->fetchColumn(0));
    }

    public function select(string $table, array|string $criteria = null, array $options = null): array|null
    {
        list($sql, $values) = $this->builder->select($table, $criteria, $options);

        $args = $options['fetch_args'] ?? array();
        $fetch = $options['fetch'] ?? \PDO::FETCH_ASSOC;
        $query = $this->query($sql, $values, $success);

        return $success ? (false === ($result = $query->fetchAll($fetch, ...$args)) ? null : $result) : null;
    }

    public function selectOne(string $table, array|string $criteria = null, array $options = null): array|object|null
    {
        return $this->select($table, $criteria, merge($options, array('limit' => 1)))[0] ?? null;
    }

    public function insert(string $table, array $data, array|string $options = null): bool|int|array|object|null
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
            $criteria[] = $this->getPdo()->lastInsertId();

            return $this->selectOne($table, $criteria, $loadOptions);
        })() : 0;
    }

    public function update(string $table, array $data, array|string $criteria, array|bool|null $options = false): bool|int|array|object|null
    {
        list($sql, $values) = $this->getBuilder()->update($table, $data, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? (false === $options ? $query->rowCount() : $this->selectOne($table, $criteria, true === $options ? null : $options)) : 0;
    }

    public function delete(string $table, array|string $criteria): bool|int
    {
        list($sql, $values) = $this->getBuilder()->delete($table, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : 0;
    }

    public function insertBatch(string $table, array $data, array|string $criteria = null, array|string $options = null): bool|int|array|null
    {
        list($sql, $values) = $this->getBuilder()->insertBatch($table, $data);

        $query = $this->query($sql, $values, $success);

        return $success ? ($criteria ? $this->select($table, $criteria, $options) : $query->rowCount()) : 0;
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

    public function lastId(string $name = null): string|false
    {
        return $this->getPdo()->lastInsertId($name);
    }

    public function exists(string $table): bool
    {
        $pdo = $this->getPdo();
        $mode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $out = $pdo->query('SELECT 1 FROM ' . $this->helper->table($table) . ' LIMIT 1');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, $mode);

        return !!$out;
    }

    public function getHelper(): Helper
    {
        return $this->helper;
    }

    public function setHelper(Helper $helper): static
    {
        $this->helper = $helper;

        return $this;
    }

    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    public function setBuilder(Builder $builder): static
    {
        $this->builder = $builder;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getVersion(): string
    {
        return $this->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function getPdo(): \PDO
    {
        return $this->hive['pdo'] ?? ($this->hive['pdo'] = self::createPDOConnection(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options['options'],
            $this->options['scripts'],
        ));
    }

    public function __clone()
    {
        throw new \LogicException('Cloning Connection is prohibited');
    }

    public static function createPDOConnection(
        string $dsn,
        string $username = null,
        string $password = null,
        array $options = null,
        array $scripts = null,
    ): \PDO
    {
        try {
            $pdo = new \PDO($dsn, $username, $password, $options);

            walk($scripts ?? array(), fn($script) => $pdo->exec($script));

            return $pdo;
        } catch (\Throwable $error) {
            throw new \RuntimeException('Unable to connect database', 0, $error);
        }
    }
}
