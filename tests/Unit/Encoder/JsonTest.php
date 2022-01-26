<?php

use Ekok\Cosiler\Encoder\Json;

final class JsonTest extends \Codeception\Test\Unit
{
    public function testEncode()
    {
        $expected = '{"foo":"bar"}';
        $actual = Json\encode(array('foo' => 'bar'));

        $this->assertSame($expected, $actual);
    }

    public function testDecode()
    {
        $expected = array('foo' => 'bar');
        $actual = Json\decode('{"foo":"bar"}');

        $this->assertSame($expected, $actual);
    }
}
