<?php

namespace Ekok\Cosiler\Container;

final class Box
{
    private static $instance;

    public $hive = array();
    public $rules = array();
    public $factories = array();
    public $protected = array();

    public static function instance(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }

    public static function reset(): void
    {
        if (self::$instance) {
            self::$instance = null;
        }
    }
}
