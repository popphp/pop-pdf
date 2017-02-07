<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Image;

class ImageTest extends \PHPUnit_Framework_TestCase
{

    public function testSetImageDoesNotExistException()
    {
        $this->expectException('Pop\Pdf\Document\Page\Exception');
        $image = Image::createImageFromFile('bad.jpg');
    }

    public function testSetImageNotAllowedException()
    {
        $this->expectException('Pop\Pdf\Document\Page\Exception');
        $image = new Image();
        $image->loadImageFromFile(__DIR__ . '/../../tmp/images/bad.tiff');
    }

    public function testSetImageStreamNotAllowedException()
    {
        $this->expectException('Pop\Pdf\Document\Page\Exception');
        $image = new Image();
        $image->loadImageFromStream(file_get_contents(__DIR__ . '/../../tmp/images/bad.tiff'));
    }

    public function testLoadImageFromStream()
    {
        $image = Image::createImageFromStream(file_get_contents(__DIR__ . '/../../tmp/images/logo-rgb.jpg'));
        $image->resizeToWidth(200);
        $this->assertEquals(200, $image->getResizeDimensions()['width']);
        $this->assertNotEmpty($image->getStream());
    }

    public function testResizeToWidth()
    {
        $image = Image::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resizeToWidth(200);
        $this->assertEquals(200, $image->getResizeDimensions()['width']);
        $this->assertTrue($image->isFile());
        $this->assertFalse($image->isPreserveResolution());
    }

    public function testResizeToHeight()
    {
        $image = Image::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resizeToHeight(50);
        $this->assertEquals(50, $image->getResizeDimensions()['height']);
    }

    public function testResize()
    {
        $image = Image::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->resize(50);
        $this->assertEquals(50, $image->getResizeDimensions()['width']);
    }

    public function testScale()
    {
        $image = Image::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image->scale(0.5);
        $this->assertEquals(120, $image->getResizeDimensions()['width']);
    }

    public function testGetImage()
    {
        $image = Image::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $this->assertEquals(__DIR__ . '/../../tmp/images/logo-rgb.jpg', $image->getImage());
    }

}