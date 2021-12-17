<?php

namespace Ekok\Cosiler\Test\Unit\Container;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Container;
use Ekok\Cosiler\Container\Box;

final class ContainerTest extends TestCase
{
    public function setUp(): void
    {
        Box::reset();
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

    public function testArrayAction()
    {
        $this->assertNull(Container\get('foo'));
        $this->assertNull(Container\pop('foo'));
        $this->assertSame(array('bar'), Container\push('foo', 'bar'));
        $this->assertSame(array('bar', 'baz'), Container\push('foo', 'baz'));
        $this->assertSame('baz', Container\pop('foo'));

        $this->assertNull(Container\get('bar'));
        $this->assertNull(Container\shift('bar'));
        $this->assertSame(array('baz'), Container\unshift('bar', 'baz'));
        $this->assertSame(array('qux', 'baz'), Container\unshift('bar', 'qux'));
        $this->assertSame('qux', Container\shift('bar'));
    }

    public function testClearing()
    {
        Container\set('foo', array(
            'bar' => array(
                'baz' => 'qux',
            ),
            'obj' => new class {
                public $data = 'data';
                public $data2 = 'data2';
                public function removeData()
                {
                    $this->data = 'removed';
                }
            },
            'tmp' => tmpfile(),
        ));

        Container\clear('foo.bar.baz');
        $this->assertArrayNotHasKey('baz', Container\get('foo.bar'));

        $this->assertEquals('data', Container\get('foo.obj.data'));
        $this->assertEquals('data2', Container\get('foo.obj.data2'));

        Container\clear('foo.obj.data');
        Container\clear('foo.obj.data2');

        $this->assertEquals('removed', Container\get('foo.obj.data'));
        $this->assertEquals(null, Container\get('foo.obj.data2'));

        $this->expectException('LogicException');
        $this->expectExceptionMessage('Unable to clear value of foo.tmp.member');
        Container\clear('foo.tmp.member');
    }

    public function testMassive()
    {
        Container\set_all(array(
            'bar' => 1,
            'baz' => 2,
        ));

        $this->assertTrue(Container\has_all('bar', 'baz'));
        $this->assertFalse(Container\has_all('bar', 'none', 'baz'));
        $this->assertTrue(Container\has_some('bar', 'none', 'baz'));

        $this->assertEquals(array('bar' => 1, 'MYBAZ' => 2), Container\get_all(array('bar', 'MYBAZ' => 'baz')));

        Container\clear_all('bar', 'baz');
        $this->assertFalse(Container\has_some('bar', 'baz'));

        Container\set_all(array(
            'bar' => 1,
            'baz' => 2,
        ), 'foo.');
        $this->assertEquals(array(
            'bar' => 1,
            'baz' => 2,
        ), Container\get('foo'));
    }

    public function testWith()
    {
        Container\set('foo', 'bar');

        $actual = Container\with('foo');
        $process = Container\with('foo', fn($bar) => $bar . 'baz');

        $this->assertEquals('bar', $actual);
        $this->assertEquals('barbaz', $process);
    }
}
