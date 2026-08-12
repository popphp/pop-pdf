<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\AbstractFont;
use Pop\Utils\ArrayObject as Data;
use PHPUnit\Framework\TestCase;

class AbstractFontTest extends TestCase
{

    /**
     * Helper to get a concrete, instantiable instance of the abstract class.
     */
    protected function getFont(?string $fontFile = null, ?string $fontStream = null): AbstractFont
    {
        return new class($fontFile, $fontStream) extends AbstractFont {};
    }

    public function testConstructorNoExtensionException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->getFont(__DIR__ . '/../../tmp/fonts/bad-font');
    }

    public function testConstructorNotAllowedTypeException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->getFont(__DIR__ . '/../../tmp/fonts/bad-font.bad');
    }

    public function testConstructorNoFileOrStreamException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $this->getFont();
    }

    public function testReadWithOffsetAndLengthFromStream()
    {
        $font = $this->getFont(null, 'abcdefghij');
        $this->assertEquals('cde', $font->read(2, 3));
    }

    public function testReadWithOffsetAndLengthFromFile()
    {
        $font = $this->getFont(__DIR__ . '/../../tmp/fonts/times.ttf');
        // The first 4 bytes of a TTF sfnt file are the version tag 00 01 00 00
        $this->assertEquals('00010000', bin2hex($font->read(0, 4)));
    }

    public function testReadWithOffsetOnlyFromStream()
    {
        $font = $this->getFont(null, 'abcdefghij');
        // No $length argument takes the offset-only branch (substr with no length)
        $this->assertEquals('cdefghij', $font->read(2));
    }

    public function testReadWithOffsetOnlyFromFile()
    {
        $font = $this->getFont(__DIR__ . '/../../tmp/fonts/times.ttf');
        $full = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        // No $length argument takes the offset-only branch (file_get_contents with no length)
        $this->assertEquals(substr($full, 4), $font->read(4));
    }

    public function testReadIntNegativeBranch()
    {
        $font = $this->getFont(null, 'stream-data');
        // High bit set on the first byte triggers the negative-number branch
        $this->assertEquals(-1, $font->readInt(2, "\xFF\xFF"));
    }

    public function testShiftToSignedScalarBranch()
    {
        $font = $this->getFont(null, 'stream-data');
        $this->assertEquals(40000 - 65536, $font->shiftToSigned(40000));
        // Below the threshold, the value passes through unchanged
        $this->assertEquals(100, $font->shiftToSigned(100));
    }

    public function testGetWidthsForGlyphsFoundBranch()
    {
        $font = $this->getFont(null, 'stream-data');
        $font['cmap']           = ['glyphNumbers' => [65 => 10]];
        $font['rawGlyphWidths'] = [10 => 500];
        $font['missingWidth']   = 0;

        $widths = $font->getWidthsForGlyphs([65]);
        $this->assertEquals([500], $widths);
    }

    public function testCalcFlagsFixedPitchAndItalic()
    {
        $font = $this->getFont(null, 'stream-data');
        $font['flags'] = new Data([
            'isFixedPitch' => true,
            'isItalic'     => true,
        ]);

        // bit 0 (fixed pitch) + bit 5 (always set) + bit 6 (italic) = 1 + 32 + 64
        $this->assertEquals(97, $font->calcFlags());
    }

    public function testOffsetUnsetAndMagicUnset()
    {
        $font = $this->getFont(null, 'stream-data');

        $font['foo'] = 'bar';
        $this->assertTrue(isset($font['foo']));
        unset($font['foo']);
        $this->assertFalse(isset($font['foo']));

        $font->baz = 'qux';
        $this->assertTrue(isset($font->baz));
        unset($font->baz);
        $this->assertFalse(isset($font->baz));
    }

}
