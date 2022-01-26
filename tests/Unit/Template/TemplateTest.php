<?php

use Ekok\Cosiler\Template;

final class TemplateTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        Template\directory(TEST_DATA . '/templates');
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
