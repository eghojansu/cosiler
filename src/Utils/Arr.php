<?php

namespace Ekok\Cosiler\Utils\Arr;

/**
 * Map array to new array pair.
 */
function map(iterable $items, callable $map): array
{
    $update = array();

    foreach ($items as $key => $value) {
        $result = $map($value, $key, $update, $items);

        if (!\is_array($result) || !isset($result[0])) {
            continue;
        }

        $update[$result[0]] = $result[1] ?? null;
    }

    return $update;
}

/**
 * Perform callback to array with value and key.
 */
function each(iterable $items, callable $callback, bool $keepKeys = true, bool $skipNulls = true): array
{
    $update = array();

    foreach ($items as $key => $value) {
        $result = $callback($value, $key, $update, $items);

        if ($skipNulls && null === $result) {
            continue;
        }

        if ($keepKeys) {
            $update[$key] = $result;
        } else {
            $update[] = $result;
        }
    }

    return $update;
}

function walk(iterable $items, callable $callback): void
{
    foreach ($items as $key => $value) {
        $callback($value, $key, $items);
    }
}

function first(iterable $items, callable $callback)
{
    foreach ($items as $key => $value) {
        if (null !== $result = $callback($value, $key, $items)) {
            return $result;
        }
    }

    return null;
}

function reduce(iterable $items, callable $callback, $carry = null, bool $useKey = false)
{
    foreach ($items as $key => $value) {
        $carry = $callback($carry, $useKey ? $key : $value, $useKey ? $value : $key, $items);
    }

    return $carry;
}

function merge(array|null ...$arr): array
{
    $result = array();

    foreach ($arr as $row) {
        foreach ($row ?? array() as $key => $value) {
            $result[$key] = $value;
        }
    }

    return $result;
}

function without(array|null $arr, string|int ...$keys): array
{
    return array_diff_key($arr ?? array(), array_fill_keys($keys, null));
}