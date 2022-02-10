<?php

use Ekok\Cosiler\Route;

use function Ekok\Cosiler\storage_reset;

class RouteClassNameTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        storage_reset();
    }

    public function testIndex()
    {
        $this->expectOutputString('className.index');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/class-name';
        Route\class_name('/class-name', RouteClass::class);
    }

    public function testNotFound()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/class-name/none';

        $actual = Route\class_name('/class-name', RouteClass::class);

        $this->assertNull($actual);
    }

    public function testPostFoo()
    {
        $this->expectOutputString('className.postFoo');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/class-name/foo';
        Route\class_name('/class-name', RouteClass::class);
    }

    public function testPutFooBar()
    {
        $this->expectOutputString('className.putFooBar');
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/class-name/foo/bar';
        Route\class_name('/class-name', RouteClass::class);
    }

    public function testAnyParams()
    {
        $this->expectOutputString('className.baz.qux');
        $_SERVER['REQUEST_METHOD'] = 'ANYTHING';
        $_SERVER['REQUEST_URI'] = '/class-name/baz/qux';
        Route\class_name('/class-name', RouteClass::class);
    }
}
