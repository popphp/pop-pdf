<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Font\GlyphNames;
use PHPUnit\Framework\TestCase;

class GlyphNamesTest extends TestCase
{

    public function testUniPrefixDecodesFourHexDigits()
    {
        $this->assertEquals(0x0041, GlyphNames::resolve('uni0041'));
        $this->assertEquals(0x20AC, GlyphNames::resolve('uni20AC'));
    }

    public function testUPrefixDecodesFourToSixHexDigits()
    {
        $this->assertEquals(0x0041, GlyphNames::resolve('u0041'));
        $this->assertEquals(0x1F600, GlyphNames::resolve('u1F600'));
    }

    public function testCuratedTableHits()
    {
        $this->assertEquals(0x0020, GlyphNames::resolve('space'));
        $this->assertEquals(0x2019, GlyphNames::resolve('quoteright'));
        $this->assertEquals(0x2018, GlyphNames::resolve('quoteleft'));
        $this->assertEquals(0xFB01, GlyphNames::resolve('fi'));
        $this->assertEquals(0x00E9, GlyphNames::resolve('eacute'));
        $this->assertEquals(0x00C9, GlyphNames::resolve('Eacute'));
    }

    public function testUnmappedNameReturnsNull()
    {
        $this->assertNull(GlyphNames::resolve('g1234'));
        $this->assertNull(GlyphNames::resolve('somecustomglyph'));
    }

}
