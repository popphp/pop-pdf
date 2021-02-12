<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Path;
use Pop\Pdf\Document\Page\Color;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{

    public function testSetFillColor()
    {
        $path = new Path();
        $this->assertEquals(Path::STROKE, $path->getStyle());
        $path->setFillColor(new Color\Rgb(255, 0, 0));
        $this->assertStringContainsString('rg', $path->getStreams()[0]['stream']);
        $path->setFillColor(new Color\Cmyk(100, 0, 0, 0));
        $this->assertStringContainsString('k', $path->getStreams()[1]['stream']);
        $path->setFillColor(new Color\Gray(50));
        $this->assertStringContainsString('g', $path->getStreams()[2]['stream']);
    }

    public function testSetStrokeColor()
    {
        $path = new Path();
        $path->setStrokeColor(new Color\Rgb(255, 0, 0));
        $this->assertStringContainsString('RG', $path->getStreams()[0]['stream']);
        $path->setStrokeColor(new Color\Cmyk(100, 0, 0, 0));
        $this->assertStringContainsString('K', $path->getStreams()[1]['stream']);
        $path->setStrokeColor(new Color\Gray(50));
        $this->assertStringContainsString('G', $path->getStreams()[2]['stream']);
    }

    public function testSetStroke()
    {
        $path = new Path();
        $path->setStroke(5, 10, 15);
        $this->assertStringContainsString('0 d', $path->getStreams()[0]['stream']);
    }

    public function testOpenLayer()
    {
        $path = new Path();
        $path->openLayer();
        $this->assertStringContainsString('q', $path->getStreams()[0]['stream']);
    }

    public function testCloseLayer()
    {
        $path = new Path();
        $path->closeLayer();
        $this->assertStringContainsString('Q', $path->getStreams()[0]['stream']);
    }

    public function testDrawLine()
    {
        $path = new Path();
        $path->drawLine(320, 240, 200, 200);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawRectangle()
    {
        $path = new Path();
        $path->drawRectangle(320, 240, 200);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y']);
    }

    public function testDrawRoundedRectangle()
    {
        $path = new Path();
        $path->drawRoundedRectangle(320, 240, 200, null, 10, 5);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(245, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawSquare()
    {
        $path = new Path();
        $path->drawSquare(320, 240, 200);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y']);
    }

    public function testDrawRoundedSquare()
    {
        $path = new Path();
        $path->drawRoundedSquare(320, 240, 200, 10);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(250, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawPolygon()
    {
        $path = new Path();
        $path->drawPolygon([
            ['x' => 300, 'y' => 200],
            ['x' => 350, 'y' => 250],
            ['x' => 400, 'y' => 300],
            ['x' => 450, 'y' => 350],
            ['x' => 500, 'y' => 400]
        ]);
        $this->assertEquals(300, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(200, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawPolygonException()
    {
        $this->expectException('Pop\Pdf\Document\Page\Exception');
        $path = new Path();
        $path->drawPolygon([[]]);
    }

    public function testDrawEllipse()
    {
        $path = new Path();
        $path->drawEllipse(320, 240, 200);
        $this->assertEquals(520, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawCircle()
    {
        $path = new Path();
        $path->drawCircle(320, 240, 200);
        $this->assertEquals(520, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawArc()
    {
        $path = new Path();
        $path->drawArc(320, 240, 30, 100, 200);
        $this->assertEquals(493, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(340, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawChord()
    {
        $path = new Path();
        $path->drawChord(320, 240, 30, 100, 200);
        $this->assertEquals(493, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(340, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawPie()
    {
        $path = new Path();
        $path->drawPie(320, 240, 30, 100, 200);
        $this->assertEquals(493, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(340, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawOpenCubicBezierCurve()
    {
        $path = new Path();
        $path->drawOpenCubicBezierCurve(320, 240, 200, 200, 150, 150, 250, 250);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawClosedCubicBezierCurve()
    {
        $path = new Path();
        $path->drawClosedCubicBezierCurve(320, 240, 200, 200, 150, 150, 250, 250);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawOpenQuadraticBezierCurve()
    {
        $path = new Path();
        $path->drawOpenQuadraticBezierCurve(320, 240, 200, 200, 150, 150);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testDrawClosedQuadraticBezierCurve()
    {
        $path = new Path();
        $path->drawClosedQuadraticBezierCurve(320, 240, 200, 200, 150, 150);
        $this->assertEquals(320, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(240, $path->getStreams()[0]['points'][0]['y1']);
    }

    public function testCalculateDegreesException1()
    {
        $this->expectException('OutOfRangeException');
        $path = new Path();
        $path->drawArc(320, 240, -50, 100, 200);
    }

    public function testCalculateDegreesException2()
    {
        $this->expectException('OutOfRangeException');
        $path = new Path();
        $path->drawArc(320, 240, 150, 100, 200);
    }

    public function testCalculateDegrees()
    {
        $path = new Path();
        $path->drawArc(320, 240, 30, 80, 200);
        $path->drawArc(320, 240, 30, 190, 200);
        $path->drawArc(320, 240, 30, 320, 200);
        $path->drawArc(320, 240, 100, 135, 200);
        $path->drawArc(320, 240, 100, 220, 200);
        $path->drawArc(320, 240, 100, 320, 200);
        $path->drawArc(320, 240, 190, 220, 200);
        $path->drawArc(320, 240, 190, 320, 200);
        $this->assertEquals(493, $path->getStreams()[0]['points'][0]['x1']);
        $this->assertEquals(340, $path->getStreams()[0]['points'][0]['y1']);
    }

}