<?php

namespace Ekok\Cosiler\Test\Unit\Route;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Route;

final class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_REQUEST = ['foo' => 'bar'];

        $_SERVER['HTTP_HOST'] = 'test:8000';
        $_SERVER['SCRIPT_NAME'] = '/foo/test.php';
        $_SERVER['REQUEST_URI'] = '/bar/baz';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testRouteWithRequest()
    {
        $this->expectOutputString('bar');

        Route\handle(
            'get',
            '/foo',
            function () {
                echo 'bar';
            },
            ['get', '/foo']
        );
    }

    public function testRouteMatching()
    {
        $this->expectOutputString('baz');

        Route\handle('get', '/foo', function ($params) {
            echo 'foo';
        });

        Route\handle('get', '/bar', function ($params) {
            echo 'bar';
        });

        Route\handle('get', '/bar/baz', function ($params) {
            echo 'baz';
        });
    }

    public function testRouteRegexp()
    {
        $this->expectOutputString('baz');

        Route\handle('get', '/bar/([a-z]+)', function ($params) {
            echo $params[1];
        });
    }

    public function testRouteNamedGroup()
    {
        $this->expectOutputString('baz');

        Route\handle('get', '/bar/{baz}', function ($params) {
            echo $params['baz'];
        });
    }

    public function testOptionalParam()
    {
        $this->expectOutputString('qux');

        Route\handle('get', '/bar/baz/?{qux}?', function ($params) {
            echo array_key_exists('qux', $params) ? 'foobar' : 'qux';
        });
    }

    public function testOptionalParamMatch()
    {
        $this->expectOutputString('biz');

        $_SERVER['REQUEST_URI'] = '/bar/baz/biz';

        Route\handle('get', '/bar/baz/?{qux}?', function ($params) {
            echo array_key_exists('qux', $params) ? $params['qux'] : 'qux';
        });
    }

    public function testRouteWrappedNamedGroup()
    {
        $this->expectOutputString('baz');

        $_SERVER['REQUEST_URI'] = '/bar/baz/qux';

        Route\handle('get', '/bar/{baz}', function ($params) {
            echo 'foo';
        });

        Route\handle('get', '/bar/{baz}/qux', function ($params) {
            echo $params['baz'];
        });
    }

    public function testRouteNamedGroupWithDash()
    {
        $this->expectOutputString('baz-qux');

        $_SERVER['REQUEST_URI'] = '/bar/baz-qux';

        Route\handle('get', '/bar/{baz}', function ($params) {
            echo 'baz-qux';
        });
    }

    public function testRouteNamedGroupWithNumber()
    {
        $this->expectOutputString('baz-2017');

        $_SERVER['REQUEST_URI'] = '/bar/baz-2017';

        Route\handle('get', '/bar/{baz}', function ($params) {
            echo $params['baz'];
        });
    }

    public function testRouteNamedGroupWithUnderscore()
    {
        $this->expectOutputString('baz_qux');

        $_SERVER['REQUEST_URI'] = '/bar/baz_qux';

        Route\handle('get', '/bar/{baz}', function ($params) {
            echo $params['baz'];
        });
    }

    public function testRouteDefaultPathInfo()
    {
        $this->expectOutputString('foo');

        unset($_SERVER['REQUEST_URI']);

        Route\handle('get', '/', function ($params) {
            echo 'foo';
        });
    }

    public function testRouteWithString()
    {
        $this->expectOutputString('foo');
        Route\handle('get', '/bar/{bar}', TEST_FIXTURES . '/routes/shout_foo.php');
    }

    public function testRouteMethod()
    {
        $this->expectOutputString('bar');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        Route\handle('get', '/bar/baz', function ($params) {
            echo 'foo';
        });

        Route\handle('post', '/bar/baz', function ($params) {
            echo 'bar';
        });
    }

    public function testRouteMultiMethods()
    {
        $this->expectOutputString('foobar');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        Route\handle(['get', 'post'], '/bar/baz', function ($params) {
            echo 'foo';
        });

        Route\handle('post', '/bar/baz', function ($params) {
            echo 'bar';
        });
    }

    public function testRouteReturn()
    {
        $actual = Route\handle('get', '/bar/baz', function () {
            return 'foo';
        });

        $this->assertSame('foo', $actual);
    }

    public function testRegexify()
    {
        $this->assertSame('#^//?$#', Route\regexify('/'));
        $this->assertSame('#^/foo/?$#', Route\regexify('/foo'));
        $this->assertSame('#^/foo/bar/?$#', Route\regexify('/foo/bar'));
        $this->assertSame('#^/foo/(?<baz>[A-z0-9_-]+)/?$#', Route\regexify('/foo/{baz}'));
        $this->assertSame('#^/foo/(?<BaZ>[A-z0-9_-]+)/?$#', Route\regexify('/foo/{BaZ}'));
        $this->assertSame('#^/foo/(?<bar_baz>[A-z0-9_-]+)/?$#', Route\regexify('/foo/{bar_baz}'));
        $this->assertSame('#^/foo/(?<baz>[A-z0-9_-]+)/qux/?$#', Route\regexify('/foo/{baz}/qux'));
        $this->assertSame('#^/foo/(?<baz>[A-z0-9_-]+)?/?$#', Route\regexify('/foo/{baz}?'));

        $this->assertSame('#^/foo/(?<baz>[0-9]+)/?$#', Route\regexify('/foo/{baz:[0-9]+}'));
        $this->assertSame('#^/foo/(?<baz>[A-z]*)/?$#', Route\regexify('/foo/{baz:[A-z]*}'));
        $this->assertSame('#^/foo/(?<baz>foo|bar|baz)/?$#', Route\regexify('/foo/{baz:foo|bar|baz}'));
    }

    public function testRoutify()
    {
        $this->assertSame(['get', '/'], Route\routify('\\index.get.php'));
        $this->assertSame(['get', '/'], Route\routify('index.get.php'));
        $this->assertSame(['get', '/'], Route\routify('/index.get.php'));
        $this->assertSame(['post', '/'], Route\routify('/index.post.php'));
        $this->assertSame(['get', '/foo'], Route\routify('/foo.get.php'));
        $this->assertSame(['get', '/foo'], Route\routify('/foo/index.get.php'));
        $this->assertSame(['get', '/foo/bar'], Route\routify('/foo.bar.get.php'));
        $this->assertSame(['get', '/foo/bar'], Route\routify('/foo/bar.get.php'));
        $this->assertSame(['get', '/foo/bar'], Route\routify('/foo/bar/index.get.php'));
        $this->assertSame(['get', '/foo/{id}'], Route\routify('/foo.{id}.get.php'));
        $this->assertSame(['get', '/foo/{id}'], Route\routify('/foo.$id.get.php'));
        $this->assertSame(['get', '/foo/?{id}?'], Route\routify('/foo.@id.get.php'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testMethodPath()
    {
        $methodPath = Route\method_path(['OPTIONS', '/baz']);
        $this->assertSame(['OPTIONS', '/baz'], $methodPath);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $methodPath = Route\method_path(null);
        $this->assertSame(['POST', '/bar/baz'], $methodPath);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCancel()
    {
        $result = Route\get('/bar/baz', fn() => 'foo');
        $this->assertFalse(Route\canceled());
        $this->assertSame('foo', $result);

        Route\resume();
        Route\cancel();

        $result = Route\get('/bar/baz', fn() => 'foo');
        $this->assertTrue(Route\canceled());
        $this->assertNull($result);
    }

    public function testUrlEncodedQueryString()
    {
        $_SERVER['REQUEST_URI'] = '/bar/baz?filters%5Bstate%5D=2';
        $_SERVER['QUERY_STRING'] = 'filters%5Bstate%5D=2';

        $actual = Route\get('/bar/baz', fn() => 'foo');
        $this->assertSame('foo', $actual);
    }

    public function testStaticMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $result = Route\get('/', RouteClass::class . '::staticMethod');

        $this->assertSame('static_method', $result);
    }

    public function testWithBase()
    {
        Route\base('/bar');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo/bar/baz';

        $result = Route\get('/baz', static function() {
            return 'foo';
        });

        $this->assertSame('foo', $result);

        Route\base('');
    }

    /** @dataProvider routeFacadesProvider */
    public function testRouteFacades(string $method)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Route /bar/baz should match');

        $call = 'Ekok\Cosiler\Route\\' . $method;
        $_SERVER['PATH_INFO'] = '/bar/baz';
        $_SERVER['REQUEST_METHOD'] = $method;

        $call('/bar/baz', function () {
            throw new \Exception('Route /bar/baz should match');
        });
    }

    public function routeFacadesProvider()
    {
        return array(
            'get' => array('get'),
            'post' => array('post'),
            'put' => array('put'),
            'delete' => array('delete'),
            'options' => array('options'),
            'any' => array('any'),
        );
    }
}
