<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{

    public function testSetAndGetRgbValues()
    {
        $color = new Color\Rgb(255, 128, 0);
        $this->assertEquals(255, $color->getR());
        $this->assertEquals(128, $color->getG());
        $this->assertEquals(0, $color->getB());
    }

    public function testSetAndGetCmykValues()
    {
        $color = new Color\Cmyk(100, 75, 50, 0);
        $this->assertEquals(100, $color->getC());
        $this->assertEquals(75, $color->getM());
        $this->assertEquals(50, $color->getY());
        $this->assertEquals(0, $color->getK());
    }

    public function testSetAndGetGrayValue()
    {
        $color = new Color\Gray(50);
        $this->assertEquals(50, $color->getGray());
    }

    public function testSetROutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Rgb(300, 128, 0);
    }

    public function testSetGOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Rgb(255, -10, 0);
    }

    public function testSetBOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Rgb(255, 128, 400);
    }

    public function testSetCOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Cmyk(1000, 75, 50, 0);
    }

    public function testSetMOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Cmyk(100, 1000, 75, 50);
    }

    public function testSetYOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Cmyk(100, 75, -10, 50);
    }

    public function testSetKOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Cmyk(100, 75, 10, -50);
    }

    public function testSetGrayOutOfRangeException()
    {
        $this->expectException('OutOfRangeException');
        $color = new Color\Gray(1000);
    }

}