<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Ascii85;
use PHPUnit\Framework\TestCase;

class Ascii85Test extends TestCase
{

    public function testDecodeKnownValue()
    {
        // "Man " encodes to "9jqo^" in Adobe ASCII85 (classic reference example).
        $filter = new Ascii85();
        $this->assertEquals('Man ', $filter->decode('<~9jqo^~>'));
    }

    public function testDecodeZAbbreviation()
    {
        $filter = new Ascii85();
        $this->assertEquals("\0\0\0\0", $filter->decode('<~z~>'));
    }

    public function testDecodeUnderCapSucceeds()
    {
        // The 'z' shortcut expands 1 input byte to 4 output bytes -
        // comfortably under the 64MB cap here.
        $filter  = new Ascii85();
        $encoded = str_repeat('z', 1024 * 1024);

        $result = $filter->decode($encoded);

        $this->assertEquals(4 * 1024 * 1024, strlen($result));
    }

    public function testDecodeAdversarialZExpansionThrows()
    {
        // The 'z' shortcut's 4:1 expansion has no cap of its own - a stream
        // of enough 'z' characters decodes past 64MB from a small input.
        $this->expectException(Exception::class);

        // 67108864 (cap) / 4 bytes-per-'z' = 16777216 'z' chars needed to
        // reach the cap exactly - a small margin over that crosses it
        // without wasting time on Ascii85's per-character decode loop.
        $filter  = new Ascii85();
        $encoded = str_repeat('z', 16800000);

        $filter->decode($encoded);
    }

    public function testDecodePartialTrailingGroup()
    {
        // A data length not a multiple of 4 bytes leaves a short final
        // group (2-4 chars instead of 5) that must be padded with 'u' and
        // truncated back down on decode.
        $filter = new Ascii85();

        $this->assertEquals('Ma', $filter->decode('9jn'));
        $this->assertEquals('M', $filter->decode('9`'));
        $this->assertEquals('Man', $filter->decode('9jqo'));
    }

    public function testDecodeGroupOverflowThrows()
    {
        // "uuuuu" is the maximum representable 5-character group
        // (85^5 - 1 = 4437053124), which exceeds the 32-bit unsigned range
        // (4294967295) a valid ASCII85 group must decode to.
        $this->expectException(Exception::class);

        $filter = new Ascii85();
        $filter->decode('uuuuu');
    }

}
