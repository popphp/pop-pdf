<?php

namespace Pop\Pdf\Test\Extract\Xref;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Xref\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{

    public function testParseClassicXref()
    {
        $data = "xref\n0 3\n" .
            "0000000000 65535 f \n" .
            "0000000009 00000 n \n" .
            "0000000074 00000 n \n" .
            "trailer\n<< /Size 3 /Root 1 0 R >>\n" .
            "startxref\n0\n%%EOF";

        $result = Table::parse($data, 0);

        $this->assertArrayNotHasKey(0, $result['offsets']);
        $this->assertEquals(9, $result['offsets'][1]['offset']);
        $this->assertEquals(74, $result['offsets'][2]['offset']);
        $this->assertEquals(3, $result['trailer']['Size']);
        $this->assertInstanceOf(\Pop\Pdf\Extract\Value\Reference::class, $result['trailer']['Root']);
        $this->assertEquals(1, $result['trailer']['Root']->objNum);
    }

    public function testParseMultipleSubsections()
    {
        $data = "xref\n0 1\n0000000000 65535 f \n3 2\n0000000100 00000 n \n0000000200 00000 n \ntrailer\n<< /Size 5 /Root 3 0 R >>";

        $result = Table::parse($data, 0);

        $this->assertEquals(100, $result['offsets'][3]['offset']);
        $this->assertEquals(200, $result['offsets'][4]['offset']);
    }

    public function testMalformedSubsectionCountDoesNotHangAndThrows()
    {
        // A subsection header declaring far more entries than the data
        // could possibly contain must be rejected quickly (clamped/EOF
        // detected) rather than looping the entry reader near-forever.
        $data = "xref\n0 100000000\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n0\n%%EOF";

        $this->expectException(Exception::class);

        Table::parse($data, 0);
    }

    public function testParseThrowsWhenMissingXrefKeyword()
    {
        $this->expectException(Exception::class);
        Table::parse("notxref\n0 1\n", 0);
    }

    public function testParseThrowsOnMalformedSubsectionHeader()
    {
        // Count token is not a number.
        $this->expectException(Exception::class);
        Table::parse("xref\n0 abc\ntrailer\n<< /Size 1 >>", 0);
    }

    public function testParseThrowsOnUnexpectedEofMidSubsection()
    {
        // Declared count (5) isn't clamped away entirely (there's just
        // enough trailing data to survive the plausibility clamp) but real
        // data still runs out mid-entry before 5 full entries are read.
        $data = "xref\n0 5\n0000000009 00000 n \n";

        $this->expectException(Exception::class);
        Table::parse($data, 0);
    }

    public function testParseThrowsOnNonDictTrailer()
    {
        $data = "xref\n0 1\n0000000000 65535 f \ntrailer\n5";

        $this->expectException(Exception::class);
        Table::parse($data, 0);
    }

}
