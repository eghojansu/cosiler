<?php

namespace Ekok\Cosiler\Test\Unit;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler;

final class CosilerTest extends TestCase
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

    public function testMap()
    {
        $actual = Cosiler\map(array('foo', 'bar', null), fn($value, $key) => array($value, $key));
        $expected = array('foo' => 0, 'bar' => 1);

        $this->assertSame($expected, $actual);
    }

    /** @dataProvider eachProvider */
    public function testEach(array $expected, ...$arguments)
    {
        $actual = Cosiler\each(...$arguments);

        $this->assertSame($expected, $actual);
    }

    public function eachProvider()
    {
        $fn = static function($value) {
            return $value ? $value . '-a' : null;
        };

        return array(
            'keep keys' => array(
                array('foo' => 'bar-a', 1 => 'baz-a', 'qux' => 'quux-a'),
                array('foo' => 'bar', 1 => 'baz', 'qux' => 'quux', 0 => null),
                $fn,
                true,
            ),
            'indexed keys' => array(
                array('bar-a', 'baz-a', 'quux-a', null),
                array('foo' => 'bar', 1 => 'baz', 'qux' => 'quux', null),
                $fn,
                false,
                false,
            ),
        );
    }

    /** @dataProvider fixslashesProvider */
    public function testFixSlashes(string $expected, ...$arguments)
    {
        $actual = Cosiler\fixslashes(...$arguments);

        $this->assertSame($expected, $actual);
    }

    public function fixslashesProvider()
    {
        return array(
            array('/foo/bar', '\\foo//bar'),
            array('foo/bar/', 'foo//bar\\'),
            array('', ''),
        );
    }

    /** @dataProvider splitProvider */
    public function testSplit(array $expected, ...$arguments)
    {
        $actual = Cosiler\split(...$arguments);

        $this->assertSame($expected, $actual);
    }

    public function splitProvider()
    {
        return array(
            array(array('foo', 'bar'), 'foo,bar'),
            array(array('foo', 'bar'), 'foo, bar'),
            array(array('foo', 'bar'), 'foo, bar, ,'),
        );
    }

    public function testWalk()
    {
        $expected = array('foo', 'bar', 'baz');
        $actual = array();

        Cosiler\walk($expected, function($value) use (&$actual) {
            $actual[] = $value;
        });

        $this->assertSame($expected, $actual);
    }

    public function testFirst()
    {
        $expected = 'foo';
        $actual = Cosiler\first(array('foo', 'bar'), fn($value) => $value);
        $second = Cosiler\first(array(null), fn($value) => $value);

        $this->assertSame($expected, $actual);
        $this->assertNull($second);
    }

    /** @runInSeparateProcess */
    public function testBootstrap()
    {
        $this->expectOutputString('Exception thrown while running apps');

        Cosiler\bootstrap(TEST_FIXTURES . '/bootstrap/error.php', TEST_FIXTURES . '/bootstrap/start.php');
    }

    public function testQuote()
    {
        $this->assertSame('"foo"', Cosiler\quote('foo'));
        $this->assertSame('"foo"."bar"', Cosiler\quote('foo.bar'));
        $this->assertSame('`foo`.`bar`', Cosiler\quote('foo.bar', '`'));
        $this->assertSame('[foo]', Cosiler\quote('foo', '[', ']'));
        $this->assertSame('[foo].[bar]', Cosiler\quote('foo.bar', '[', ']'));
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
}
