<?php

namespace Ekok\Cosiler\Tests\Fixture;

use PHPUnit\Framework\TestCase;

use function Ekok\Cosiler\storage;

abstract class ScopedTestCase extends TestCase
{
    private $globals = array();

    protected function setUp(): void
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

        storage(null, 'RESET');
    }

    protected function tearDown(): void
    {
        foreach ($this->globals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
}
