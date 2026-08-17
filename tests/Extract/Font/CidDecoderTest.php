<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Font\CidDecoder;
use Pop\Pdf\Extract\Font\FontInfo;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class CidDecoderTest extends TestCase
{

    public function testIdentityHWithToUnicodePresent()
    {
        $toUnicode = [
            'bfMappings'      => [0x0041 => 'H', 0x0042 => 'i'],
            'cidMappings'     => [],
            'codespaceRanges' => [['lo' => "\x00\x00", 'hi' => "\xFF\xFF", 'length' => 2]],
        ];
        $info = new FontInfo(true, new Value\Name('Identity-H'), $toUnicode, 'Identity', null);

        $rawBytes = "\x00\x41\x00\x42"; // two 2-byte codes: 0x0041, 0x0042
        $this->assertEquals('Hi', CidDecoder::decode($rawBytes, $info));
    }

    public function testTwoByteCodeSplittingWithoutToUnicode()
    {
        // No ToUnicode and no embedded font bytes - fallback has nothing to
        // work with, so unmapped codes are silently skipped (never guessed).
        $info = new FontInfo(true, new Value\Name('Identity-H'), null, 'Identity', null);

        $rawBytes = "\x00\x41\x00\x42";
        $this->assertEquals('', CidDecoder::decode($rawBytes, $info));
    }

    public function testEmbeddedCMapEncodingDrivesCidMapping()
    {
        $encoding = [
            'cidMappings'     => [0x0100 => 5],
            'bfMappings'      => [],
            'codespaceRanges' => [['lo' => "\x00\x00", 'hi' => "\xFF\xFF", 'length' => 2]],
        ];
        $toUnicode = ['bfMappings' => [0x0100 => 'Q'], 'cidMappings' => [], 'codespaceRanges' => []];
        $info      = new FontInfo(true, $encoding, $toUnicode, 'Identity', null);

        // ToUnicode keys by CODE (not CID), so this still resolves via the
        // code-keyed bfMappings regardless of the CID the encoding produces.
        $this->assertEquals('Q', CidDecoder::decode("\x01\x00", $info));
    }

    public function testCidToGidTreatsIdentityMarkerAsIdentityMapping()
    {
        $method = new \ReflectionMethod(CidDecoder::class, 'cidToGid');

        for ($cid = 0; $cid <= 4; $cid++) {
            $this->assertEquals($cid, $method->invoke(null, $cid, 'Identity'));
        }
    }

    public function testCidToGidStillAppliesRealCidToGidMapBytes()
    {
        $method = new \ReflectionMethod(CidDecoder::class, 'cidToGid');

        // cid0->gid0, cid1->gid5, cid2->gid10
        $map = "\x00\x00\x00\x05\x00\x0a";

        $this->assertEquals(0, $method->invoke(null, 0, $map));
        $this->assertEquals(5, $method->invoke(null, 1, $map));
        $this->assertEquals(10, $method->invoke(null, 2, $map));
    }

    public function testReverseCmapIsCachedPerFontInfoInstance()
    {
        $fontBytes = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $info      = new FontInfo(true, new Value\Name('Identity-H'), null, 'Identity', $fontBytes);

        $first  = CidDecoder::decode("\x00\x41", $info);
        $second = CidDecoder::decode("\x00\x41", $info);

        $this->assertEquals($first, $second);

        $cacheProp = new \ReflectionProperty(CidDecoder::class, 'reverseCmapCache');
        $weakMap = $cacheProp->getValue();

        $this->assertTrue(isset($weakMap[$info]));
    }

    public function testSplitCodesFallsBackToTwoByteWidthWhenCodespaceLengthInvalid()
    {
        $method = new \ReflectionMethod(CidDecoder::class, 'splitCodes');

        // A codespace range with a zero (invalid) length must not be taken
        // literally - splitCodes falls back to the standard 2-byte width.
        $encoding = ['codespaceRanges' => [['lo' => '', 'hi' => '', 'length' => 0]]];
        $codes    = $method->invoke(null, "\x00\x41\x00\x42", $encoding);

        $this->assertEquals([0x0041, 0x0042], $codes);
    }

    public function testCodeToCidUsesEncodingCidMappingWhenPresentElseIdentity()
    {
        $method   = new \ReflectionMethod(CidDecoder::class, 'codeToCid');
        $encoding = ['cidMappings' => [0x0100 => 42], 'bfMappings' => [], 'codespaceRanges' => []];

        $this->assertEquals(42, $method->invoke(null, 0x0100, $encoding));
        // A code with no entry in cidMappings falls back to identity (CID == code).
        $this->assertEquals(0x0200, $method->invoke(null, 0x0200, $encoding));
    }

    public function testCidToGidReturnsIdentityWhenCidFallsOutsideMapBytes()
    {
        $method = new \ReflectionMethod(CidDecoder::class, 'cidToGid');

        // Map only defines cid 0 and cid 1 (4 bytes); cid 5 (offset 10) falls
        // past the end of the map, so it must fall back to identity.
        $map = "\x00\x00\x00\x05";

        $this->assertEquals(5, $method->invoke(null, 5, $map));
    }

    public function testBuildReverseCmapReturnsEmptyWhenFontHasNoCmapTable()
    {
        // A minimal, structurally-valid TTF directory (numberOfTables = 0)
        // parses successfully but never populates $font->tables['cmap'].
        $header = pack('n*', 1, 0, 0, 0, 0, 0) . str_repeat("\x00", 4) . pack('N*', 0, 0, 0);

        $method = new \ReflectionMethod(CidDecoder::class, 'buildReverseCmap');

        $this->assertEquals([], $method->invoke(null, $header));
    }

    public function testBuildReverseCmapReturnsEmptyWhenFontParsingThrows()
    {
        // A 'head' table advertising unitsPerEm = 0 makes TrueType's internal
        // em-space conversion divide by zero while parsing - buildReverseCmap
        // must swallow that (any embedded-font parse failure) instead of
        // letting it escape text extraction.
        $header     = pack('n*', 1, 0, 1, 0, 0, 0) . str_repeat("\x00", 4) . pack('N*', 0, 0, 0);
        $tableEntry = 'head' . pack('N*', 0, 44, 54); // offset 44 = 28-byte header + one 16-byte entry
        $headBody   = str_repeat("\x00", 54);         // unitsPerEm bytes (rel. offset 18-20) left at 0
        $font       = $header . $tableEntry . $headBody;

        $method = new \ReflectionMethod(CidDecoder::class, 'buildReverseCmap');

        $this->assertEquals([], $method->invoke(null, $font));
    }

}
