<?php

namespace Ekok\Cosiler\Test\Unit\Route;

use EKok\Cosiler\Route;
use PHPUnit\Framework\TestCase;

final class RouteStaticMethodTest extends TestCase
{
    public function testStaticMethod()
    {
        define('xx', 1);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $result = Route\get('/', RouteClass::class . '::staticMethod');

        $this->assertSame('static_method', $result);
    }
}
