<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Font\CMapParser;
use PHPUnit\Framework\TestCase;

class CMapParserTest extends TestCase
{

    public function testCodespaceRange()
    {
        $result = CMapParser::parse("1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange");

        $this->assertCount(1, $result['codespaceRanges']);
        $this->assertEquals("\x00\x00", $result['codespaceRanges'][0]['lo']);
        $this->assertEquals("\xFF\xFF", $result['codespaceRanges'][0]['hi']);
        $this->assertEquals(2, $result['codespaceRanges'][0]['length']);
    }

    public function testBfChar()
    {
        $result = CMapParser::parse("2 beginbfchar\n<0003> <0020>\n<0004> <0041>\nendbfchar");

        $this->assertEquals(' ', $result['bfMappings'][0x0003]);
        $this->assertEquals('A', $result['bfMappings'][0x0004]);
    }

    public function testBfRangeWithSequentialDestination()
    {
        $result = CMapParser::parse("1 beginbfrange\n<0005> <0009> <0042>\nendbfrange");

        $this->assertEquals('B', $result['bfMappings'][0x0005]);
        $this->assertEquals('C', $result['bfMappings'][0x0006]);
        $this->assertEquals('D', $result['bfMappings'][0x0007]);
        $this->assertEquals('E', $result['bfMappings'][0x0008]);
        $this->assertEquals('F', $result['bfMappings'][0x0009]);
    }

    public function testBfRangeWithArrayDestination()
    {
        $result = CMapParser::parse(
            "1 beginbfrange\n<000A> <000C> [<0058> <00660066> <0059>]\nendbfrange"
        );

        $this->assertEquals('X', $result['bfMappings'][0x000A]);
        $this->assertEquals('ff', $result['bfMappings'][0x000B]);
        $this->assertEquals('Y', $result['bfMappings'][0x000C]);
    }

    public function testCidChar()
    {
        $result = CMapParser::parse("2 begincidchar\n<0100> 500\n<0101> 501\nendcidchar");

        $this->assertEquals(500, $result['cidMappings'][0x0100]);
        $this->assertEquals(501, $result['cidMappings'][0x0101]);
    }

    public function testCidRange()
    {
        $result = CMapParser::parse("1 begincidrange\n<0000> <00FF> 0\nendcidrange");

        $this->assertEquals(0, $result['cidMappings'][0x0000]);
        $this->assertEquals(255, $result['cidMappings'][0x00FF]);
    }

    public function testIgnoresPostScriptBoilerplateOutsideSections()
    {
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n" .
            "1 beginbfchar\n<0001> <0041>\nendbfchar\n" .
            "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";

        $result = CMapParser::parse($cmap);

        $this->assertEquals('A', $result['bfMappings'][0x0001]);
    }

    public function testTruncatedSectionDegradesGracefullyInsteadOfThrowing()
    {
        // No endbfchar before the stream ends - must not throw, and must
        // keep the one mapping that was successfully parsed first.
        $result = CMapParser::parse("2 beginbfchar\n<0003> <0020>\n<0004>");

        $this->assertEquals(' ', $result['bfMappings'][0x0003]);
        $this->assertArrayNotHasKey(0x0004, $result['bfMappings']);
    }

    public function testBfrangeWithHugeSpanIsSkippedNotMaterialized()
    {
        $cmap = "1 beginbfrange\n<0000> <FFFFFF> <0041>\nendbfrange\n";
        $result = CMapParser::parse($cmap);
        $this->assertEmpty($result['bfMappings']);
    }

    public function testCidrangeWithHugeSpanIsSkippedNotMaterialized()
    {
        $cmap = "1 begincidrange\n<0000> <FFFFFF> 1\nendcidrange\n";
        $result = CMapParser::parse($cmap);
        $this->assertEmpty($result['cidMappings']);
    }

    public function testLegitimateBfrangeStillWorks()
    {
        $cmap = "1 beginbfrange\n<0041> <0043> <0061>\nendbfrange\n";
        $result = CMapParser::parse($cmap);
        $this->assertEquals(['a', 'b', 'c'], array_values($result['bfMappings']));
    }

    public function testOddLengthDestinationReturnsEmptyNotQuestionMark()
    {
        $method = new \ReflectionMethod(CMapParser::class, 'dstToUnicodeString');
        $this->assertEquals('', $method->invoke(null, "\x41"));
    }

    public function testAllNulDestinationReturnsEmptyNotRawNul()
    {
        $method = new \ReflectionMethod(CMapParser::class, 'dstToUnicodeString');
        $this->assertEquals('', $method->invoke(null, "\x00\x00"));
    }

    public function testMalformedTopLevelTokenStopsParsingButKeepsPriorResults()
    {
        // A stray ']' at the top level (outside any array) is a token type
        // parseValueFromToken() doesn't handle, so it throws - parse() must
        // catch that and return whatever was already parsed rather than
        // propagating the exception out of text extraction.
        $result = CMapParser::parse("1 beginbfchar\n<0003> <0020>\nendbfchar\n]");

        $this->assertEquals(' ', $result['bfMappings'][0x0003]);
    }

    public function testCodespaceRangeSkipsNonStringLoThenStillReadsNextRange()
    {
        $result = CMapParser::parse("1 begincodespacerange\n123 <0000> <FFFF>\nendcodespacerange");

        $this->assertCount(1, $result['codespaceRanges']);
        $this->assertEquals("\x00\x00", $result['codespaceRanges'][0]['lo']);
    }

    public function testCodespaceRangeSkipsNonStringHi()
    {
        $result = CMapParser::parse("1 begincodespacerange\n<0000> 123\nendcodespacerange");

        $this->assertEmpty($result['codespaceRanges']);
    }

    public function testCodespaceRangeTruncatedSectionDegradesGracefully()
    {
        $result = CMapParser::parse("1 begincodespacerange\n<0000>");

        $this->assertEmpty($result['codespaceRanges']);
    }

    public function testBfCharSkipsNonStringCode()
    {
        $result = CMapParser::parse("1 beginbfchar\n123 <0041>\nendbfchar");

        $this->assertEmpty($result['bfMappings']);
    }

    public function testBfRangeSkipsNonStringLoThenStillReadsNextRange()
    {
        $result = CMapParser::parse("1 beginbfrange\n123 <000A> <000C> <0041>\nendbfrange");

        $this->assertEquals('A', $result['bfMappings'][0x000A]);
        $this->assertEquals('C', $result['bfMappings'][0x000C]);
    }

    public function testBfRangeTruncatedSectionDegradesGracefully()
    {
        $result = CMapParser::parse("1 beginbfrange\n<0000> <0002>");

        $this->assertEmpty($result['bfMappings']);
    }

    public function testCidCharSkipsNonStringCodeThenStillReadsNextPair()
    {
        $result = CMapParser::parse("1 begincidchar\n123 <0100> 500\nendcidchar");

        $this->assertEquals(500, $result['cidMappings'][0x0100]);
    }

    public function testCidCharTruncatedSectionDegradesGracefully()
    {
        $result = CMapParser::parse("1 begincidchar\n<0100>");

        $this->assertEmpty($result['cidMappings']);
    }

    public function testCidRangeSkipsNonStringLoThenStillReadsNextRange()
    {
        $result = CMapParser::parse("1 begincidrange\n123 <0000> <0005> 0\nendcidrange");

        $this->assertEquals(0, $result['cidMappings'][0x0000]);
        $this->assertEquals(5, $result['cidMappings'][0x0005]);
    }

    public function testCidRangeTruncatedSectionDegradesGracefully()
    {
        $result = CMapParser::parse("1 begincidrange\n<0000>");

        $this->assertEmpty($result['cidMappings']);
    }

}
