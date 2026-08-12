<?php

namespace Pop\Pdf\Test\Extract\Font\Encoding;

use Pop\Pdf\Extract\Font\Encoding\MacRomanEncoding;
use PHPUnit\Framework\TestCase;

class MacRomanEncodingTest extends TestCase
{

    public function testAsciiRangeIsIdentity()
    {
        $this->assertEquals(0x0041, MacRomanEncoding::TABLE[0x41]);
    }

    public function testKnownMacRomanSpecialCases()
    {
        $this->assertEquals(0x00C4, MacRomanEncoding::TABLE[0x80]); // A-dieresis
        $this->assertEquals(0x2022, MacRomanEncoding::TABLE[0xA5]); // bullet
        $this->assertEquals(0x2018, MacRomanEncoding::TABLE[0xD4]); // left single quote
        $this->assertEquals(0xFB01, MacRomanEncoding::TABLE[0xDE]); // fi ligature
    }

}
