<?php

use Ekok\Cosiler;

class CosilerTest extends \Codeception\Test\Unit
{
    /** @dataProvider requireFnProvider */
    public function testRequire_fn($expected, string $file, array $params = null, array $data = null)
    {
        $cb = Cosiler\require_fn($file, $data);
        $actual = $cb($params ?? array());

        $this->assertSame($expected, $actual);
    }

    public function requireFnProvider()
    {
        return array(
            'require file' => array(
                'baz',
                TEST_DATA.'/routes/foo.php',
                array('bar' => 'baz'),
            ),
            'require file data' => array(
                'quux',
                TEST_DATA.'/routes/foo.php',
                array('qux' => 'quux'),
                array('get' => 'qux'),
            ),
            'file returns callback' => array(
                'bar',
                TEST_DATA.'/routes/callable.php',
                array('foo' => 'bar'),
            ),
            'file not found' => array(
                null,
                'not exists',
            ),
        );
    }

    public function testBootstrap()
    {
        $this->expectOutputString('Exception thrown while running apps');

        Cosiler\bootstrap(TEST_DATA . '/bootstrap/error.php', TEST_DATA . '/bootstrap/start.php');
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

        Cosiler\storage_reset();

        $this->assertCount(0, Cosiler\storage());
    }
}
