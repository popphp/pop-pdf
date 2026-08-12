<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    protected string $scratchDir;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/pop-pdf-parser-test-' . uniqid();
        mkdir($this->scratchDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratchDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->scratchDir);
    }

    public function testLoadFromStream()
    {
        $this->assertNull(Parser::loadFromStream('some-stream-data'));
    }

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
        $font->setCidFontObjectIndex(5);
        $font->setToUnicodeIndex(6);
        $this->assertEquals(1, $font->getFontIndex());
        $this->assertEquals(2, $font->getFontFileIndex());
        $this->assertEquals(3, $font->getFontDescIndex());
        $this->assertEquals(4, $font->getFontObjectIndex());
        $this->assertEquals(5, $font->getCidFontObjectIndex());
        $this->assertEquals(6, $font->getToUnicodeIndex());
        $this->assertEquals(5, count($font->getObjects()));
    }

    public function testParseTrueTypeEmitsCidFontStructure()
    {
        $font = new Parser(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf');
        $font->setFontIndex(1)
            ->setFontObjectIndex(2)
            ->setCidFontObjectIndex(3)
            ->setFontDescIndex(4)
            ->setFontFileIndex(5)
            ->setToUnicodeIndex(6);
        $font->parse();

        $objects = $font->getObjects();
        $this->assertCount(5, $objects);

        $type0 = (string) $objects[2];
        $this->assertStringContainsString('/Subtype /Type0', $type0);
        $this->assertStringContainsString('/Encoding /Identity-H', $type0);
        $this->assertStringContainsString('/DescendantFonts [3 0 R]', $type0);
        $this->assertStringContainsString('/ToUnicode 6 0 R', $type0);
        $this->assertStringContainsString('/BaseFont /DejaVuSans', $type0);

        $cidFont = (string) $objects[3];
        $this->assertStringContainsString('/Subtype /CIDFontType2', $cidFont);
        $this->assertStringContainsString('/CIDToGIDMap /Identity', $cidFont);
        $this->assertStringContainsString('/DW 600', $cidFont);
        $this->assertStringContainsString('/FontDescriptor 4 0 R', $cidFont);

        $toUnicode = (string) $objects[6];
        $this->assertStringContainsString('beginbfchar', $toUnicode);
        // GID 948 (Cyrillic 'П', U+041F) must round-trip through the CMap.
        $this->assertStringContainsString('<03B4> <041F>', $toUnicode);
    }

    public function testParseTrueTypeMissingCidIndicesException()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $font = new Parser(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf');
        $font->setFontIndex(1)->setFontObjectIndex(2)->setFontDescIndex(4)->setFontFileIndex(5);
        $font->parse();
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