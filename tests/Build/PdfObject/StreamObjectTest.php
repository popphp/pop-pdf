<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\StreamObject;
use PHPUnit\Framework\TestCase;

class StreamObjectTest extends TestCase
{

    public function testParseDeclaredLengthMatchesActualStreamLength()
    {
        // Mirrors the raw object template Build\Image\Parser builds for embedded
        // images: a fixed /Length computed from the raw binary, a single EOL
        // after 'stream' and a single EOL before 'endstream', neither of which
        // are part of the declared length.
        $imageData = random_bytes(64);
        $raw = "5 0 obj\n<<\n    /Type /XObject\n    /Subtype /Image\n    /Filter /DCTDecode\n    /Length " .
            strlen($imageData) . "\n>>\nstream\n{$imageData}\nendstream\nendobj\n";

        $object = StreamObject::parse($raw);
        $output = (string)$object;

        preg_match('/\/Length\s+(\d+)/', $output, $lengthMatch);
        $declaredLength = (int)$lengthMatch[1];

        $streamStart = strpos($output, 'stream') + strlen("stream\n");
        $streamEnd   = strpos($output, 'endstream', $streamStart);
        $actualLength = $streamEnd - $streamStart - 1; // trailing EOL before 'endstream' is not part of the data

        $this->assertEquals($declaredLength, $actualLength);
        $this->assertEquals(strlen($imageData), $declaredLength);
    }

    public function testParseStripsTrailingCarriageReturnLinefeedBeforeEndstream()
    {
        $raw = "5 0 obj\n<< /Length 5 >>\nstream\nhello\r\nendstream\nendobj\n";

        $object = StreamObject::parse($raw);

        $this->assertEquals("\nhello", $object->getStream());
    }

    public function testParseWithoutAStreamKeywordSetsDefinitionOnly()
    {
        $raw = "5 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n";

        $object = StreamObject::parse($raw);

        $this->assertNull($object->getStream());
        $this->assertStringContainsString('/Type /Pages', $object->getDefinition());
    }

    public function testLengthReplacementDoesNotCorruptCollidingDigitsOrIndirectReferences()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Length 6 0 R /Width 384 /Height 6 >>');
        $object->appendStream('BT ET ');

        $result = (string) $object;

        $this->assertStringNotContainsString('/Length 6 0 R', $result);
        $this->assertStringContainsString('/Width 384', $result);
        $this->assertStringContainsString('/Height 6', $result);
        $this->assertMatchesRegularExpression('/\/Length \d+(?!\s+\d+\s+R)/', $result);
    }

    public function testSetDefinitionDetectsEncodingFilter()
    {
        $filters = [
            '/ASCIIHexDecode'  => 'ASCIIHexDecode',
            '/ASCII85Decode'   => 'ASCII85Decode',
            '/LZWDecode'       => 'LZWDecode',
            '/FlateDecode'     => 'FlateDecode',
            '/RunLengthDecode' => 'RunLengthDecode',
            '/CCITTFaxDecode'  => 'CCITTFaxDecode',
            '/JBIG2Decode'     => 'JBIG2Decode',
            '/DCTDecode'       => 'DCTDecode',
            '/JPXDecode'       => 'JPXDecode',
            '/Crypt'           => 'Crypt',
        ];

        foreach ($filters as $filter => $expected) {
            $object = new StreamObject(5);
            $object->setDefinition('<< /Filter ' . $filter . ' >>');

            $this->assertTrue($object->isEncoded());
            $this->assertEquals($expected, $object->getEncoding());
        }
    }

    public function testSetDefinitionDetectsXObject()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Type /XObject /Subtype /Image >>');

        $this->assertTrue($object->isXObject());
    }

    public function testSetDefinitionWithoutXObjectLeavesFlagFalse()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Length 10 >>');

        $this->assertFalse($object->isXObject());
        $this->assertFalse($object->isEncoded());
        $this->assertNull($object->getEncoding());
    }

    public function testSetStreamOverwritesRatherThanAppends()
    {
        $object = new StreamObject(5);
        $object->appendStream('first');
        $object->setStream('second');

        $this->assertEquals('second', $object->getStream());
    }

    public function testEncodeCompressesStreamWithFlateDecode()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Length 0 >>');
        $object->appendStream(str_repeat('A', 200));
        $object->encode();

        $this->assertEquals('FlateDecode', $object->getEncoding());
        $this->assertEquals(gzuncompress(trim($object->getStream())), str_repeat('A', 200));
    }

    public function testEncodeSkipsImageStreams()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Subtype /Image >>');
        $object->appendStream('raw-image-bytes');
        $object->encode();

        $this->assertNull($object->getEncoding());
        $this->assertEquals('raw-image-bytes', $object->getStream());
    }

    public function testEncodeSkipsAlreadyFlateEncodedStreams()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Filter /FlateDecode >>');
        $object->appendStream('already-compressed');
        $object->encode();

        $this->assertEquals('already-compressed', $object->getStream());
    }

    public function testDecodeReturnsUncompressedStream()
    {
        $object = new StreamObject(5);
        $object->setStream(gzcompress('decoded content', 9));

        $this->assertEquals('decoded content', $object->decode());
    }

    public function testDecodeReturnsFalseForEmptyStream()
    {
        $object = new StreamObject(5);

        $this->assertFalse($object->decode());
    }

    public function testPaletteFlagDefaultsFalseAndIsSettable()
    {
        $object = new StreamObject(5);
        $this->assertFalse($object->isPalette());

        $object->setPalette(true);
        $this->assertTrue($object->isPalette());
    }

    public function testGetByteLengthReflectsStringOutputLength()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Length 0 >>');
        $object->appendStream('some bytes');

        $this->assertEquals(strlen((string)$object), $object->getByteLength());
    }

    public function testToStringAddsLengthDefinitionWhenAbsent()
    {
        $object = new StreamObject(5);
        $object->setDefinition('<< /Type /Page >>');
        $object->appendStream('content');

        $result = (string) $object;

        $this->assertMatchesRegularExpression('/\/Length \d+/', $result);
    }

    public function testToStringClearsZeroLengthDefinition()
    {
        // No definition and no stream means byte length resolves to 0 and
        // the else-branch appends the literal "<</Length [{byte_length}]>>"
        // template (no spaces) - the only way to reach that exact string
        // the zero-clearing check matches against.
        $object = new StreamObject(5);

        $result = (string) $object;

        $this->assertStringNotContainsString('<</Length 0>>', $result);
    }

}
