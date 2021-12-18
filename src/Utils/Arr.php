<?php

namespace Ekok\Cosiler\Utils\Arr;

/**
 * Map array to new array pair.
 */
function map(array $items, callable $map): array
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
function each(array $items, callable $callback, bool $keepKeys = true, bool $skipNulls = true): array
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

function walk(array $items, callable $callback): void
{
    foreach ($items as $key => $value) {
        $callback($value, $key, $items);
    }
}

function first(array $items, callable $callback)
{
    foreach ($items as $key => $value) {
        if (null !== $result = $callback($value, $key, $items)) {
            return $result;
        }
    }

    return null;
}
