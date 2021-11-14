<?php

declare(strict_types=1);

namespace Ekok\Cosiler;

/**
 * Returns a function that requires the given filename.
 *
 * @param string $filename The file to be required
 *
 * @return \Closure(string[]):(false|mixed|null)
 */
function require_fn(string $filename): \Closure
{
    return static function (array $params = array()) use ($filename) {
        if (!\file_exists($filename)) {
            return null;
        }

        $value = Container\co($filename) ?? Container\co($filename, include_once $filename);

        if (\is_callable($value)) {
            $call = $value;
            $value = $call($params);

            // Cache result
            Container\co($filename, $value);
        }

        return $value;
    };
}

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

function fixslashes(string $str): string
{
    return \strtr($str, array('\\' => '/', '//' => '/'));
}

function split(string $str, string $symbols = ',;|'): array
{
    return \array_filter(\array_map('trim', \preg_split('/[' . $symbols . ']/i', $str, 0, PREG_SPLIT_NO_EMPTY)));
}
