<?php

namespace Ekok\Cosiler\Test\Fixture;

use PHPUnit\Framework\TestCase;

use function Ekok\Cosiler\Container\reset_state;

abstract class ScopedTestCase extends TestCase
{
    private $globals = array();

    public function setUp(): void
    {
        $this->globals = array_intersect_key($GLOBALS, array(
            '_GET' => true,
            '_POST' => true,
            '_FILES' => true,
            '_SERVER' => true,
            '_COOKIE' => true,
            '_REQUEST' => true,
            '_SESSION' => true,
        ));
        reset_state();
    }

    public function tearDown(): void
    {
        foreach ($this->globals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
}
