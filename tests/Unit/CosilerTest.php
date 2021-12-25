<?php

namespace Ekok\Cosiler\Test\Unit;

use Ekok\Cosiler;
use Ekok\Cosiler\Test\Fixture\ScopedTestCase;

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

    public function testRef()
    {
        $source = array(
            'foo' => array(
                'bar' => array(
                    'baz' => 'qux',
                ),
                'string' => 'foobar',
                'tmp' => tmpfile(),
                'obj' => new class {
                    public $name = 'AClass';
                    public function getDescription()
                    {
                        return 'Class description';
                    }
                },
            ),
        );
        $copy = $source;

        $this->assertEquals($source['foo'], Cosiler\ref('foo', $source, false, $exists, $parts));
        $this->assertEquals(array('foo'), $parts);
        $this->assertTrue($exists);

        $this->assertEquals(null, Cosiler\ref('unknown', $source));
        $this->assertEquals(null, Cosiler\ref(1, $source, false, $exists, $parts));
        $this->assertEquals(array(1), $parts);
        $this->assertFalse($exists);
        $this->assertEquals($copy, $source);

        $this->assertEquals($source['foo']['bar'], Cosiler\ref('foo.bar', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'bar'), $parts);
        $this->assertTrue($exists);

        $this->assertEquals(null, Cosiler\ref('foo.unknown.member', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'unknown', 'member'), $parts);
        $this->assertFalse($exists);

        $this->assertEquals('foobar', Cosiler\ref('foo.string', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'string'), $parts);
        $this->assertTrue($exists);

        $this->assertEquals(null, Cosiler\ref('foo.string.member', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'string', 'member'), $parts);
        $this->assertFalse($exists);

        $this->assertEquals(null, Cosiler\ref('foo.tmp.member', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'tmp', 'member'), $parts);
        $this->assertFalse($exists);

        $this->assertEquals('AClass', Cosiler\ref('foo.obj.name', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'obj', 'name'), $parts);
        $this->assertTrue($exists);

        $this->assertEquals('Class description', Cosiler\ref('foo.obj.description', $source, false, $exists, $parts));
        $this->assertEquals(array('foo', 'obj', 'description'), $parts);
        $this->assertTrue($exists);

        // adding reference
        $add = &Cosiler\ref('add', $source, true, $exists);
        $add = 'value';

        $this->assertFalse($exists);
        $this->assertArrayHasKey('add', $source);
        $this->assertEquals('value', Cosiler\ref('add', $source, false, $exists));
        $this->assertTrue($exists);

        $member = &Cosiler\ref('foo.new.member', $source, true, $exists);
        $member = 'added';

        $this->assertFalse($exists);
        $this->assertArrayHasKey('new', $source['foo']);
        $this->assertArrayHasKey('member', $source['foo']['new']);
        $this->assertEquals('added', Cosiler\ref('foo.new.member', $source, false, $exists));
        $this->assertTrue($exists);
        $this->assertEquals(array('member' => 'added'), Cosiler\ref('foo.new', $source, false, $exists));
        $this->assertTrue($exists);

        if (is_resource($source['foo']['tmp'])) {
            fclose($source['foo']['tmp']);
        }

        if (is_resource($copy['foo']['tmp'])) {
            fclose($copy['foo']['tmp']);
        }
    }

    public function testCast()
    {
        $this->assertSame(1234, Cosiler\cast('1234'));
        $this->assertSame(83, Cosiler\cast('0123'));
        $this->assertSame(26, Cosiler\cast('0x1A'));
        $this->assertSame(255, Cosiler\cast('0b11111111'));
        $this->assertSame(true, Cosiler\cast('true'));
        $this->assertSame(null, Cosiler\cast('null'));
        $this->assertSame('1_234_567', Cosiler\cast('1_234_567'));
    }
}
