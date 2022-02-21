<?php

declare(strict_types=1);

use Ekok\Utils\Arr;
use Ekok\Utils\Str;
use Ekok\Container\Box;
use Ekok\Utils\Payload;

class Kernel
{
    protected $loadDirectory = array();
    protected $loadExtension = 'php';

    public function __construct(public Box|null $box = null)
    {
        if (!$box) {
            $this->box = new Box();
        }
    }

    public function getLoadDirectory(): array
    {
        return $this->loadDirectory;
    }

    public function setLoadDirectory(string|array $directories): static
    {
        $this->loadDirectory = array_map(
            static fn(string $dir) => rtrim(Str::fixslashes($dir), '/'),
            is_array($directories) ? $directories : $directories,
        );

        return $this;
    }

    public function getLoadExtension(): string
    {
        return $this->loadExtension;
    }

    public function setLoadExtension(string $extension): static
    {
        $this->loadExtension = $extension;

        return $this;
    }

    public function load(string $file, array $data = null, bool $safe = false, $defaults = null): mixed
    {
        $ext = '.' . $this->getLoadExtension();
        $add = '/' . $file;
        $found = file_exists($file) ? $file : Arr::first(
            $this->getLoadDirectory(),
            fn(Payload $dir) => file_exists($path = $dir->value . $add)
                || file_exists($path = $dir->value . $add . $ext)
                || file_exists($path = $dir->value . strtr($add, '.', '/')) ? $path : null,
        );

        if ($found) {
            return (static function () {
                extract(func_get_arg(1));

                return require func_get_arg(0);
            })($found, $data ?? array());
        }

        if ($safe) {
            return $defaults;
        }

        throw new \LogicException(sprintf('File not found: "%s"', $file));
    }
}
