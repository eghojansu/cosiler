<?php

namespace Ekok\Cosiler\Test\Unit\Route;

use EKok\Cosiler\Route;
use Ekok\Cosiler\Test\Fixture\ScopedTestCase;
use Ekok\Cosiler\Test\Fixture\Route\RouteClass;

class RouteClassNameTest extends ScopedTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Route\purge_match();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        Route\resume();
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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testPostFoo()
    {
        $this->expectOutputString('className.postFoo');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/class-name/foo';
        Route\class_name('/class-name', RouteClass::class);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
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
