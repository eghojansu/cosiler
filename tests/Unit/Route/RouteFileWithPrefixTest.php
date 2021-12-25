<?php

namespace Ekok\Cosiler\Test\Unit\Route;

use Ekok\Cosiler\Route;
use Ekok\Cosiler\Test\Fixture\ScopedTestCase;

final class RouteFileWithPrefixTest  extends ScopedTestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetIndex()
    {
        $this->expectOutputString('index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/';

        Route\files(TEST_FIXTURES . '/route_files/', '/foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetContact()
    {
        $this->expectOutputString('contact.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/contact';

        Route\files(TEST_FIXTURES . '/route_files/', '/foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testPostContact()
    {
        $this->expectOutputString('contact.post');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/foo/contact';

        Route\files(TEST_FIXTURES . '/route_files/', '/foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetAbout()
    {
        $this->expectOutputString('about.index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/about';

        Route\files(TEST_FIXTURES . '/route_files/', '/foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetWithParam()
    {
        $this->expectOutputString('foo.$8.get');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/foo/8';

        Route\files(TEST_FIXTURES . '/route_files/', '/foo');
    }
}
