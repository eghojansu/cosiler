<?php

namespace Ekok\Cosiler\Test\Unit\Container;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Container;

final class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        Container\box()->prepared = false;
    }

    public function testBox()
    {
        $box = Container\box();

        $this->assertInstanceOf('stdClass', $box);
    }

    public function testCo()
    {
        $this->assertSame('bar', Container\co('foo', 'bar'));
        $this->assertSame('bar', Container\co('foo'));
    }

    public function testContainer()
    {
        $count = 0;

        Container\config(TEST_FIXTURES.'/config.php');
        Container\set('cb', static function() use (&$count) {
            return ++$count;
        });
        Container\protect('protected', 'is_string');

        $this->assertTrue(Container\has('foo'));
        $this->assertSame('bar', Container\get('foo'));
        $this->assertSame(1, Container\get('cb'));
        $this->assertSame(1, Container\get('cb'));
        $this->assertSame('is_string', Container\get('protected'));
        $this->assertNull(Container\make('nothing', false));

        Container\clear('foo');
        $this->assertFalse(Container\has('foo'));
    }

    public function testMakeUnknown()
    {
        $this->expectErrorMessage('No rule defined for: "foo"');

        Container\make('foo');
    }

    public function testFactory()
    {
        Container\set('foo', fn() => new \stdClass());
        Container\factory('bar', fn() => new \stdClass());

        $foo1 = Container\get('foo');
        $foo2 = Container\get('foo');
        $bar1 = Container\get('bar');
        $bar2 = Container\get('bar');

        $this->assertSame($foo1, $foo2);
        $this->assertNotSame($bar1, $bar2);
    }
}
