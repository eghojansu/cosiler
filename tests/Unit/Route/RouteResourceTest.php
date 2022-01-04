<?php

namespace Ekok\Cosiler\Tests\Route;

use Ekok\Cosiler\Route;
use Ekok\Cosiler\Tests\Fixture\ScopedTestCase;

final class RouteResourceTest extends ScopedTestCase
{
    public function testIndexResource()
    {
        $this->expectOutputString('resources.index');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/resources';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateResource()
    {
        $this->expectOutputString('resources.create');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/resources/create';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    public function testStoreResource()
    {
        $this->expectOutputString('resources.store bar');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/resources';
        $_POST['foo'] = 'bar';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    public function testShowResource()
    {
        $this->expectOutputString('resources.show 8');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/resources/8';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    public function testEditResource()
    {
        $this->expectOutputString('resources.edit 8');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/resources/8/edit';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    public function testUpdateResource()
    {
        $this->expectOutputString('resources.update 8');

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/resources/8';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    public function testDestroyResource()
    {
        $this->expectOutputString('resources.destroy 8');

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/resources/8';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources');
    }

    /**
     * @runInSeparateProcess
     */
    public function testOverrideIdentity()
    {
        $this->expectOutputString('resources.edit foo');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/resources/foo/edit';
        $_SERVER['SCRIPT_NAME'] = '/test/index.php';

        Route\resource('/resources', TEST_FIXTURES . '/route_resources/slug', 'slug');
    }
}
