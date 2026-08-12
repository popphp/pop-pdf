<?php

namespace Pop\Pdf\Test\Extract\Xref;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Xref\Stream;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{

    protected function buildRow(int $type, int $field2, int $field3): string
    {
        return chr($type) . chr(($field2 >> 8) & 0xFF) . chr($field2 & 0xFF) . chr($field3 & 0xFF);
    }

    public function testParseXrefStream()
    {
        // W = [1 2 1]: type(1 byte), field2(2 bytes), field3(1 byte).
        $rows = $this->buildRow(1, 9, 0)      // obj 0: uncompressed, offset 9
              . $this->buildRow(1, 74, 0)     // obj 1: uncompressed, offset 74
              . $this->buildRow(2, 5, 3);     // obj 2: in object stream 5, index 3

        $compressed = gzcompress($rows);

        $data = "10 0 obj\n<< /Type /XRef /W [1 2 1] /Index [0 3] /Size 3 /Root 1 0 R /Filter /FlateDecode /Length "
            . strlen($compressed) . " >>\nstream\n" . $compressed . "\nendstream\nendobj";

        $result = Stream::parse($data, 0);

        $this->assertEquals(9, $result['offsets'][0]['offset']);
        $this->assertEquals(74, $result['offsets'][1]['offset']);
        $this->assertEquals(5, $result['offsets'][2]['inStream']);
        $this->assertEquals(3, $result['offsets'][2]['index']);
        $this->assertEquals(3, $result['trailer']['Size']);
    }

    public function testParseThrowsWhenObjectIsNotAStream()
    {
        // A well-formed object at the given position, but it's a plain
        // dictionary rather than a stream - Stream::parse() requires an
        // actual stream object.
        $data = "10 0 obj\n<< /Type /XRef >>\nendobj";

        $this->expectException(Exception::class);
        Stream::parse($data, 0);
    }

    public function testParseThrowsOnMalformedWArray()
    {
        // No /W entry at all in the stream dict.
        $data = "10 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj";

        $this->expectException(Exception::class);
        Stream::parse($data, 0);
    }

    public function testParseStopsAtTruncatedRow()
    {
        // /Index claims 5 objects but the decoded stream data only holds 2
        // full 4-byte rows - the loop must break out cleanly instead of
        // reading past the end of the decoded data.
        $rows = $this->buildRow(1, 100, 0) . $this->buildRow(1, 200, 0);

        $compressed = gzcompress($rows);
        $data = "10 0 obj\n<< /Type /XRef /W [1 2 1] /Index [0 5] /Size 5 /Root 1 0 R /Filter /FlateDecode /Length "
            . strlen($compressed) . " >>\nstream\n" . $compressed . "\nendstream\nendobj";

        $result = Stream::parse($data, 0);

        $this->assertEquals(100, $result['offsets'][0]['offset']);
        $this->assertEquals(200, $result['offsets'][1]['offset']);
        $this->assertArrayNotHasKey(2, $result['offsets']);
        $this->assertArrayNotHasKey(3, $result['offsets']);
        $this->assertArrayNotHasKey(4, $result['offsets']);
    }

    public function testParseSkipsDuplicateObjectNumberAcrossOverlappingIndexSegments()
    {
        // Two /Index segments overlap on object 1 - the second occurrence
        // must be skipped in favor of the first.
        $rows = $this->buildRow(1, 100, 0)  // segment 1: obj 0
              . $this->buildRow(1, 200, 0)  // segment 1: obj 1
              . $this->buildRow(1, 300, 0)  // segment 2: obj 1 (duplicate, should be skipped)
              . $this->buildRow(1, 400, 0); // segment 2: obj 2

        $compressed = gzcompress($rows);
        $data = "10 0 obj\n<< /Type /XRef /W [1 2 1] /Index [0 2 1 2] /Size 3 /Root 1 0 R /Filter /FlateDecode /Length "
            . strlen($compressed) . " >>\nstream\n" . $compressed . "\nendstream\nendobj";

        $result = Stream::parse($data, 0);

        $this->assertEquals(100, $result['offsets'][0]['offset']);
        $this->assertEquals(200, $result['offsets'][1]['offset']);
        $this->assertEquals(400, $result['offsets'][2]['offset']);
    }

    public function testParseWithZeroWidthFieldsReadsAsZero()
    {
        // W = [0 2 0]: type field absent (defaults to 1/uncompressed),
        // field3 absent entirely - readBigEndian() must return 0 for a
        // zero-width field rather than reading any bytes.
        $rows = chr(0) . chr(9) . chr(0) . chr(74); // obj 0 offset 9, obj 1 offset 74

        $compressed = gzcompress($rows);
        $data = "10 0 obj\n<< /Type /XRef /W [0 2 0] /Index [0 2] /Size 2 /Root 1 0 R /Filter /FlateDecode /Length "
            . strlen($compressed) . " >>\nstream\n" . $compressed . "\nendstream\nendobj";

        $result = Stream::parse($data, 0);

        $this->assertEquals(9, $result['offsets'][0]['offset']);
        $this->assertEquals(74, $result['offsets'][1]['offset']);
    }

}
