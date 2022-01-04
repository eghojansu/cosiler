<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Template;

use Ekok\Utils\Arr;
use Ekok\Utils\File;
use Ekok\Utils\Str;
use Ekok\Utils\Payload;
use function Ekok\Cosiler\storage;

define('TEMPLATE_DIRECTORIES', 'template_directories');

function directory(string $directories): void
{
    storage(TEMPLATE_DIRECTORIES, Str::split($directories));
}

function locate(string $template): string
{
    $found = Arr::first(
        storage(TEMPLATE_DIRECTORIES) ?? array(),
        fn(Payload $dir) => is_file($file = $dir->value . '/' . $template) || is_file($file = $dir->value . '/' . $template . '.php') ? $file : null,
    );

    if (!$found) {
        throw new \LogicException(sprintf('Template not found: "%s"', $template));
    }

    return $found;
}

function load(string $template, array $data = null): void
{
    File::load(locate($template), $data);
}
