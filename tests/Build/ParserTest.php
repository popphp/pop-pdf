<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf\Build\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{

    public function testGetObjectStreamsAndMap()
    {
        $parser = new Parser();
        $parser->parse(__DIR__ . '/../tmp/doc.pdf');
        $this->assertTrue(is_array($parser->getObjectStreams()));
        $this->assertTrue(is_array($parser->getObjectMap()));
    }

    public function testInitFileDoesNotExistException()
    {
        $this->expectException('Pop\Pdf\Build\Exception');
        $parser = new Parser();
        $parser->parse(__DIR__ . '/../tmp/bad.pdf');
    }

    public function testGetFile()
    {
        $parser = new Parser();
        $parser->parse(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals(__DIR__ . '/../tmp/doc.pdf', $parser->getFile());
    }

    public function testGetData()
    {
        $parser = new Parser();
        $parser->parse(__DIR__ . '/../tmp/doc.pdf');
        $this->assertContains('%PDF', $parser->getData());
    }

}