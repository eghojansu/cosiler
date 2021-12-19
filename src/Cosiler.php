<?php

declare(strict_types=1);

namespace Ekok\Cosiler;

use function Ekok\Cosiler\Utils\Arr\walk;
use function Ekok\Cosiler\Utils\Str\split;

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

function &ref($key, array &$ref, bool $add = false, bool &$exists = null, array &$parts = null)
{
    if ($add) {
        $var = &$ref;
    } else {
        $var = $ref;
    }


    if (
        ($exists = isset($var[$key]) || array_key_exists($key, $var))
        || !is_string($key)
        || false === strpos($key, '.')
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

        if (($arr = is_array($var)) || $var instanceof \ArrayAccess) {
            $exists = isset($var[$part]) || ($arr && array_key_exists($part, $var));
            $var = &$var[$part];
        } elseif (is_object($var) && is_callable($get = array($var, 'get' . $part))) {
            $exists = true;
            $var = $get();
        } elseif (is_object($var)) {
            $exists = isset($var->$part);
            $var = &$var->$part;
        } else {
            $exists = false;
            $var = $nulls;

            break;
        }
    }

    return $var;
}

function cast(string $value): int|float|bool|string|array|null
{
    $val = trim($value);

    if (preg_match('/^(?:0x[0-9a-f]+|0[0-7]+|0b[01]+)$/i', $val)) {
        return intval($val, 0);
    }

    if (is_numeric($val)) {
        return $val * 1;
    }

    if (preg_match('/^\w+$/i', $val) && defined($val)) {
        return constant($val);
    }

    return $val;
}
