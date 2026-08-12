<?php

namespace Pop\Pdf\Test\Extract\Font\Encoding;

use Pop\Pdf\Extract\Font\Encoding\WinAnsiEncoding;
use PHPUnit\Framework\TestCase;

class WinAnsiEncodingTest extends TestCase
{

    public function testAsciiRangeIsIdentity()
    {
        $this->assertEquals(0x0041, WinAnsiEncoding::TABLE[0x41]);
        $this->assertEquals(0x0020, WinAnsiEncoding::TABLE[0x20]);
    }

    public function testKnownCp1252SpecialCases()
    {
        $this->assertEquals(0x20AC, WinAnsiEncoding::TABLE[0x80]); // Euro sign
        $this->assertEquals(0x201C, WinAnsiEncoding::TABLE[0x93]); // left double quote
        $this->assertEquals(0x2019, WinAnsiEncoding::TABLE[0x92]); // right single quote
        $this->assertEquals(0x2014, WinAnsiEncoding::TABLE[0x97]); // em dash
    }

    public function testLatin1SupplementRangeIsIdentity()
    {
        $this->assertEquals(0x00E9, WinAnsiEncoding::TABLE[0xE9]); // e-acute
        $this->assertEquals(0x00C4, WinAnsiEncoding::TABLE[0xC4]); // A-dieresis
    }

}
