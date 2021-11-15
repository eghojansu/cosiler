<?php

namespace Ekok\Cosiler\Test\Unit\Template;

use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Template;

final class TemplateTest extends TestCase
{
    protected function setUp(): void
    {
        Template\directory(TEST_FIXTURES . '/templates');
    }

    /** @dataProvider loadProvider */
    public function testLoad(string $expected, ?string $exception, ...$arguments)
    {
        if ($exception) {
            $this->expectException($exception);
            $this->expectExceptionMessage($expected);
        } else {
            $this->expectOutputString($expected);
        }

        Template\load(...$arguments);
    }

    public function loadProvider()
    {
        return array(
            array('header', null, 'header'),
            array('footer', null, 'footer.php'),
            array('Template not found: "unknown"', 'LogicException', 'unknown'),
        );
    }
}
