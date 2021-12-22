<?php

namespace Ekok\Cosiler\Test\Unit\Utils\Str;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Utils\Str;

class StrTest extends TestCase
{
    /** @dataProvider fixslashesProvider */
    public function testFixSlashes(string $expected, ...$arguments)
    {
        $actual = Str\fixslashes(...$arguments);

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
        $actual = Str\split(...$arguments);

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

    public function testQuote()
    {
        $this->assertSame('"foo"', Str\quote('foo'));
        $this->assertSame('"foo"."bar"', Str\quote('foo.bar'));
        $this->assertSame('`foo`.`bar`', Str\quote('foo.bar', '`'));
        $this->assertSame('[foo]', Str\quote('foo', '[', ']'));
        $this->assertSame('[foo].[bar]', Str\quote('foo.bar', '[', ']'));
    }

    /** @dataProvider caseSnakeProvider */
    public function testCaseSnake(string $expected, ...$arguments)
    {
        $actual = Str\case_snake(...$arguments);

        $this->assertSame($expected, $actual);
    }

    public function caseSnakeProvider()
    {
        return array(
            array('snake_case', 'snakeCase'),
            array('snake_case', 'SnakeCase'),
        );
    }

    /** @dataProvider caseCamelProvider */
    public function testCaseCamel(string $expected, ...$arguments)
    {
        $actual = Str\case_camel(...$arguments);

        $this->assertSame($expected, $actual);
    }

    public function caseCamelProvider()
    {
        return array(
            array('camelCase', 'camel_case'),
            array('camelCase', 'Camel_Case'),
        );
    }

    public function testRandom()
    {
        $this->assertNotEquals(Str\random(), Str\random());
        $this->assertNotEquals(Str\random(), Str\random());
        $this->assertNotEquals(Str\random(), Str\random());
        $this->assertNotEquals(Str\random(), Str\random());
        $this->assertNotEquals(Str\random(), Str\random());
        $this->assertNotEquals(Str\random(), Str\random());
    }

    public function testRandomUp()
    {
        $this->assertNotEquals(Str\random_up(), Str\random_up());
        $this->assertNotEquals(Str\random_up(), Str\random_up());
        $this->assertNotEquals(Str\random_up(), Str\random_up());
        $this->assertNotEquals(Str\random_up(), Str\random_up());
        $this->assertNotEquals(Str\random_up(), Str\random_up());
        $this->assertNotEquals(Str\random_up(), Str\random_up());
    }
}
