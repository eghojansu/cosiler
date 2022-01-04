<?php

declare(strict_types=1);

namespace Ekok\Cosiler;

use Ekok\Utils\File;

function bootstrap(string $errorFile, string ...$appFiles): void
{
    $level = ob_get_level();

    try {
        array_walk($appFiles, fn($file) => require $file);
    } catch (\Throwable $error) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        require $errorFile;
    }
}

function require_fn(string $filename): \Closure
{
    return static fn (array $params = null) => is_callable($value = File::load($filename, compact('params'))) ? $value($params) : $value;
}

function storage(string $name = null, ...$sets)
{
    static $storage = array();
    static $default = null;

    if ($name) {
        if ($sets) {
            $storage[$name] = $sets[0];
        }

        return $storage[$name] ?? null;
    }

    if ($sets) {
        if ('RESET' === $sets[0]) {
            $storage = array();
            $default = null;
        } else {
            $default = $sets[0];
        }
    }

    return $default ? ($storage[$default] ?? null) : $storage;
}
