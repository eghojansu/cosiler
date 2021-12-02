<?php

namespace Ekok\Cosiler\Sql;

use PDO;

use function Ekok\Cosiler\quote;
use function Ekok\Cosiler\walk;

/**
 * PDO Sql Connection Wrapper
 *
 * @property \PDO $pdo
 * @property Builder $builder
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
        Builder|null $builder = null,
    ) {
        if ($builder) {
            $this->hive['builder'] = $builder;
        }
    }

    public function select(string $table, array|string $criteria = null, array $options = null): array|null
    {
        list($sql, $values) = $this->builder->select($table, $criteria, $options);

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

    public function update(string $table, array $data, array|string $criteria, array|bool|null $options = false)
    {
        list($sql, $values) = $this->builder->update($table, $data, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? (false === $options ? $query->rowCount() : $this->selectOne($table, $criteria, true === $options ? null : $options)) : false;
    }

    public function delete(string $table, array|string $criteria): bool|int
    {
        list($sql, $values) = $this->builder->delete($table, $criteria);

        $query = $this->query($sql, $values, $success);

        return $success ? $query->rowCount() : false;
    }

    public function insertBatch(string $table, array $data, array|string $criteria = null, array|string $options = null): bool|array
    {
        list($sql, $values) = $this->builder->insertBatch($table, $data);

        $query = $this->query($sql, $values, $success);

        return $success ? ($criteria ? $this->select($table, $criteria, $options) : $query->rowCount()) : false;
    }

    public function query(string $sql, array $values = null, bool &$result = null): \PDOStatement
    {
        $query = $this->pdo->prepare($sql);

        if (!$query) {
            throw new \RuntimeException('Unable to prepare query');
        }

        $result = $query->execute($values);

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
            $endTransaction = '00000' === $pdo->errorCode() ? 'commit' : 'rollBack';

            $pdo->$endTransaction();
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
        return quote($expr, ...$this->quotes);
    }

    public function getName(): string|null
    {
        return preg_match('/^.+?(?:dbname|database)=(.+?)(?=;|$)/is', $this->dsn, $match) ? str_replace('\\ ', ' ', $match[1]) : null;
    }

    public function getQuotes(): array
    {
        return array_slice($this->options['quotes'] ?? array(), 0, 2);
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
            $options = $this->options['pdo'] ?? array();

            $pdo = new PDO($this->dsn, $this->username, $this->password, $options);

            walk($scripts, fn($script) => $pdo->exec($script));

            return $pdo;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to connect database', 0, $e);
        }
    }

    public function getBuilder(): Builder
    {
        return new Builder($this->driver, $this->options['format_query'] ?? null, $this->quotes, $this->options['raw_identifier'] ?? null);
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
