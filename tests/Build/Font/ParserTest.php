<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    public function testConstructor()
    {
        $otf = new Parser(__DIR__ . '/../../tmp/fonts/bos.otf');
        $pfb = new Parser(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $this->assertInstanceOf('Pop\Pdf\Build\Font\Parser', $otf);
        $this->assertInstanceOf('Pop\Pdf\Build\Font\Parser', $pfb);
        $this->assertFalse($otf->isCompressed());
        $this->assertTrue($otf->isEmbeddable());
    }

    public function testConstructorDoesNotExistException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser('bad-font.ttf');
    }

    public function testConstructorNotExtensionException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser(__DIR__ . '/../../tmp/fonts/bad-font');
    }

    public function testConstructorNotAllowedException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser(__DIR__ . '/../../tmp/fonts/bad-font.bad');
    }

    public function testConstructorBadFontException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser('font.bad');
    }

    public function testConstructorNoAfmException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $pfb = new Parser(__DIR__ . '/../../tmp/fonts/cez.pfb');
    }

    public function testSetIndices()
    {
        $font = new Parser(__DIR__ . '/../../tmp/fonts/times.ttf');
        $font->setFontIndex(1);
        $font->setFontFileIndex(2);
        $font->setFontDescIndex(3);
        $font->setFontObjectIndex(4);
        $this->assertEquals(1, $font->getFontIndex());
        $this->assertEquals(2, $font->getFontFileIndex());
        $this->assertEquals(3, $font->getFontDescIndex());
        $this->assertEquals(4, $font->getFontObjectIndex());
        $this->assertEquals(3, count($font->getObjects()));
    }

    public function testParseType1()
    {
        $font = new Parser(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $font->setFontIndex(1);
        $font->setFontFileIndex(2);
        $font->setFontDescIndex(3);
        $font->setFontObjectIndex(4);
        $font->parse();
        $this->assertEquals(3, count($font->getObjects()));
    }

    public function testParseNoIndicesSetException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser(__DIR__ . '/../../tmp/fonts/s050000l.pfb');
        $font->parse();
    }

}