<?php

/**
 * Framework variables hive.
 */

declare(strict_types=1);

namespace Ekok\Cosiler\Container;

/**
 * Box storage.
 */
function box(): \stdClass
{
    static $box;

    if (!$box) {
        $box = new \stdClass();
        $box->content = array();
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
        $box->content['co'][$key] = $value[0];
    }

    return $box->content['co'][$key] ?? null;
}

/**
 * Checks if there is some value in the given $key.
 *
 * @param mixed $key key to search in the Container
 */
function has($key): bool
{
    $box = box()->content;

    return isset($box[$key]) || \array_key_exists($key, $box);
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
    return box()->content[$key] ?? null;
}

/**
 * Set a value in the container.
 *
 * @param mixed $key   Identified by the given key
 * @param mixed $value The value to be stored
 */
function set($key, $value): void
{
    box()->content[$key] = $value;
}

/**
 * Clears the value on the container.
 *
 * @param mixed $key
 */
function clear($key): void
{
    unset(box()->content[$key]);
}

function dot(string $key)
{
    $var = box()->content;
    $parts = \explode('.', $key);

    foreach ($parts as $part) {
        if (\is_scalar($var) || null === $var) {
            $var = array();
        }

        if (\is_array($var) && (isset($var[$part]) || \array_key_exists($part, $var))) {
            $var = &$var;
        } else {
            $var = null;

            break;
        }
    }

    return $var;
}

function merge(array $config): void
{
    $box = box();

    foreach ($config as $key => $value) {
        $box->content[$key] = $value;
    }
}

function config(string ...$files): void
{
    foreach ($files as $file) {
        if (\is_readable($file) && \is_array($config = require $file)) {
            merge($config);
        }
    }
}
