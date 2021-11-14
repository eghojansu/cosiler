<?php

namespace Ekok\Cosiler\Test\Unit\File;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\File;

final class FileTest extends TestCase
{
    public function testTouch()
    {
        $tmp = TEMP_ROOT.'/file-touch';
        $file = $tmp.'/test.txt';

        if (is_dir($tmp)) {
            $files = glob($tmp.'/*.txt');

            array_map('unlink', $files);
            rmdir($tmp);
        }

        $this->assertTrue(File\touch($file));
        $this->assertFileExists($file);
        $this->assertSame('', file_get_contents($file));
        $this->assertTrue(File\touch($file));

        $this->assertTrue(File\touch($file, 'foo'));
        $this->assertSame('foo', file_get_contents($file));

        unlink($file);
        $this->assertTrue(File\touch($file, 'bar'));
        $this->assertSame('bar', file_get_contents($file));
    }
}
