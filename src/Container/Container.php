<?php

/**
 * Framework variables hive.
 */

declare(strict_types=1);

namespace Ekok\Cosiler\Container;

use Ekok\Cosiler;

/**
 * Box storage.
 */
function box(): \stdClass
{
    static $box;

    if (!$box?->prepared) {
        $box = new \stdClass();
        $box->hive = array();
        $box->rules = array();
        $box->factories = array();
        $box->protected = array();
        $box->prepared = true;
    }

    return $box;
}

/**
 * Get or Set a value in the container for consiler namespace.
 *
 * @param mixed $key   Identified by the given key
 * @param mixed $value The value to be stored
 */
function co($key, ...$value)
{
    $box = box();

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
    $box = box();

    return (
        isset($box->hive[$key])
        || \array_key_exists($key, $box->hive)
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
    $box = box();

    return $box->hive[$key] ?? $box->protected[$key] ?? make($key, false) ?? null;
}

/**
 * Set a value in the container.
 *
 * @param mixed $key   Identified by the given key
 * @param mixed $value The value to be stored
 */
function set($key, $value): void
{
    $box = box();

    if ($value instanceof \Closure || (is_array($value) && \is_callable($value))) {
        $box->rules[$key] = $value;
    } else {
        $box->hive[$key] = $value;
    }
}

/**
 * Clears the value on the container.
 *
 * @param mixed $key
 */
function clear($key): void
{
    $box = box();

    unset($box->hive[$key], $box->rules[$key], $box->protected[$key], $box->factories[$key]);
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
    box()->protected[$key] = $value;
}

function factory($key, callable $value): void
{
    $box = box();

    $box->rules[$key] = $value;
    $box->factories[$key] = true;
}

function make($key, bool $throw = true)
{
    $box = box();
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

    return $box->hive[$key] ?? ($box->hive[$key] = $rule());
}

function merge(array $config): void
{
    Cosiler\walk($config, fn($value, $key) => set($key, $value));
}

function config(string ...$files): void
{
    Cosiler\walk($files, fn($file) => \is_file($file) && \is_array($config = require $file) && merge($config));
}
