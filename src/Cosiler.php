<?php

declare(strict_types=1);

namespace Ekok\Cosiler;

function bootstrap(string $errorFile, string ...$appFiles): void
{
    $level = ob_get_level();

    try {
        walk($appFiles, fn($file) => require $file);
    } catch (\Throwable $error) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        require $errorFile;
    }
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

function quote(string $text, string $open = '"', string $close = null, string $delimiter = '.'): string
{
    $a = $open;
    $b = $close ?? $a;

    return $a . str_replace($delimiter, $b . $delimiter . $a, $text) . $b;
}

function &ref($key, array &$var, bool &$exists = null, array &$parts = null)
{
    if (
        ($exists = isset($var[$key]) || array_key_exists($key, $var))
        || !is_string($key)
        || false !== strpos($key, '.')
    ) {
        $parts = array($key);
        $var = &$var[$key];

        return $var;
    }

    $parts = split($key, '.');
    $nulls = null;

    foreach ($parts as $part) {
        if (null === $var || is_scalar($var)) {
            $var = array();
        }

        $get = null;
        $found = null;

        if (($arr = is_array($var)) || $var instanceof \ArrayAccess) {
            $exists = isset($var[$part]) || ($arr && array_key_exists($part, $var));
            $var = &$var[$part];
        } elseif (is_object($var) && (isset($var->$part) || method_exists($var, $get = 'get' . $part))) {
            $exists = $found ?? false;

            if ($get) {
                $var = $var->$get();
            } else {
                $ref = &$ref->$part;
            }
        } else {
            $exists = false;
            $ref = $nulls;

            break;
        }
    }

    return $ref;
}
