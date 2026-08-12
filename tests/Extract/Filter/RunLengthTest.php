<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\RunLength;
use PHPUnit\Framework\TestCase;

class RunLengthTest extends TestCase
{

    public function testLiteralRun()
    {
        $filter = new RunLength();
        // length byte 4 = copy next 5 literal bytes.
        $this->assertEquals('ABCDE', $filter->decode(chr(4) . 'ABCDE' . chr(128)));
    }

    public function testRepeatRun()
    {
        $filter = new RunLength();
        // length byte 257-4=253 -> repeat next byte 4 times.
        $this->assertEquals('XXXX', $filter->decode(chr(253) . 'X' . chr(128)));
    }

    public function testDecodeUnderCapSucceeds()
    {
        // length byte 129 -> repeat next byte 257-129=128 times, the
        // maximum ratio the RunLengthDecode format allows.
        $pairs   = (int) ceil((1024 * 1024) / 128);
        $encoded = str_repeat(chr(129) . 'X', $pairs) . chr(128);
        $filter  = new RunLength();

        $result = $filter->decode($encoded);

        $this->assertLessThan(67108864, strlen($result));
    }

    public function testDecodeAdversarialMaxRatioThrows()
    {
        // At the format's maximum 128:1 ratio, a stream sized to decode to
        // well over 64MB.
        $this->expectException(Exception::class);

        $pairs   = (int) ceil((70 * 1024 * 1024) / 128);
        $encoded = str_repeat(chr(129) . 'X', $pairs) . chr(128);
        $filter  = new RunLength();

        $filter->decode($encoded);
    }

}
