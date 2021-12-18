<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Template;

use function Ekok\Cosiler\Container\co;
use function Ekok\Cosiler\Utils\Arr\first;
use function Ekok\Cosiler\Utils\Str\split;

define('TEMPLATE_DIRECTORIES', 'template_directories');

function directory(string $directories): void
{
    co(TEMPLATE_DIRECTORIES, split($directories));
}

function locate(string $template): string
{
    $directories = co(TEMPLATE_DIRECTORIES) ?? array();
    $finder = fn($dir) => is_file($file = $dir . '/' . $template) || is_file($file = $dir . '/' . $template . '.php') ? $file : null;
    $found = first($directories, $finder);

    if (!$found) {
        throw new \LogicException(sprintf('Template not found: "%s"', $template));
    }

    return $found;
}

function load(string $template, array $data = null): void
{
    static $loader;

    if (!$loader) {
        $loader = static function() {
            extract(func_get_arg(0));
            require locate(func_get_arg(1));
        };
    }

    $loader($data ?? array(), $template);
}
