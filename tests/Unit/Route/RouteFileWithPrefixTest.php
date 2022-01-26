<?php

use Ekok\Cosiler\Route;

use function Ekok\Cosiler\storage_reset;

final class RouteFileWithPrefixTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        storage_reset();
    }

    public function testGetIndex()
    {
        $this->expectOutputString('index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/';

        Route\files(TEST_DATA . '/route_files/', '/foo');
    }

    public function testGetContact()
    {
        $this->expectOutputString('contact.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/contact';

        Route\files(TEST_DATA . '/route_files/', '/foo');
    }

    public function testPostContact()
    {
        $this->expectOutputString('contact.post');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/foo/contact';

        Route\files(TEST_DATA . '/route_files/', '/foo');
    }

    public function testGetAbout()
    {
        $this->expectOutputString('about.index.get');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/about';

        Route\files(TEST_DATA . '/route_files/', '/foo');
    }

    public function testGetWithParam()
    {
        $this->expectOutputString('foo.$8.get');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/foo/8';

        Route\files(TEST_DATA . '/route_files/', '/foo');
    }
}
