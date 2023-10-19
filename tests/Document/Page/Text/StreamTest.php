<?php

namespace Pop\Pdf\Test\Document\Page\Text;

use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{

    public function testConstructor()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Stream', $stream);
        $this->assertEquals(10, $stream->getStartX());
        $this->assertEquals(15, $stream->getStartY());
        $this->assertEquals(20, $stream->getEdgeX());
        $this->assertEquals(25, $stream->getEdgeY());
    }

    public function testSetAndGetCurrent()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);

        $stream->setCurrentX(50);
        $stream->setCurrentY(100);
        $this->assertEquals(50, $stream->getCurrentX());
        $this->assertEquals(100, $stream->getCurrentY());
    }

    public function testSetAndGetStreams()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);
        $stream->addText('Hello World');
        $this->assertTrue($stream->hasTextStreams());
        $this->assertCount(1, $stream->getTextStreams());
    }

}
