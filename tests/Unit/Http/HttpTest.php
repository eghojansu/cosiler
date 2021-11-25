<?php

namespace Ekok\Cosiler\Test\Unit\Http;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Http;
use Ekok\Cosiler\Http\HttpException;

final class HttpTest extends TestCase
{
    protected function setUp(): void
    {
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

        Http\session_end();

        $this->assertSame('/foo/bar/baz', Http\url());
        $this->assertSame('/foo/', Http\url(''));
        $this->assertSame('/foo/bar', Http\url('/bar'));
        $this->assertSame('/bar/baz', Http\path());

        $this->assertSame('http://test:8000/bar/baz', Http\uri());
    }

    /**
     * @runInSeparateProcess
     */
    public function testNotInSubFolderPath()
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/foo/bar';

        $this->assertSame('/foo/bar', Http\path());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubFolderPathRepeats()
    {
        $_SERVER['REQUEST_URI'] = '/bar/foo/baz';

        $this->assertSame('/bar/foo/baz', Http\path());
    }

    public function testFuzzyQueryString()
    {
        $_SERVER['QUERY_STRING'] = 'baz=qux&foo=bar';
        $this->assertSame('/bar/baz', Http\path());
    }

    public function testEmptyRequestUri()
    {
        $_SERVER['REQUEST_URI'] = '';
        $this->assertSame('/', Http\path());

        $_SERVER['REQUEST_URI'] = '/';
        $this->assertSame('/', Http\path());

        $_SERVER['REQUEST_URI'] = '?foo=bar';
        $this->assertSame('/', Http\path());

        $_SERVER['REQUEST_URI'] = '/?foo=bar';
        $this->assertSame('/', Http\path());
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
