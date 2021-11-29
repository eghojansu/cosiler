<?php

namespace Ekok\Cosiler\Sql;

use PDO;

use function Ekok\Cosiler\walk;

/**
 * PDO Sql Connection Wrapper
 *
 * @property \PDO $pdo
 * @property array $quotes
 * @property string $name
 * @property string $driver
 * @property string $version
 */
class Connection
{
    private $hive = array();

    public function __construct(
        private string $dsn,
        private string|null $username = null,
        private string|null $password = null,
        private array|null $options = null,
    ) {}

    public function select(string $table, array|string $criteria = null, array $options = null): array|null
    {
        list($sql, $values) = $this->buildSelect($table, $criteria, $options);

        $args = $options['fetch_args'] ?? array();
        $fetch = $options['fetch'] ?? \PDO::FETCH_ASSOC;
        $query = $this->query($sql, $values, $success);

        return $success ? ($query->fetchAll($fetch, ...$args) ?: null) : null;
    }

    public function selectOne(string $table, array|string $criteria = null, array $options = null)
    {
        return $this->select($table, $criteria, array('limit' => 1) + ($options ?? array()))[0] ?? null;
    }

    public function insert(string $table, array $data, array|string $options = null)
    {
        list($sql, $values) = $this->buildInsert($table, $data);

        $query = $this->query($sql, $values, $success);

        if (!$success) {
            return false;
        }

        if (!$options || (is_array($options) && !($load = $options['load'] ?? null))) {
            return true;
        }

        if (isset($load)) {
            $loadOptions = $options;
        } else {
            $loadOptions = null;
            $load = $options;
        }

        $criteria = is_string($load) ? array($load . ' = ?') : (array) $load;
        $criteria[1][] = $this->pdo->lastInsertId();

        return $this->selectOne($table, $criteria, $loadOptions);
    }

    public function update(string $table, array $data, array|string $criteria, array|bool|null $options = false)
    {
        list($sql, $values) = $this->buildUpdate($table, $data, $criteria);

        $query = $this->query($sql, $values, $success);

        if (!$success) {
            return false;
        }

        if (false === $options) {
            return true;
        }

        $loadOptions = true === $options ? null : $options;

        return $this->selectOne($table, $criteria, $loadOptions);
    }

    public function delete(string $table, array|string $criteria): bool|int
    {
        list($sql, $values) = $this->buildDelete($table, $criteria);

        $query = $this->query($sql, $values, $success);

        if (!$success) {
            return false;
        }

        return $query->rowCount();
    }

    public function insertBatch(string $table, array $data, array|string $criteria = null, array|string $options = null): bool|array
    {
        list($sql, $values) = $this->buildInsertBatch($table, $data);

        $query = $this->query($sql, $values, $success);

        if (!$success) {
            return false;
        }

        if (!$criteria) {
            return true;
        }

        return $this->select($table, $criteria, $options);
    }

    public function query(string $sql, array $values = null, bool &$result = null): \PDOStatement
    {
        $query = $this->pdo->prepare($sql);
        $result = $query->execute($query);

        return $query;
    }

    public function exec(string $sql, array $values = null): int
    {
        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : 0;
    }

    public function transact(\Closure $fn)
    {
        $pdo = $this->pdo;
        $auto = !$pdo->inTransaction();

        if ($auto) {
            $pdo->beginTransaction();
        }

        $result = $fn($this);

        if ($auto) {
            if ('00000' === $pdo->errorCode()) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        }

        return $result;
    }

    public function exists(string $table): bool
    {
        $mode = $this->pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $out = $this->pdo->query('SELECT 1 FROM ' . $this->quote($table) . ' LIMIT 1');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, $mode);

        return !!$out;
    }

    public function quote(string $expr): string
    {
        if (false === strpos($expr, '.')) {
            return $expr;
        }

        list($a, $b) = $this->quotes;

        return $a . implode($b . '.' . $a, explode('.', $expr)) . $b;
    }

    public function getName(): string|null
    {
        return preg_match('/^.+?(?:dbname|database)=(.+?)(?=;|$)/is', $this->dsn, $match) ? str_replace('\\ ', ' ', $match[1]) : null;
    }

    public function getQuotes(): array
    {
        return array_slice($this->options['quotes'] ?? array('"', '"'), 0, 2);
    }

    public function getDriver(): string
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function getVersion(): string
    {
        return $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function getPdo(): \PDO
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

    public function buildSelect(string $table, array|string $criteria = null, array $options = null): array
    {
        if ($options) {
            extract($options, prefix: 'o_');
        }

        $alias = $o_alias ?? null;
        $sub = $o_sub ?? false;
        $lf = "\n";
        $top = $lf;
        $sql = '';
        $values = array();

        $sql .= $lf . (isset($o_columns) ? $this->buildColumns($o_columns, $sub ? $alias : $table, $lf) : '*');
        $sql .= $lf . 'FROM ' . ($sub ? '(' . $table . ')' : $this->quote($table));

        if ($alias) {
            $sql .= $lf . 'AS ' . $this->quote($alias);
        }

        if (isset($o_joins) && $line = $this->buildJoins($o_joins)) {
            $sql .= $lf . $line;
        }

        if ($criteria && $filter = $this->buildCriteria($criteria)) {
            $sql .= $lf . 'WHERE ' . array_shift($filter);

            array_push($values, ...$filter);
        }

        if (isset($o_groups) && $line = $this->buildOrders($o_groups)) {
            $sql .= $lf . 'GROUP BY ' . $line;
        }

        if (isset($o_havings) && $filter = $this->buildCriteria($o_havings)) {
            $sql .= $lf . 'HAVING ' . array_shift($filter);

            array_push($values, ...$filter);
        }

        if (isset($o_orders) && $line = $this->buildOrders($o_orders)) {
            $sql .= $lf . 'ORDER BY ' . $line;
        }

        if ($parts = $this->buildOffset($o_limit ?? null, $o_offset ?? null, $sql)) {
            $sql .= $parts[0];
            $top .= $parts[1] . $lf;
        }

        return array('SELECT' . $top . $sql, $values);
    }

    public function __clone()
    {
        throw new \LogicException('Cloning Connection is prohibited');
    }

    public function __isset($name)
    {
        return isset($this->hive[$name]) || array_key_exists($name, $this->hive);
    }

    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->hive[$name];
        }

        if (method_exists($this, $get = 'get' . $name)) {
            return $this->hive[$name] = $this->$get();
        }

        throw new \RuntimeException(sprintf('Undefined property: %s', $name));
    }
}
