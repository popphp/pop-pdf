<?php

namespace Pop\Pdf\Test\Extract\Font\Encoding;

use Pop\Pdf\Extract\Font\Encoding\SymbolEncoding;
use PHPUnit\Framework\TestCase;

class SymbolEncodingTest extends TestCase
{

    public function testLowercaseGreekByKeyboardPosition()
    {
        $this->assertEquals(0x03B1, SymbolEncoding::get(0x61)); // a -> alpha
        $this->assertEquals(0x03C0, SymbolEncoding::get(0x70)); // p -> pi
        $this->assertEquals(0x03C9, SymbolEncoding::get(0x77)); // w -> omega
    }

    public function testUppercaseGreekDerivedFromLowercase()
    {
        $this->assertEquals(0x0391, SymbolEncoding::get(0x41)); // A -> Alpha
        $this->assertEquals(0x03A9, SymbolEncoding::get(0x57)); // W -> Omega
    }

    public function testUnmappedKeysReturnNull()
    {
        $this->assertNull(SymbolEncoding::get(0x6A)); // j - not a Greek letter position
        $this->assertNull(SymbolEncoding::get(0x76)); // v - not a Greek letter position
    }

    public function testSpaceIsMapped()
    {
        $this->assertEquals(0x0020, SymbolEncoding::get(0x20));
    }

    public function testByteOutsideAnyMappedRangeReturnsNull()
    {
        // '$' (0x24) is neither the space byte nor within either letter range.
        $this->assertNull(SymbolEncoding::get(0x24));
    }

}
