<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Font;
use PHPUnit\Framework\TestCase;

class FontTest extends TestCase
{

    public function testSetFontException()
    {
        $this->expectException('InvalidArgumentException');
        $font = new Font('BAD_FONT');
    }

    public function testGetFont()
    {
        $font = new Font('Arial');
        $this->assertEquals('Arial', $font->getFont());
    }

    public function testGetStandardFonts()
    {
        $font = new Font('Arial');
        $this->assertEquals(26, count($font->getStandardFonts()));
        $this->assertEquals(26, count(Font::standardFonts()));
    }

    public function testGetStringWidth()
    {
        $font = new Font('Arial');
        $this->assertEquals(27.336, $font->getStringWidth('Hello', 12));
    }

}