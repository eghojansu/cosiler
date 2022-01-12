<?php

namespace Ekok\Cosiler\Tests;

use Ekok\Cosiler;
use Ekok\Cosiler\Tests\Fixture\ScopedTestCase;

final class CosilerTest extends ScopedTestCase
{
    /** @dataProvider requireFnProvider */
    public function testRequire_fn($expected, string $file, array $params = null)
    {
        $cb = Cosiler\require_fn($file);
        $actual = $cb($params ?? array());

        $this->assertSame($expected, $actual);
    }

    public function requireFnProvider()
    {
        return array(
            'require file' => array(
                'baz',
                TEST_FIXTURES.'/routes/foo.php',
                array('bar' => 'baz'),
            ),
            'file returns callback' => array(
                'bar',
                TEST_FIXTURES.'/routes/callable.php',
                array('foo' => 'bar'),
            ),
            'file not found' => array(
                null,
                'not exists',
            ),
        );
    }

    /** @runInSeparateProcess */
    public function testBootstrap()
    {
        $this->expectOutputString('Exception thrown while running apps');

        Cosiler\bootstrap(TEST_FIXTURES . '/bootstrap/error.php', TEST_FIXTURES . '/bootstrap/start.php');
    }

    public function testStorage()
    {
        // initials
        $this->assertCount(0, Cosiler\storage());
        // no data
        $this->assertNull(Cosiler\storage('foo'));

        // set
        Cosiler\storage('foo', 'bar');

        // set default
        Cosiler\storage(null, 'foo');

        $this->assertSame('bar', Cosiler\storage());

        // set default second method
        Cosiler\storage('bar', 'baz', true);

        $this->assertSame('baz', Cosiler\storage());

        Cosiler\storage(null, 'RESET');

        $this->assertCount(0, Cosiler\storage());
    }
}
