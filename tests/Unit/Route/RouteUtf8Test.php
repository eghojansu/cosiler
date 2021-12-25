<?php

namespace Ekok\Cosiler\Test\Unit\Route;

use Ekok\Cosiler\Route;
use Ekok\Cosiler\Test\Fixture\ScopedTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RouteUtf8Test extends ScopedTestCase
{
    public function testUtf8A()
    {
        $this->expectOutputString('foo');
        $_SERVER['REQUEST_URI'] = rawurlencode('/жолжаксынов');
        Route\get('/жолжаксынов', function () {
            echo 'foo';
        });
    }

    public function testUtf8B()
    {
        $this->expectOutputString('victon-빅톤-mayday');
        $_SERVER['REQUEST_URI'] = rawurlencode('/test/victon-빅톤-mayday');
        Route\get('/test/{test:.*}', function (array $params) {
            echo $params['test'];
        });
    }

    public function testUtf8C()
    {
        $this->expectOutputString('আড়-ইহ-জ-র-দ-ড়-বছর-র-শ-শ-র-গল-য়-ছ-র');
        $_SERVER['REQUEST_URI'] = rawurlencode('/foo/আড়-ইহ-জ-র-দ-ড়-বছর-র-শ-শ-র-গল-য়-ছ-র/baz');
        Route\get('/foo/{bar:.*}/baz', function (array $params) {
            echo $params['bar'];
        });
    }
}
