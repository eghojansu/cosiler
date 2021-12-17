<?php

namespace Ekok\Cosiler\Test\Unit\Http\Request;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Http\Request;

final class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_REQUEST = $_COOKIE = $_SESSION = ['foo' => 'bar'];
        $_FILES = [
            'foo' => [
                'name' => 'bar',
            ],
        ];

        $_SERVER['HTTP_HOST'] = 'test:8000';
        $_SERVER['SCRIPT_NAME'] = '/foo/test.php';
        $_SERVER['PATH_INFO'] = '/bar/baz';
        $_SERVER['NON_HTTP'] = 'Ignore me';
        $_SERVER['CONTENT_TYPE'] = 'phpunit/test';
        $_SERVER['CONTENT_LENGTH'] = '123';
    }

    public function testRaw()
    {
        $rawContent = Request\raw(TEST_FIXTURES . '/php_input.txt');

        $this->assertSame('foo=bar', $rawContent);
    }

    public function testParams()
    {
        $params = Request\params(TEST_FIXTURES . '/php_input.txt');

        $this->assertArrayHasKey('foo', $params);
        $this->assertContains('bar', $params);
        $this->assertCount(1, $params);
        $this->assertArrayHasKey('foo', $params);
        $this->assertSame('bar', $params['foo']);
    }

    public function testJson()
    {
        $params = Request\json(TEST_FIXTURES . '/php_input.json');

        $this->assertArrayHasKey('foo', $params);
        $this->assertContains('bar', $params);
        $this->assertCount(1, $params);
        $this->assertArrayHasKey('foo', $params);
        $this->assertSame('bar', $params['foo']);
    }

    public function testBodyParseJson()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $params = Request\body_parse(TEST_FIXTURES . '/php_input.json');

        $this->assertArrayHasKey('foo', $params);
        $this->assertContains('bar', $params);
        $this->assertCount(1, $params);
        $this->assertArrayHasKey('foo', $params);
        $this->assertSame('bar', $params['foo']);
    }

    public function testGet()
    {
        $this->assertSame($_GET, Request\get());
        $this->assertSame('bar', Request\get('foo'));
        $this->assertNull(Request\get('baz'));
    }

    public function testPost()
    {
        $this->assertSame($_POST, Request\post());
        $this->assertSame('bar', Request\post('foo'));
        $this->assertNull(Request\post('baz'));
    }

    public function testPostBodyParse()
    {
        $data = Request\body_parse();

        $this->assertSame($_POST, $data);
        $this->assertSame('bar', $data['foo']);
    }

    public function testInput()
    {
        $this->assertSame($_REQUEST, Request\input());
        $this->assertSame('bar', Request\input('foo'));
        $this->assertNull(Request\input('baz'));
    }

    public function testFile()
    {
        $this->assertSame($_FILES, Request\file());
        $this->assertSame(['name' => 'bar'], Request\file('foo'));
        $this->assertNull(Request\file('baz'));
    }

    public function testServer()
    {
        $this->assertSame($_SERVER, Request\server());
        $this->assertSame('test:8000', Request\server('HTTP_HOST'));
        $this->assertNull(Request\server('baz'));
    }

    public function testHeaders()
    {
        $headers = Request\headers();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Content-Length', $headers);
        $this->assertArrayHasKey('Host', $headers);
        $this->assertContains('phpunit/test', $headers);
        $this->assertContains('test:8000', $headers);
        $this->assertCount(3, $headers);

        $expectedHeaders = [
            'Content-Type' => 'phpunit/test',
            'Content-Length' => '123',
            'Host' => 'test:8000'
        ];

        foreach ($expectedHeaders as $key => $value) {
            $this->assertArrayHasKey($key, $headers);
            $this->assertSame($value, $headers[$key]);
        }
    }

    public function testHeader()
    {
        $contentType = Request\headers('Content-Type');
        $this->assertSame('phpunit/test', $contentType);
    }

    public function testHeaderExists()
    {
        $this->assertTrue(Request\header_exists('Content-Type'));
        $this->assertTrue(Request\header_exists('content-type'));
        $this->assertFalse(Request\header_exists('content-types'));
    }

    public function testMethod()
    {
        $this->assertSame('GET', Request\method());

        $_POST['_method'] = 'POST';

        $this->assertSame('POST', Request\method());

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $this->assertSame('PUT', Request\method());

        unset($_POST['_method']);
        unset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    public function testRequestMethodIs()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Request\method_is('post'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue(Request\method_is('get'));

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->assertTrue(Request\method_is('put'));

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->assertTrue(Request\method_is('delete'));

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->assertTrue(Request\method_is('options'));

        $_SERVER['REQUEST_METHOD'] = 'CUSTOM';
        $this->assertTrue(Request\method_is('custom'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue(Request\method_is(['get', 'post']));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Request\method_is(['get', 'post']));

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->assertFalse(Request\method_is(['get', 'post']));
    }

    public function testAcceptedLocales()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.5';
        $this->assertSame('en-US', array_keys(Request\accepted_locales())[0]);

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertEmpty(Request\accepted_locales());
    }

    public function testRecommendedLocale()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.5';
        $this->assertSame('en-US', Request\recommended_locale());

        $_GET['lang'] = 'fr';
        $this->assertSame('fr', Request\recommended_locale());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertSame('fr', Request\recommended_locale());

        unset($_GET['lang']);
        $this->assertSame('it', Request\recommended_locale('it'));

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.5';
        $this->assertSame('en-US', Request\recommended_locale('it'));

        if (function_exists('locale_get_default')) {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
            $this->assertSame(locale_get_default(), Request\recommended_locale());
        }
    }

    public function testAuthorizationHeader()
    {
        $this->assertNull(Request\authorization_header());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic foo';
        $this->assertSame('Basic foo', Request\authorization_header());
    }

    public function testBearer()
    {
        $this->assertNull(Request\bearer());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic foo';
        $this->assertNull(Request\bearer());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer foo';
        $this->assertSame('foo', Request\bearer());
    }

    public function testContentType()
    {
        $this->assertSame('phpunit/test', Request\content_type());
    }

    public function testIsJson()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json;charset=utf8';
        $this->assertTrue(Request\is_json());

        $_SERVER['CONTENT_TYPE'] = '';
        $this->assertFalse(Request\is_json());
    }

    public function testIsMultipart()
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data ----foobarbaz';
        $this->assertTrue(Request\is_multipart());

        $_SERVER['CONTENT_TYPE'] = '';
        $this->assertFalse(Request\is_multipart());
    }

    public function testUserAgent()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'test';
        $this->assertSame('test', Request\user_agent());
    }

    /** @dataProvider acceptProvider */
    public function testAccept($expected, $mime, $header)
    {
        $_SERVER['HTTP_ACCEPT'] = $header;

        $this->assertSame($expected, Request\accept($mime));
    }

    public function acceptProvider()
    {
        return array(
            'html' => array(true, 'html', 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8'),
            'json' => array(true, 'json', 'application/json'),
        );
    }

    public function testGetPostType()
    {
        $_GET = $_POST = array('int' => '123', 'number' => '12.34');

        $this->assertSame(123, Request\get_int('int'));
        $this->assertSame(null, Request\get_int('number'));
        $this->assertSame(12.34, Request\get_number('number'));

        $this->assertSame(123, Request\post_int('int'));
        $this->assertSame(null, Request\post_int('number'));
        $this->assertSame(12.34, Request\post_number('number'));
    }
}
