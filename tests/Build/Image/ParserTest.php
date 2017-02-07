<?php

namespace Pop\Pdf\Test\Build\Image;

use Pop\Pdf\Build\Image\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo.gif', 20, 600);
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
    }

    public function testLoadFromStream()
    {
        $image = Parser::createImageFromStream(file_get_contents(__DIR__ . '/../../tmp/images/logo.gif'), 20, 600);
        $image = Parser::createImageFromStream(file_get_contents(__DIR__ . '/../../tmp/images/logo-rgb.png'), 20, 600);
        $image = Parser::createImageFromStream(file_get_contents(__DIR__ . '/../../tmp/images/logo-rgb.jpg'), 20, 600, ['width' => 120, 'height' => 50], true);
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);

    }
    public function testSetImageWithResize()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg', 20, 600, ['width' => 120, 'height' => 50]);
        $image->setIndex(1);
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageWithResizeAndPreserveResolution()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.jpg', 20, 600, ['width' => 120, 'height' => 50], true);
        $image->setIndex(1);
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImagePng()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageGray()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-gray.jpg', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageCmyk()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-cmyk.jpg', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageIndexPng()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-index.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageIndexTransPng()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-index-trans.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

    public function testSetImageIndexTransGif()
    {
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-trans.gif', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(20, $image->getX());
        $this->assertEquals(600, $image->getY());
        $this->assertEquals(1, $image->getIndex());
    }

}