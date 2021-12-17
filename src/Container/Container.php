<?php

/**
 * Framework variables hive.
 */

declare(strict_types=1);

namespace Ekok\Cosiler\Container;

use function Ekok\Cosiler\map;
use function Ekok\Cosiler\ref;
use function Ekok\Cosiler\walk;


/**
 * Get or Set a value in the container for consiler namespace.
 *
 * @param mixed $key   Identified by the given key
 * @param mixed $value The value to be stored
 */
function co($key, ...$value)
{
    $box = Box::instance();

    if ($value) {
        $box->hive['co'][$key] = $value[0];
    }

    return $box->hive['co'][$key] ?? null;
}

/**
 * Checks if there is some value in the given $key.
 *
 * @param mixed $key key to search in the Container
 */
function has($key): bool
{
    $box = Box::instance();

    return (
        ref($key, $box->hive, false, $exists) || $exists
        || isset($box->rules[$key])
        || isset($box->protected[$key])
    );
}

/**
 * Get a value from the container.
 *
 * @param mixed $key The key to be searched on the container
 *
 * @return mixed
 */
function get($key)
{
    $box = Box::instance();

    return ref($key, $box->hive) ?? $box->protected[$key] ?? make($key, false) ?? null;
}

/**
 * Set a value in the container.
 *
 * @param mixed $key   Identified by the given key
 * @param mixed $value The value to be stored
 */
function set($key, $value): void
{
    $box = Box::instance();

    if ($value instanceof \Closure || (is_array($value) && \is_callable($value))) {
        $box->rules[$key] = $value;
    } else {
        $var = &ref($key, $box->hive, true);
        $var = $value;
    }
}

/**
 * Clears the value on the container.
 *
 * @param mixed $key
 */
function clear($key): void
{
    $box = Box::instance();

    unset($box->rules[$key], $box->protected[$key], $box->factories[$key]);

    if (false === $pos = strrpos($key, '.')) {
        unset($box->hive[$key]);

        return;
    }

    $root = substr($key, 0, $pos);
    $leaf = substr($key, $pos + 1);
    $var = &ref($root, $box->hive, true);

    if (is_array($var) || $var instanceof \ArrayAccess) {
        unset($var[$leaf]);
    } elseif (is_object($var) && is_callable($remove = array($var, 'remove' . $leaf))) {
        $remove();
    } elseif (is_object($var) && isset($var->$leaf)) {
        unset($var->$leaf);
    } else {
        throw new \LogicException(sprintf('Unable to clear value of %s', $key));
    }
}

function has_some(...$keys): bool
{
    return array_reduce($keys, fn ($found, $key) => $found || has($key));
}

function has_all(...$keys): bool
{
    return array_reduce($keys, fn ($found, $key) => $found && has($key), true);
}

function get_all(array $keys): array
{
    return map($keys, fn($key, $alias) => array(is_numeric($alias) ? $key : $alias, get($key)));
}

function set_all(array $values, string $prefix = null): void
{
    walk($values, fn($value, $key) => set($prefix . $key, $value));
}

function clear_all(...$keys): void
{
    walk($keys, fn($key) => clear($key));
}

function push($key, $value): array
{
    $data = get($key);

    if (!\is_array($data)) {
        $data = (array) $data;
    }

    $data[] = $value;

    set($key, $data);

    return $data;
}

function pop($key)
{
    $data = get($key);

    if (\is_array($data)) {
        $value = \array_pop($data);

        set($key, $data);

        return $value;
    }

    clear($key);

    return $data;
}

function unshift($key, $value): array
{
    $data = get($key);

    if (!\is_array($data)) {
        $data = (array) $data;
    }

    \array_unshift($data, $value);

    set($key, $data);

    return $data;
}

function shift($key)
{
    $data = get($key);

    if (\is_array($data)) {
        $value = \array_shift($data);

        set($key, $data);

        return $value;
    }

    clear($key);

    return $data;
}

function protect($key, callable $value): void
{
    Box::instance()->protected[$key] = $value;
}

function factory($key, callable $value): void
{
    $box = Box::instance();

    $box->rules[$key] = $value;
    $box->factories[$key] = true;
}

function make($key, bool $throw = true)
{
    $box = Box::instance();
    $rule = $box->rules[$key] ?? null;

    if (!$rule) {
        if ($throw) {
            throw new \LogicException(sprintf('No rule defined for: "%s"', $key));
        }

        return null;
    }

    if (isset($box->factories[$key])) {
        return $rule();
    }

    $instance = &ref($key, $box->hive, true);

    return $instance ?? ($instance = $rule());
}

function merge(array $config): void
{
    walk($config, fn($value, $key) => set($key, $value));
}

function config(string ...$files): void
{
    walk($files, fn($file) => \is_file($file) && \is_array($config = require $file) && merge($config));
}

function with(string $key, \Closure $cb = null)
{
    return $cb ? $cb(get($key)) : get($key);
}
