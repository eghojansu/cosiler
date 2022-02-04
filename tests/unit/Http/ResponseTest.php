<?php

use Ekok\Cosiler\Http\Response;

use function Ekok\Cosiler\storage_reset;

final class ResponseTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        storage_reset();
    }

    public function testDefaultOutput()
    {
        $this->expectOutputString('');

        Response\output();

        $this->assertSame(204, http_response_code());

        if (function_exists('xdebug_get_headers')) {
            $this->assertContains('Content-Type: text/plain;charset=utf-8', xdebug_get_headers());
        }
    }

    public function testText()
    {
        $this->expectOutputString('foo');

        Response\text('foo');

        $this->assertSame(200, http_response_code());

        if (function_exists('xdebug_get_headers')) {
            $this->assertContains('Content-Type: text/plain;charset=utf-8', xdebug_get_headers());
        }
    }

    public function testHtml()
    {
        $this->expectOutputString('<a href="#"></a>');

        Response\html('<a href="#"></a>');

        $this->assertSame(200, http_response_code());

        if (function_exists('xdebug_get_headers')) {
            $this->assertContains('Content-Type: text/html;charset=utf-8', xdebug_get_headers());
        }
    }

    public function testJson()
    {
        $this->expectOutputString('{"foo":"bar","baz":true,"qux":2}');

        Response\json(['foo' => 'bar', 'baz' => true, 'qux' => 2]);

        $this->assertSame(200, http_response_code());

        if (function_exists('xdebug_get_headers')) {
            $this->assertContains('Content-Type: application/json;charset=utf-8', xdebug_get_headers());
        }
    }

    public function testJsonError()
    {
        $this->expectException(\JsonException::class);

        Response\json(fopen('php://input', 'r'));
    }

    public function testStatusCode()
    {
        $this->expectOutputString('{"error":true,"message":"test"}');

        Response\json(['error' => true, 'message' => 'test'], 400);

        $this->assertSame(400, http_response_code());
    }

    public function testHeader()
    {
        Response\header('X-Foo', 'foo');
        Response\header('X-Bar', 'bar');
        Response\header('X-Bar', 'baz', false);

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();

            $this->assertContains('X-Foo: foo', $headers);
            $this->assertContains('X-Bar: bar', $headers);
            $this->assertContains('X-Bar: baz', $headers);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testRedirect()
    {
        $_SERVER['SCRIPT_NAME'] = '/foo/index.php';

        Response\redirect('/bar', true);

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $this->assertContains('Location: http://localhost/foo/index.php/bar', $headers);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testBack()
    {
        $_SERVER['SCRIPT_NAME'] = '/foo/index.php';

        Response\back(null, null, true);

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $this->assertContains('Location: http://localhost/foo/index.php/', $headers);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testNoContent()
    {
        $this->expectOutputString('');
        Response\no_content();
        $this->assertSame(204, http_response_code());
    }

    public function testCors()
    {
        $_SERVER['REQUEST_METHOD'] = 'options';

        Response\cors();

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();

            $this->assertContains('Access-Control-Allow-Origin: *', $headers);
            $this->assertContains('Access-Control-Allow-Headers: Content-Type', $headers);
            $this->assertContains('Access-Control-Allow-Methods: GET, POST, PUT, DELETE', $headers);
            $this->assertContains('Access-Control-Allow-Credentials: true', $headers);
        } else {
            $this->assertTrue(true);
        }
    }
}
