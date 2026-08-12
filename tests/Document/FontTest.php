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

    public function testGetStringWidthForEmbeddedFont()
    {
        // Same string/size as the AbstractFont-level assertions already made
        // in DocumentTest's testEmbedFont(), but going through
        // Font::getStringWidth() itself rather than getParsedFont() directly
        // - that's the parser!==null branch, which none of the existing
        // tests exercise.
        $font = new Font(__DIR__ . '/../tmp/fonts/times.ttf');
        $width = $font->getStringWidth('Hello World', 12);
        $this->assertIsFloat($width);
        $this->assertEquals(
            $font->getParsedFont()->getStringWidth('Hello World', 12),
            $width
        );
    }

    public function testCourierNewVariantsResolveToBackingClassesWithCourierMetrics()
    {
        // Document\Font::standardFonts() lists 'CourierNew'/',Italic'/',Bold'/
        // ',BoldItalic' as valid names, but until Build\Font\Standard\CourierNew*
        // existed, using any of them threw "That standard font class was not
        // found" the moment a string width was actually measured. Courier New
        // is metric-compatible with Courier (that's its whole purpose as a
        // TrueType-safe rename), so the backing classes mirror Courier's data
        // exactly, matching this codebase's existing Arial<->Helvetica and
        // TimesNewRoman<->TimesRoman alias pairs.
        $pairs = [
            'CourierNew'            => 'Courier',
            'CourierNew,Italic'     => 'Courier-Oblique',
            'CourierNew,Bold'       => 'Courier-Bold',
            'CourierNew,BoldItalic' => 'Courier-BoldOblique',
        ];

        foreach ($pairs as $courierNewName => $courierName) {
            $courierNewFont = new Font($courierNewName);
            $courierFont    = new Font($courierName);

            $this->assertEquals(
                $courierFont->getStringWidth('Hello World', 12),
                $courierNewFont->getStringWidth('Hello World', 12)
            );
        }
    }

    public function testGetStringWidthThrowsExceptionWhenStandardFontClassIsMissing()
    {
        // Every real standardFonts() entry maps to a real Standard\* class,
        // so this reaches the "class not found" throw the only way possible
        // without touching src/: forcing a name that isn't one of those
        // constants onto an already-standard Font via reflection, leaving
        // isStandard/parser exactly as a real standard font would have them.
        $font = new Font('Arial');

        $name = new \ReflectionProperty(Font::class, 'name');
        $name->setAccessible(true);
        $name->setValue($font, 'NotARealStandardFont');

        $this->expectException('Pop\Pdf\Document\Exception');
        $this->expectExceptionMessage('That standard font class was not found');
        $font->getStringWidth('Hello', 12);
    }

    public function testIsCidTrueForEmbeddedTrueType()
    {
        $font = new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf');
        $this->assertTrue($font->isCid());
    }

    public function testIsCidFalseForStandardFont()
    {
        $font = new Font(Font::ARIAL);
        $this->assertFalse($font->isCid());
    }

    public function testStringToCodeUnits()
    {
        // 'A' (U+0041) followed by Cyrillic 'П' (U+041F)
        $this->assertEquals([0x0041, 0x041F], Font::stringToCodeUnits("A\u{041F}"));
    }

    public function testHasGlyphAndGetGlyphIdForCidFont()
    {
        $font = new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf');
        $this->assertTrue($font->hasGlyph(0x041F));
        $this->assertEquals(948, $font->getGlyphId(0x041F));
        // U+FFFE is a permanently-unassigned noncharacter - guaranteed absent.
        $this->assertFalse($font->hasGlyph(0xFFFE));
        $this->assertNull($font->getGlyphId(0xFFFE));
    }

    public function testHasGlyphForStandardFont()
    {
        $font = new Font(Font::ARIAL);
        $this->assertTrue($font->hasGlyph(0x0041)); // 'A'
        $this->assertFalse($font->hasGlyph(0x041F)); // Cyrillic 'П'
    }

    public function testHasGlyphForWinAnsiPunctuationOnStandardFont()
    {
        $font = new Font(Font::TIMES_ROMAN);
        $this->assertTrue($font->hasGlyph(0x00A0)); // no-break space
        $this->assertTrue($font->hasGlyph(0x00AD)); // soft hyphen
        $this->assertTrue($font->hasGlyph(0x2018)); // left single quote
        $this->assertTrue($font->hasGlyph(0x2020)); // dagger
    }

    public function testGetStringWidthWithWinAnsiPunctuationDoesNotThrow()
    {
        $font = new Font(Font::TIMES_ROMAN);
        $font->requireGlyphCoverage("A\u{00A0}B\u{00AD}C\u{2018}D\u{2020}");
        $this->assertGreaterThan(0, $font->getStringWidth("A\u{00A0}B\u{00AD}C\u{2018}D\u{2020}", 12));
    }

    public function testRequireGlyphCoverageThrowsForUnsupportedCharacter()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Font(Font::ARIAL);
        $font->requireGlyphCoverage("\u{041F}");
    }

    public function testStringToGidHexForCidFont()
    {
        $font = new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf');
        // GID 948 -> hex '03B4'
        $this->assertEquals('03B4', $font->stringToGidHex("\u{041F}"));
    }

}