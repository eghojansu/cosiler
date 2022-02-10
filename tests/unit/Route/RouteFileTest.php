<?php

use Ekok\Cosiler\Route;

use function Ekok\Cosiler\storage_reset;

final class RouteFileTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        storage_reset();
    }

    public function testGetIndex()
    {
        $this->expectOutputString('index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testGetContact()
    {
        $this->expectOutputString('contact.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/contact';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testPostContact()
    {
        $this->expectOutputString('contact.post');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/contact';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testGetAbout()
    {
        $this->expectOutputString('about.index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/about';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testGetWithParam()
    {
        $this->expectOutputString('foo.$8.get');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/8';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testReturns()
    {
        $_SERVER['REQUEST_URI'] = '/returns';

        $expected = 'I am "something" to returns';
        $actual = Route\files(TEST_DATA . '/route_files/');

        $this->assertEquals($expected, $actual);
        $this->assertTrue(Route\did_match());
    }

    public function testReturnsFromGlobals()
    {
        $_SERVER['REQUEST_URI'] = '/returns';
        Route\globals_add('returns_back', "It's returning back");

        $expected = "It's returning back";
        $actual = Route\files(TEST_DATA . '/route_files/');

        $this->assertEquals($expected, $actual);
        $this->assertTrue(Route\did_match());
    }

    public function testNotFound()
    {
        $_SERVER['REQUEST_URI'] = '/not-exists-route';
        $actual = Route\files(TEST_DATA . '/route_files/');

        $this->assertNull($actual);
        $this->assertFalse(Route\did_match());
    }

    public function testNotExists()
    {
        $this->expectException(\InvalidArgumentException::class);
        Route\files('path/does/not/exists');
    }

    public function testParamsEater()
    {
        $this->expectOutputString('complete path is: foo/bar/baz');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/all/foo/bar/baz';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testParamsEaterNotGiven()
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/all';

        $actual = Route\files(TEST_DATA . '/route_files/');

        $this->assertNull($actual);
        $this->assertFalse(Route\did_match());
    }

    public function testOptionalParamsEater()
    {
        $this->expectOutputString('complete path is: foo/bar/baz');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/all-none/foo/bar/baz';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testOptionalParamsEaterNotGiven()
    {
        $this->expectOutputString('complete path is: ');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/all-none';

        Route\files(TEST_DATA . '/route_files/');
    }

    public function testFileSkipped()
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/skip';

        $actual = Route\files(TEST_DATA . '/route_files/');

        $this->assertNull($actual);
        $this->assertFalse(Route\did_match());
    }
}
