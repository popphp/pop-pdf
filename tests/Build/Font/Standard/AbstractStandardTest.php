<?php

namespace Pop\Pdf\Test\Build\Font\Standard;

use Pop\Pdf\Build\Font\Standard\Arial;
use Pop\Pdf\Build\Font\Standard\Symbol;
use Pop\Pdf\Build\Font\Standard\ZapfDingbats;
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

    /**
     * Symbol and ZapfDingbats carry their own built-in encoding instead of WinAnsiEncoding,
     * so their text cannot be produced by transcoding to Windows-1252.
     */
    public function testOnlySymbolAndZapfDingbatsAreSymbolic()
    {
        $this->assertTrue((new ZapfDingbats())->isSymbolic());
        $this->assertTrue((new Symbol())->isSymbolic());
        $this->assertFalse((new Arial())->isSymbolic());
    }

    /**
     * The encoding table is the only thing that turns a Unicode character into the byte the
     * viewer looks up, so every cmap entry needs exactly one byte and no two may share one.
     */
    public function testSymbolicEncodingCoversEveryCmapEntryWithADistinctByte()
    {
        foreach ([new ZapfDingbats(), new Symbol()] as $font) {
            $cmap     = (new \ReflectionProperty($font, 'cmap'))->getValue($font);
            $encoding = (new \ReflectionProperty($font, 'encoding'))->getValue($font);
            $name     = (new \ReflectionClass($font))->getShortName();

            $this->assertEqualsCanonicalizing(array_keys($cmap), array_keys($encoding), $name);
            $this->assertCount(count($encoding), array_unique($encoding), $name . ' reuses a byte');

            foreach ($encoding as $codePoint => $byte) {
                $this->assertGreaterThanOrEqual(0x20, $byte, sprintf('%s U+%04X', $name, $codePoint));
                $this->assertLessThanOrEqual(0xFE, $byte, sprintf('%s U+%04X', $name, $codePoint));
            }
        }
    }

    /**
     * Spot checks against the byte assignments in the PDF specification's ZapfDingbats and
     * Symbol encoding tables (ISO 32000-1 Annex D.5 / D.6), independent of how the table was
     * built.
     */
    public function testSymbolicEncodingMatchesTheSpecificationTables()
    {
        $zapf = new ZapfDingbats();
        $this->assertSame("\x33", $zapf->encodeString("\u{2713}")); // check mark
        $this->assertSame("\x34", $zapf->encodeString("\u{2714}")); // heavy check mark
        $this->assertSame("\x48", $zapf->encodeString("\u{2605}")); // black star
        $this->assertSame("\x6C", $zapf->encodeString("\u{25CF}")); // black circle
        $this->assertSame("\x80", $zapf->encodeString("\u{2768}")); // first of the 0x80-0x8D run
        $this->assertSame("\xA1", $zapf->encodeString("\u{2761}")); // first of the 0xA1 run
        $this->assertSame("\xAC", $zapf->encodeString("\u{2460}")); // circled digit one
        $this->assertSame("\xD5", $zapf->encodeString("\u{2192}")); // rightwards arrow
        $this->assertSame("\xF1", $zapf->encodeString("\u{27B1}")); // 0xF0 is unassigned
        $this->assertSame("\xFE", $zapf->encodeString("\u{27BE}"));

        $symbol = new Symbol();
        $this->assertSame("\x61\x62\x67", $symbol->encodeString("\u{03B1}\u{03B2}\u{03B3}")); // alpha beta gamma
        $this->assertSame("\x22", $symbol->encodeString("\u{2200}")); // for all
        $this->assertSame("\xA0", $symbol->encodeString("\u{20AC}")); // euro
        $this->assertSame("\xB7", $symbol->encodeString("\u{2022}")); // bullet
        $this->assertSame("\xE5", $symbol->encodeString("\u{2211}")); // summation
        $this->assertSame("\xF0", $symbol->encodeString("\u{F8FF}")); // apple, out of glyph order
        $this->assertSame("\xF1", $symbol->encodeString("\u{232A}")); // right angle bracket
        $this->assertSame("\x30\x31", $symbol->encodeString('01'));    // ASCII digits keep their bytes
    }

    /**
     * Before v7 standard-font text was written as raw bytes, so the only way to draw a ZapfDingbats
     * check mark was the byte '3' (and 'a' for an alpha in Symbol). Unicode is the primary input
     * now, but a printable ASCII character the cmap has no Unicode entry for is still accepted as
     * that byte. Within ASCII the two readings never disagree: every ASCII cmap key (space, and
     * digits/punctuation in Symbol) encodes to itself.
     */
    public function testLegacySymbolicBytesStillSelectTheirGlyph()
    {
        $zapf = new ZapfDingbats();
        $this->assertTrue($zapf->hasGlyph(0x33));
        $this->assertSame("\x33", $zapf->encodeString('3'));
        $this->assertSame($zapf->getGlyphWidth(0x2713), $zapf->getGlyphWidth(0x33));
        $this->assertGreaterThan(0, $zapf->getGlyphWidth(0x33));

        $symbol = new Symbol();
        $this->assertTrue($symbol->hasGlyph(ord('a')));
        $this->assertSame("\x61", $symbol->encodeString('a'));
        $this->assertSame($symbol->getGlyphWidth(0x03B1), $symbol->getGlyphWidth(ord('a')));

        foreach ([$zapf, $symbol] as $font) {
            $encoding = (new \ReflectionProperty($font, 'encoding'))->getValue($font);
            foreach ($encoding as $codePoint => $byte) {
                if ($codePoint <= 0x7E) {
                    $this->assertSame($codePoint, $byte, sprintf('U+%04X is both a cmap key and a byte', $codePoint));
                }
            }
        }
    }

    /**
     * Above ASCII the readings do disagree in Symbol, so the legacy byte reading stops there and
     * the Unicode meaning always wins: '×' is Symbol's multiply sign (0xB4), not whatever sits at
     * byte 0xD7, and a Latin-1 letter is never silently turned into a dingbat.
     */
    public function testLegacyBytesStopAtAsciiSoUnicodeAlwaysWins()
    {
        $symbol = new Symbol();
        $this->assertSame("\xB4", $symbol->encodeString("\u{00D7}")); // multiply sign
        $this->assertSame("\xB8", $symbol->encodeString("\u{00F7}")); // divide sign
        $this->assertSame("\x6D", $symbol->encodeString("\u{00B5}")); // micro sign is mu
        $this->assertSame("\xD8", $symbol->encodeString("\u{00AC}")); // logical not

        $zapf = new ZapfDingbats();
        $this->assertFalse($zapf->hasGlyph(0x00E9));
        $this->assertNull($zapf->encodeString('é'));
    }

    public function testSymbolicEncodeStringReturnsNullForACharacterTheFontLacks()
    {
        $this->assertNull((new ZapfDingbats())->encodeString("\u{041F}"));
        $this->assertNull((new ZapfDingbats())->encodeString("\u{2713}\u{041F}"));
        $this->assertFalse((new ZapfDingbats())->hasGlyph(0x041F));
        // 0x7F-0x9F and 0xF0 are not assigned in ZapfDingbats' encoding
        $this->assertFalse((new ZapfDingbats())->hasGlyph(0x7F));
        $this->assertFalse((new ZapfDingbats())->hasGlyph(0xF0));
    }

    public function testWinAnsiFontsEncodeToWindows1252()
    {
        $arial = new Arial();
        $this->assertSame("caf\xE9 \x80", $arial->encodeString("café \u{20AC}"));
        $this->assertNull($arial->encodeString("\u{041F}"));
    }

}
