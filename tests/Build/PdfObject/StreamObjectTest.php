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

}
