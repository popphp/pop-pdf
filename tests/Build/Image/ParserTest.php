<?php

namespace Pop\Pdf\Test\Build\Image;

use Pop\Pdf\Build\Image\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
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

    public function testParseThrowsWhenIndexNotSet()
    {
        $this->expectException('Pop\Pdf\Build\Image\Exception');
        $this->expectExceptionMessage('Error: The image index has not been set.');

        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb.png', 20, 600);
        $image->parse();
    }

    public function testSetImageTrueGrayscalePng()
    {
        // colorType 0 (true grayscale, no palette) - a distinct PNG shape
        // from the indexed-grayscale/RGB fixtures above; exercises the
        // 'Gray' branch of both parseImageData()'s color-type switch and
        // parsePng()'s colorspace selection.
        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-gray.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $objects = $image->getObjects();
        $this->assertStringContainsString('/DeviceGray', (string) $objects[1]);
    }

    public function testSetImageGrayscaleAlphaPngThrows()
    {
        // colorType 4 (grayscale + alpha channel, 8-bit) - parseImageData()
        // must still classify it (channels=1, alpha=true) before parsePng()
        // rejects it as an unsupported true-alpha PNG.
        $this->expectException('Pop\Pdf\Build\Image\Exception');
        $this->expectExceptionMessage(
            'Error: PNG alpha channels are not supported. Only 8-bit transparent PNG images are supported.'
        );

        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-gray-alpha.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
    }

    public function testSetImageRgbAlphaPngThrows()
    {
        // colorType 6 (RGB + alpha channel, 8-bit) - same true-alpha
        // rejection as the grayscale+alpha case, but through the RGB branch
        // of parseImageData()'s color-type switch.
        $this->expectException('Pop\Pdf\Build\Image\Exception');
        $this->expectExceptionMessage(
            'Error: PNG alpha channels are not supported. Only 8-bit transparent PNG images are supported.'
        );

        $image = Parser::createImageFromFile(__DIR__ . '/../../tmp/images/logo-rgb-alpha.png', 20, 600);
        $image->setIndex(1);
        $image->parse();
    }

    public function testLoadFromStreamWithResizeWithoutPreservingResolution()
    {
        // loadImageFromStream()'s resize branch (resize dimensions given,
        // preserveResolution left false) - routes into resizeImage()'s
        // stream-based branch (imagecreatefromstring()/getimagesizefromstring())
        // rather than the file-path branch already covered elsewhere.
        $stream = file_get_contents(__DIR__ . '/../../tmp/images/logo-rgb.jpg');
        $image  = Parser::createImageFromStream($stream, 20, 600, ['width' => 60, 'height' => 30]);
        $image->setIndex(1);

        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $this->assertEquals(60, $image->getWidth());
        $this->assertEquals(30, $image->getHeight());
        $image->parse();
        $this->assertNotEmpty($image->getObjects());
    }

    public function testLoadFromFileResizesAPngWithoutPreservingResolution()
    {
        // loadImageFromFile()'s resize branch on a PNG source (mime ==
        // 'image/png') - resizeImage() must save the resized output via
        // imagepng(), not imagejpeg() (the branch every other resize test
        // hits, since they all use JPEG fixtures).
        $image = Parser::createImageFromFile(
            __DIR__ . '/../../tmp/images/logo-rgb.png', 20, 600, ['width' => 60, 'height' => 30]
        );
        $image->setIndex(1);

        $this->assertInstanceOf('Pop\Pdf\Build\Image\Parser', $image);
        $image->parse();
        $this->assertNotEmpty($image->getObjects());
    }

}