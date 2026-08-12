<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\TextRun;
use PHPUnit\Framework\TestCase;

class TextRunTest extends TestCase
{

    public function testConstructorForRawTextRun()
    {
        $run = new TextRun('TT0', 'Hello', null, 100.0, 700.0, TextRun::SEPARATOR_NONE);

        $this->assertEquals('TT0', $run->fontResourceName);
        $this->assertEquals('Hello', $run->rawBytes);
        $this->assertNull($run->decodedText);
        $this->assertEquals(100.0, $run->x);
        $this->assertEquals(700.0, $run->y);
        $this->assertEquals(TextRun::SEPARATOR_NONE, $run->separator);
        $this->assertFalse($run->reversed);
    }

    public function testConstructorForActualTextRun()
    {
        $run = new TextRun(null, null, 'Replacement', 50.0, 60.0, TextRun::SEPARATOR_SPACE, true);

        $this->assertNull($run->fontResourceName);
        $this->assertNull($run->rawBytes);
        $this->assertEquals('Replacement', $run->decodedText);
        $this->assertTrue($run->reversed);
    }

    public function testConstructorAcceptsResolvedFont()
    {
        $fontDict = ['Type' => 'Font'];
        $run      = new TextRun('F1', 'Hello', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, $fontDict);

        $this->assertSame($fontDict, $run->font);
    }

    public function testFontDefaultsToNull()
    {
        $run = new TextRun('F1', 'Hello', null, 0.0, 0.0, TextRun::SEPARATOR_NONE);

        $this->assertNull($run->font);
    }

}
