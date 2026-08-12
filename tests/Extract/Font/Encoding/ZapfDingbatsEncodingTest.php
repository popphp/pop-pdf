<?php

namespace Pop\Pdf\Test\Extract\Font\Encoding;

use Pop\Pdf\Extract\Font\Encoding\ZapfDingbatsEncoding;
use PHPUnit\Framework\TestCase;

class ZapfDingbatsEncodingTest extends TestCase
{

    public function testSpaceIsMapped()
    {
        $this->assertEquals(0x0020, ZapfDingbatsEncoding::TABLE[0x20]);
    }

    public function testOtherBytesAreUnmapped()
    {
        $this->assertArrayNotHasKey(0x41, ZapfDingbatsEncoding::TABLE);
    }

}
