<?php

namespace Ekok\Cosiler\Test\Unit\Http;

use Ekok\Cosiler\Http;
use Ekok\Cosiler\Http\HttpException;
use Ekok\Cosiler\Test\Fixture\ScopedTestCase;

final class HttpTest extends ScopedTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_GET = array('get' => 'foo');
        $_POST = array('post' => 'foo');
        $_REQUEST = array('request' => 'foo');
        $_COOKIE = array('cookie' => 'foo');

        $_SERVER['HTTP_HOST'] = 'test:8000';
        $_SERVER['SCRIPT_NAME'] = '/foo/test.php';
        $_SERVER['REQUEST_URI'] = '/bar/baz?foo=bar';
    }

    public function testHttp()
    {
        $this->assertSame($_COOKIE, Http\cookie());
        $this->assertSame('foo', Http\cookie('cookie'));
        $this->assertSame(null, Http\cookie('bar'));

        $this->assertSame(array(), Http\session());
        $this->assertNull(Http\session('bar'));
        $this->assertSame('baz', Http\session('bar', 'baz'));
        $this->assertSame(array('bar' => 'baz'), Http\session());
        $this->assertSame('baz', Http\flash('bar'));
        $this->assertNull(Http\session('bar'));

        $this->assertSame('/foo', Http\base_path());
        $this->assertSame('/foo/bar', Http\base_path('/bar'));
        $this->assertSame('test.php', Http\entry());
        $this->assertSame('http', Http\scheme());
        $this->assertSame('test', Http\host());
        $this->assertSame('', Http\port());
        $this->assertSame('/foo/test.php', Http\path());
        $this->assertSame('/foo/test.php/foo', Http\path('/foo'));
        $this->assertSame('http://test/foo', Http\base_url());
        $this->assertSame('http://test/foo/bar', Http\base_url('/bar'));
        $this->assertSame('http://test/foo/test.php', Http\url());
        $this->assertSame('http://test/foo/test.php/bar', Http\url('/bar'));
        $this->assertSame('http://test/foo/bar', Http\asset('/bar'));

        Http\session_end();
        Http\set_base_path('/update');
        Http\set_entry('update.php');
        Http\set_host('update');
        Http\set_scheme('https');
        Http\set_port('8000');
        Http\set_asset('assets');

        $this->assertSame('/update', Http\base_path());
        $this->assertSame('update.php', Http\entry());
        $this->assertSame('https', Http\scheme());
        $this->assertSame('update', Http\host());
        $this->assertSame('8000', Http\port());
        $this->assertSame('/update/update.php', Http\path());
        $this->assertSame('/update/update.php/foo', Http\path('/foo'));
        $this->assertSame('https://update:8000/update', Http\base_url());
        $this->assertSame('https://update:8000/update/update.php', Http\url());
        $this->assertSame('https://update:8000/update/assets/bar', Http\asset('/bar'));
    }

    public function testStatus()
    {
        $this->assertSame('OK', Http\status(200));

        $this->expectExceptionMessage('Unsupported HTTP code: 999');
        Http\status(999);
    }

    /** @dataProvider errorsProvider */
    public function testErrors(string $expected, string $fn, ...$args)
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($expected);

        ('Ekok\\Cosiler\\Http\\' . $fn)(...$args);
    }

    public function errorsProvider()
    {
        return array(
            'general' => array('Internal Server Error', 'error'),
            'unprocessable' => array('Unprocessable Entity', 'unprocessable'),
            'not_allowed' => array('Method Not Allowed', 'not_allowed'),
            'not_found' => array('Not Found', 'not_found'),
            'forbidden' => array('Forbidden', 'forbidden'),
            'unauthorized' => array('Unauthorized', 'unauthorized'),
            'bad_request' => array('Bad Request', 'bad_request'),
        );
    }
}
