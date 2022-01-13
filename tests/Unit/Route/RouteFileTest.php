<?php

namespace Ekok\Cosiler\Tests\Route;

use Ekok\Cosiler\Route;
use Ekok\Cosiler\Tests\Fixture\ScopedTestCase;

final class RouteFileTest extends ScopedTestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetIndex()
    {
        $this->expectOutputString('index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        Route\files(TEST_FIXTURES . '/route_files/');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetContact()
    {
        $this->expectOutputString('contact.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/contact';

        Route\files(TEST_FIXTURES . '/route_files/');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testPostContact()
    {
        $this->expectOutputString('contact.post');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/contact';

        Route\files(TEST_FIXTURES . '/route_files/');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testGetAbout()
    {
        $this->expectOutputString('about.index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/about';

        Route\files(TEST_FIXTURES . '/route_files/');
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
        $_SERVER['REQUEST_URI'] = '/foo/8';

        Route\files(TEST_FIXTURES . '/route_files/');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testReturns()
    {
        $_SERVER['REQUEST_URI'] = '/returns';

        $expected = 'I am "something" to returns';
        $actual = Route\files(TEST_FIXTURES . '/route_files/');

        $this->assertEquals($expected, $actual);
        $this->assertTrue(Route\did_match());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testReturnsFromGlobals()
    {
        $_SERVER['REQUEST_URI'] = '/returns';
        Route\globals_add('returns_back', "It's returning back");

        $expected = "It's returning back";
        $actual = Route\files(TEST_FIXTURES . '/route_files/');

        $this->assertEquals($expected, $actual);
        $this->assertTrue(Route\did_match());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testNotFound()
    {
        $_SERVER['REQUEST_URI'] = '/not-exists-route';
        $actual = Route\files(TEST_FIXTURES . '/route_files/');

        $this->assertNull($actual);
        $this->assertFalse(Route\did_match());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState  disabled
     */
    public function testNotExists()
    {
        $this->expectException(\InvalidArgumentException::class);
        Route\files('path/does/not/exists');
    }
}
