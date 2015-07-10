<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Image;

class ImageTest extends \PHPUnit_Framework_TestCase
{

    public function testSetImageDoesNotExistException()
    {
        $this->setExpectedException('Pop\Pdf\Document\Page\Exception');
        $image = new Image('bad.jpg');
    }

    public function testSetImageNotAllowedException()
    {
        $this->setExpectedException('Pop\Pdf\Document\Page\Exception');
        $image = new Image(__DIR__ . '/../../tmp/images/bad.tiff');
    }

    public function testResizeToWidth()
    {
        $image = new Image(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resizeToWidth(200);
        $this->assertEquals(200, $image->getResizeDimensions()['width']);
        $this->assertFalse($image->isPreserveResolution());
    }

    public function testResizeToHeight()
    {
        $image = new Image(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resizeToHeight(50);
        $this->assertEquals(50, $image->getResizeDimensions()['height']);
    }

    public function testResize()
    {
        $image = new Image(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resize(50);
        $this->assertEquals(50, $image->getResizeDimensions()['width']);
    }

    public function testScale()
    {
        $image = new Image(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->scale(0.5);
        $this->assertEquals(120, $image->getResizeDimensions()['width']);
    }

    public function testGetImage()
    {
        $image = new Image(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $this->assertEquals(__DIR__ . '/../../tmp/images/logo-rgb.jpg', $image->getImage());
    }

}