<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Font\FontInfo;
use Pop\Pdf\Extract\Font\SimpleDecoder;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class SimpleDecoderTest extends TestCase
{

    public function testDecodesWithWinAnsiEncoding()
    {
        $info = new FontInfo(false, new Value\Name('WinAnsiEncoding'), null, null, null);

        $this->assertEquals('Hello', SimpleDecoder::decode('Hello', $info));
    }

    public function testDifferencesOverrideBaseEncoding()
    {
        $encoding = [
            'BaseEncoding' => new Value\Name('WinAnsiEncoding'),
            'Differences'  => [65, new Value\Name('bullet')],
        ];
        $info = new FontInfo(false, $encoding, null, null, null);

        // Byte 65 ('A' in WinAnsi) is overridden to bullet (U+2022) by Differences.
        $this->assertEquals("\xE2\x80\xA2", SimpleDecoder::decode(chr(65), $info));
    }

    public function testDifferencesWithUniGlyphName()
    {
        $encoding = [
            'BaseEncoding' => new Value\Name('WinAnsiEncoding'),
            'Differences'  => [65, new Value\Name('uni20AC')],
        ];
        $info = new FontInfo(false, $encoding, null, null, null);

        $this->assertEquals("\xE2\x82\xAC", SimpleDecoder::decode(chr(65), $info)); // Euro sign
    }

    public function testToUnicodeOverridesEncoding()
    {
        $toUnicode = ['bfMappings' => [65 => 'Z'], 'cidMappings' => [], 'codespaceRanges' => []];
        $info      = new FontInfo(false, new Value\Name('WinAnsiEncoding'), $toUnicode, null, null);

        $this->assertEquals('Z', SimpleDecoder::decode(chr(65), $info));
    }

    public function testUnmappedByteIsSkipped()
    {
        $info = new FontInfo(false, new Value\Name('ZapfDingbats'), null, null, null);

        // Byte 65 has no entry in the minimal ZapfDingbats stub table.
        $this->assertEquals('', SimpleDecoder::decode(chr(65), $info));
    }

    public function testBuildTableIsCachedPerFontInfoInstance()
    {
        $fontBytes = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $info      = new FontInfo(false, null, null, null, $fontBytes);

        // First call builds and caches the table (exercises detectFallbackEncoding's
        // TrueType parse); second call with the SAME FontInfo instance must hit the
        // cache. Correctness (not just speed) is what this test proves.
        $first  = SimpleDecoder::decode('Hello', $info);
        $second = SimpleDecoder::decode('Hello', $info);

        $this->assertEquals($first, $second);
        $this->assertEquals('Hello', $first);

        $cacheProp = new \ReflectionProperty(SimpleDecoder::class, 'tableCache');
        $weakMap = $cacheProp->getValue();

        $this->assertTrue(isset($weakMap[$info]));
    }

    public function testBuildTableCacheIsPerInstanceNotShared()
    {
        $fontBytes = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $infoA     = new FontInfo(false, null, null, null, $fontBytes);
        $infoB     = new FontInfo(false, new Value\Name('MacRomanEncoding'), null, null, null);

        SimpleDecoder::decode('Hello', $infoA);
        SimpleDecoder::decode('Hello', $infoB);

        $cacheProp = new \ReflectionProperty(SimpleDecoder::class, 'tableCache');
        $weakMap = $cacheProp->getValue();

        $this->assertTrue(isset($weakMap[$infoA]));
        $this->assertTrue(isset($weakMap[$infoB]));
    }

    public function testSymbolEncodingBuildsGreekTableAndSkipsUnmappedBytes()
    {
        $info = new FontInfo(false, new Value\Name('Symbol'), null, null, null);

        // 'a' (0x61) maps to Greek alpha (U+03B1); 'j' (0x6A) is an
        // intentionally-unmapped Symbol-font byte and must be silently skipped.
        $result = SimpleDecoder::decode(chr(0x61) . chr(0x6A), $info);

        $this->assertEquals("\xCE\xB1", $result);
    }

    public function testDetectFallbackEncodingReturnsWinAnsiWhenEmbeddedFontParsingThrows()
    {
        // A 'head' table advertising unitsPerEm = 0 makes TrueType's internal
        // em-space conversion divide by zero while parsing - the fallback
        // detector must swallow that and default to WinAnsiEncoding rather
        // than letting the exception escape.
        $header     = pack('n*', 1, 0, 1, 0, 0, 0) . str_repeat("\x00", 4) . pack('N*', 0, 0, 0);
        $tableEntry = 'head' . pack('N*', 0, 44, 54);
        $headBody   = str_repeat("\x00", 54); // unitsPerEm bytes (rel. offset 18-20) left at 0
        $fontBytes  = $header . $tableEntry . $headBody;

        $method = new \ReflectionMethod(SimpleDecoder::class, 'detectFallbackEncoding');

        $this->assertEquals('WinAnsiEncoding', $method->invoke(null, $fontBytes));
    }

    public function testDetectFallbackEncodingReturnsWinAnsiWhenCmapHasNoMacRomanSubtable()
    {
        // A 'cmap' table that parses successfully but declares zero
        // subtables falls all the way through the subtable scan loop
        // without ever matching Mac Roman/format 0.
        $header     = pack('n*', 1, 0, 1, 0, 0, 0) . str_repeat("\x00", 4) . pack('N*', 0, 0, 0);
        $tableEntry = 'cmap' . pack('N*', 0, 44, 4);
        $cmapBody   = pack('n*', 0, 0); // tableVersion = 0, numberOfTables = 0
        $fontBytes  = $header . $tableEntry . $cmapBody;

        $method = new \ReflectionMethod(SimpleDecoder::class, 'detectFallbackEncoding');

        $this->assertEquals('WinAnsiEncoding', $method->invoke(null, $fontBytes));
    }

}
