<?php

declare(strict_types=1);

namespace Ekok\Cosiler\File;

function touchContent(string $path, string $content = null, int $permissions = 0775): bool
{
    if (\is_writable($path)) {
        if (null !== $content) {
            return (bool) \file_put_contents($path, $content);
        }

        return true;
    }

    if (!\is_dir($dir = \dirname($path))) {
       \mkdir($dir, $permissions, true);
    }

    if (null === $content) {
        return \touch($path);
    }

    return (bool) \file_put_contents($path, $content);
}
