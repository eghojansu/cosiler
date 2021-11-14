<?php

namespace Ekok\Cosiler\Test\Unit\Http;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Http;

final class HttpTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_REQUEST = $_COOKIE = $_SESSION = ['foo' => 'bar'];

        $_SERVER['HTTP_HOST'] = 'test:8000';
        $_SERVER['SCRIPT_NAME'] = '/foo/test.php';
        $_SERVER['REQUEST_URI'] = '/bar/baz?foo=bar';
    }

    public function testHttp()
    {
        $this->assertSame($_COOKIE, Http\cookie());
        $this->assertSame('bar', Http\cookie('foo'));
        $this->assertSame(null, Http\cookie('bar'));

        $this->assertSame($_SESSION, Http\cookie());
        $this->assertSame('bar', Http\session('foo'));
        $this->assertNull(Http\session('bar'));
        $this->assertSame('baz', Http\session('bar', 'baz'));
        $this->assertSame('baz', Http\flash('bar'));
        $this->assertNull(Http\session('bar'));

        Http\session_end();

        $this->assertSame('/foo/', Http\url());
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
}
