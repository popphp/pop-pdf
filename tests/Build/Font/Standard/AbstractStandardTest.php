<?php

namespace Pop\Pdf\Test\Build\Font\Standard;

use Pop\Pdf\Build\Font\Standard\Arial;
use PHPUnit\Framework\TestCase;

class AbstractStandardTest extends TestCase
{

    public function testGetUnitsPerEm()
    {
        $arial = new Arial();
        $this->assertEquals(1000, $arial->getUnitsPerEm());
    }

    public function testGetGlyphWidthMapped()
    {
        $arial = new Arial();
        // 'A' (65) is a mapped, known-width character
        $this->assertGreaterThan(0, $arial->getGlyphWidth(65));
    }

    public function testGetGlyphWidthUnmappedReturnsZero()
    {
        $arial = new Arial();
        // A code well outside any mapped character range falls through to the default
        $this->assertEquals(0, $arial->getGlyphWidth(999999));
    }

    public function testHasGlyph()
    {
        $arial = new Arial();
        // 'A' (65) is a mapped, known-width character (see testGetGlyphWidthMapped())
        $this->assertTrue($arial->hasGlyph(65));
        // Cyrillic 'П' (U+041F) - the standard 14 fonts have no Cyrillic glyphs at all
        $this->assertFalse($arial->hasGlyph(0x041F));
    }

    /**
     * Every WinAnsi-based standard font class must cover the four WinAnsi
     * codepoints its bundled cmap used to omit: U+00A0 (no-break space),
     * U+00AD (soft hyphen), U+2018 (left single quote) and U+2020 (dagger).
     */
    public function testHasGlyphForPreviouslyMissingWinAnsiCodepoints()
    {
        $classes = [
            'Arial', 'ArialBold', 'ArialBoldItalic', 'ArialItalic',
            'Courier', 'CourierBold', 'CourierBoldOblique', 'CourierOblique',
            'CourierNew', 'CourierNewBold', 'CourierNewBoldItalic', 'CourierNewItalic',
            'Helvetica', 'HelveticaBold', 'HelveticaBoldOblique', 'HelveticaOblique',
            'TimesRoman', 'TimesBold', 'TimesBoldItalic', 'TimesItalic',
            'TimesNewRoman', 'TimesNewRomanBold', 'TimesNewRomanBoldItalic', 'TimesNewRomanItalic'
        ];

        foreach ($classes as $class) {
            $fontClass = 'Pop\Pdf\Build\Font\Standard\\' . $class;
            $font      = new $fontClass();
            foreach ([0x00A0, 0x00AD, 0x2018, 0x2020] as $codePoint) {
                $this->assertTrue(
                    $font->hasGlyph($codePoint),
                    sprintf('%s is missing a glyph for U+%04X', $class, $codePoint)
                );
                $this->assertGreaterThan(
                    0, $font->getGlyphWidth($codePoint),
                    sprintf('%s has no width for U+%04X', $class, $codePoint)
                );
            }

            // The right single quote and double dagger the four additions sit
            // next to must keep working (they shared duplicated cmap keys).
            $this->assertTrue($font->hasGlyph(0x2019));
            $this->assertTrue($font->hasGlyph(0x2021));
        }
    }

}
