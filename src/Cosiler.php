<?php

declare(strict_types=1);

namespace Ekok\Cosiler;


function fixslashes(string $str): string
{
    return \strtr($str, array(
        '\\' => '/',
        '//' => '/',
    ));
}

function split(string $content, string $symbols = ',;|'): array
{
    return \array_map('trim', \preg_split('/['.\preg_quote($symbols, '/').']/i', $content, PREG_SPLIT_NO_EMPTY));
}

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

        $update[\array_shift($result)] = \array_shift($result);
    }

    return $update;
}

/**
 * Perform callback to array with value and key.
 */
function each(array $items, callable $callback, bool $keepKeys = true, bool $skipNulls = false): array
{
    $update = array();

    foreach ($items as $key => $value) {
        $result = $callback($value, $key, $update, $items);

        if ($skipNulls && null === $result) {
            continue;
        }

        if ($keepKeys) {
            $update[$key] = $value;
        } else {
            $update[] = $value;
        }
    }

    return $update;
}
