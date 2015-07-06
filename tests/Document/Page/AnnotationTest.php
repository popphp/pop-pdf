<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Annotation;

class AnnotationTest extends \PHPUnit_Framework_TestCase
{

    public function testGetWidthAndHeight()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $this->assertEquals(120, $annot->getWidth());
        $this->assertEquals(20, $annot->getHeight());
    }

    public function testGetXAndYTargets()
    {
        $annot = new Annotation\Link(120, 20, 200, 100);
        $this->assertEquals(200, $annot->getXTarget());
        $this->assertEquals(100, $annot->getYTarget());
    }

    public function testSetHRadius()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setHRadius(10);
        $this->assertEquals(10, $annot->getHRadius());
    }

    public function testSetVRadius()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setVRadius(10);
        $this->assertEquals(10, $annot->getVRadius());
    }

    public function testSetBorderWidth()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setBorderWidth(10);
        $this->assertEquals(10, $annot->getBorderWidth());
    }

    public function testSetDashLength()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setDashLength(10);
        $this->assertEquals(10, $annot->getDashLength());
    }

    public function testSetDashGap()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setDashGap(10);
        $this->assertEquals(10, $annot->getDashGap());
    }

    public function testSetZTarget()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setZTarget(2);
        $this->assertEquals(2, $annot->getZTarget());
    }

    public function testSetPageTarget()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setPageTarget(2);
        $this->assertEquals(2, $annot->getPageTarget());
    }

    public function testGetLinkStream()
    {
        $annot = new Annotation\Link(120, 20, 200, 200);
        $annot->setDashLength(10);
        $annot->setDashGap(5);
        $annot->setZTarget(2);
        $annot->setPageTarget(2);
        $this->assertContains('10 0 obj', $annot->getStream(10, 20, 200, 1, [1, 2]));
        $this->assertContains('/XYZ 200 200 2', $annot->getStream(10, 20, 200, 1, [1, 2]));
    }

    public function testGetUrl()
    {
        $annot = new Annotation\Url(120, 20, 'http://www.google.com');
        $this->assertEquals('http://www.google.com', $annot->getUrl());
    }

    public function testGetUrlStream()
    {
        $annot = new Annotation\Url(120, 20, 'http://www.google.com');
        $annot->setDashLength(10);
        $annot->setDashGap(5);
        $this->assertContains('10 0 obj', $annot->getStream(10, 20, 200, 1, [1, 2]));
        $this->assertContains('<</S /URI /URI (http://www.google.com)', $annot->getStream(10, 20, 200, 1, [1, 2]));
    }

}