<?php

namespace Ekok\Cosiler\Sql;

use function Ekok\Cosiler\each;
use function Ekok\Cosiler\quote;

class Builder
{
    private $quotes = array();
    private $rawIdentifier = '```';
    private $delimiter = ' ';

    public function __construct(
        private string|null $driver = null,
        private bool|null $format = null,
        string|array $quotes = null,
        string $rawIdentifier = null,
    ) {
        if ($quotes) {
            $this->quotes = array_slice(is_array($quotes) ? array_values($quotes) : str_split($quotes), 0, 2);
        }

        if ($rawIdentifier) {
            $this->rawIdentifier = $rawIdentifier;
        }

        if ($format) {
            $this->delimiter = "\n";
        }
    }

    public function quote(string $expr): string
    {
        return quote($expr, ...$this->quotes);
    }

    public function select(string $table, array|string $criteria = null, array $options = null): array
    {
        if ($options) {
            extract($options, EXTR_PREFIX_ALL, 'o');
        }

        $sub = $o_sub ?? false;
        $alias = $o_alias ?? null;
        $prefix = $alias ?? ($sub ? null : $table);
        $lf = $this->delimiter;
        $sql = '';
        $values = array();

        if ($sub && !$alias) {
            throw new \LogicException('Sub query needs an alias');
        }

        $sql .= $lf . (isset($o_columns) ? $this->columns($o_columns, $prefix, $lf) : '*');
        $sql .= $lf . 'FROM ' . ($sub ? '(' . $table . ')' : $this->quote($table));

        if ($alias) {
            $sql .= $lf . 'AS ' . $this->quote($alias);
        }

        if (isset($o_joins) && $line = $this->joins($o_joins, $lf)) {
            $sql .= $lf . $line;
        }

        if ($criteria && $filter = $this->criteria($criteria)) {
            $sql .= $lf . 'WHERE ' . array_shift($filter);

            array_push($values, ...$filter);
        }

        if (isset($o_groups) && $line = $this->orders($o_groups, $prefix, $lf)) {
            $sql .= $lf . 'GROUP BY ' . $line;
        }

        if (isset($o_having) && $filter = $this->criteria($o_having)) {
            $sql .= $lf . 'HAVING ' . array_shift($filter);

            array_push($values, ...$filter);
        }

        if (isset($o_orders) && $line = $this->orders($o_orders, $prefix, $lf)) {
            $sql .= $lf . 'ORDER BY ' . $line;
        }

        if ($line = $this->offset($o_limit ?? 0, $o_offset ?? 0, $sql, $top)) {
            $sql = $top ? $lf . $line . $sql : $sql . $lf . $line;
        }

        return array('SELECT' . $sql, $values);
    }

    public function insert(string $table, array $data): array
    {
        return array(
            'INSERT INTO ' . $this->quote($table) . $this->delimiter .
            '(' . $this->columns(array_keys($data), null, $this->delimiter) . ')' . $this->delimiter .
            'VALUES' . $this->delimiter .
            '(' . str_repeat('?, ', count($data) - 1) . '?)',
            array_values($data),
        );
    }

    public function update(string $table, array $data, array|string $criteria): array
    {
        $values = array_values($data);
        $filter = $this->criteria($criteria);
        $withFilter = $filter && isset($filter[0]) ? $this->delimiter . 'WHERE ' . $filter[0] : null;

        array_push($values, ...array_slice($filter, 1));

        return array(
            'UPDATE ' . $this->quote($table) . $this->delimiter .
            'SET ' . implode(' = ?,' . $this->delimiter, array_map(array($this, 'quote'), array_keys($data))) . ' = ?' .
            $withFilter,
            $values,
        );
    }

    public function delete(string $table, array|string $criteria): array
    {
        $values = $this->criteria($criteria);
        $filter = array_shift($values);
        $withFilter = $filter ? $this->delimiter . 'WHERE ' . $filter : null;

        return array(
            'DELETE FROM ' . $this->quote($table) . $withFilter,
            $values,
        );
    }

    public function insertBatch(string $table, array $data): array
    {
        $first = $data[0] ?? null;

        if (!$first || !is_array($first)) {
            throw new \LogicException('No data to be inserted');
        }

        $firstCount = count($first);
        $columns = array_keys($first);
        $line = '(' . str_repeat('?, ', $firstCount - 1) . '?)';

        $sql = 'INSERT INTO ' . $this->quote($table) . $this->delimiter . '(' . $this->columns($columns) . ')' . $this->delimiter . 'VALUES ' . $line;
        $values = array();

        foreach ($data as $pos => $row) {
            if (count(array_intersect_key($first, $row)) !== $firstCount) {
                throw new \LogicException(sprintf('Invalid data at position: %s', $pos));
            }

            if ($pos > 0) {
                $sql .= "," . $this->delimiter . $line;
            }

            array_push($values, ...each($columns, fn($column) => $row[$column], false));
        }

        return array($sql, $values);
    }

    public function isRaw(string $expr, string &$cut = null): bool
    {
        $raw = str_starts_with($expr, $this->rawIdentifier);

        if ($raw) {
            $cut = substr($expr, strlen($this->rawIdentifier));
        }

        return $raw;
    }

    public function raw(string $expr): string
    {
        return $this->rawIdentifier . $expr;
    }

    public function expr(string $expr, string $prefix = null): string
    {
        return $this->isRaw($expr, $cut) ? $cut : $this->quote((false === strpos($expr, '.') && $prefix ? $prefix . '.' : null) . $expr);
    }

    public function columns(string|array $columns, string $prefix = null, string $separator = ' '): string
    {
        return implode(',' . $separator, each((array) $columns, function ($column, $alias) use ($prefix) {
            if (is_numeric($alias)) {
                return $this->expr($column, $prefix);
            }

            return $this->expr($column, $prefix) . ' AS ' . $this->quote($alias);
        }, false));
    }

    public function joins(string|array $joins, string $separator = "\n"): string
    {
        return implode($separator, each((array) $joins, function ($join) {
            if (preg_match('/^(.+)join/i', $join)) {
                return $join;
            }

            return 'JOIN ' . $join;
        }, false));
    }

    public function criteria(string|array $criteria): array
    {
        return (array) $criteria;
    }

    public function orders(string|array $orders, string $prefix = null, string $separator = ' '): string
    {
        return implode(',' . $separator, each((array) $orders, function ($direction, $column) use ($prefix) {
            if (is_numeric($column)) {
                return $this->expr($direction, $prefix);
            }

            return $this->expr($column, $prefix) . ' ' . strtoupper($direction);
        }, false));
    }

    public function offset(int $limit, int $offset, string $sql, bool &$top = null): string|null
    {
        if ($limit <= 0 && $offset <= 0) {
            return null;
        }

        return match($this->driver) {
            'sqlsrv' => $this->offsetSqlServer($limit, $offset, $sql, $top),
            default => trim(($limit ? 'LIMIT ' . $limit : '') . ' ' . ($offset ? 'OFFSET ' . $offset : '')),
        };
    }

    protected function offsetSqlServer(int $limit, int $offset, string $sql, bool &$top = null): string
    {
        if (!preg_match('/order by/i', $sql)) {
            throw new \LogicException('Offsetting require column order');
        }

        $top = !$offset;

        if ($top) {
            return 'TOP ' . $limit;
        }

        return 'OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $limit . ' ROWS ONLY';
    }
}