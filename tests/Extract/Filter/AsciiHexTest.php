<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Filter\AsciiHex;
use PHPUnit\Framework\TestCase;

class AsciiHexTest extends TestCase
{

    public function testDecode()
    {
        $filter = new AsciiHex();
        $this->assertEquals('Hello', $filter->decode('48656C6C6F>'));
    }

    public function testDecodeOddDigits()
    {
        $filter = new AsciiHex();
        $this->assertEquals("Hello\x00", $filter->decode('48656C6C6F0'));
    }

}
