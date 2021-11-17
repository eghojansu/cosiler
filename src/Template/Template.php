<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Template;

use Ekok\Cosiler;
use Ekok\Cosiler\Container;

define('TEMPLATE_DIRECTORIES', 'template_directories');

function directory(string $directories): void
{
    Container\co(TEMPLATE_DIRECTORIES, Cosiler\split($directories));
}

function locate(string $template): string
{
    $directories = Container\co(TEMPLATE_DIRECTORIES) ?? array();
    $finder = fn($dir) => is_file($file = $dir . '/' . $template) || is_file($file = $dir . '/' . $template . '.php') ? $file : null;
    $found = Cosiler\first($directories, $finder);

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
