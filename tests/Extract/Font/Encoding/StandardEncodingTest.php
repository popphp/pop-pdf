<?php

namespace Pop\Pdf\Test\Extract\Font\Encoding;

use Pop\Pdf\Extract\Font\Encoding\StandardEncoding;
use PHPUnit\Framework\TestCase;

class StandardEncodingTest extends TestCase
{

    public function testAsciiRangeIsIdentity()
    {
        $this->assertEquals(0x0041, StandardEncoding::TABLE[0x41]);
        $this->assertEquals(0x007E, StandardEncoding::TABLE[0x7E]);
    }

    public function testUpperRangeIsIntentionallyUnmapped()
    {
        $this->assertArrayNotHasKey(0x80, StandardEncoding::TABLE);
        $this->assertArrayNotHasKey(0xFF, StandardEncoding::TABLE);
    }

    public function testQuoterightAndQuoteleftDeviateFromPlainAscii()
    {
        $this->assertEquals(0x2019, StandardEncoding::TABLE[0x27]);
        $this->assertEquals(0x2018, StandardEncoding::TABLE[0x60]);
    }

}
