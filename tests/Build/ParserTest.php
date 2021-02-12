<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf\Build\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    public function testGetObjectStreamsAndMap()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertTrue(is_array($parser->getObjectStreams()));
        $this->assertTrue(is_array($parser->getObjectMap()));
    }

    public function testInitFileDoesNotExistException()
    {
        $this->expectException('Pop\Pdf\Build\Exception');
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/bad.pdf');
    }

    public function testGetFile()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals(__DIR__ . '/../tmp/doc.pdf', $parser->getFile());
    }

    public function testGetData()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertStringContainsString('%PDF', $parser->getData());
    }

    public function testGetObjectStreamsAndMapFromData()
    {
        $parser = new Parser();
        $parser->parseData(file_get_contents(__DIR__ . '/../tmp/doc.pdf'));
        $this->assertTrue(is_array($parser->getObjectStreams()));
        $this->assertTrue(is_array($parser->getObjectMap()));
    }


    public function testGetDataFromData()
    {
        $parser = new Parser();
        $parser->parseData(file_get_contents(__DIR__ . '/../tmp/doc.pdf'));
        $this->assertStringContainsString('%PDF', $parser->getData());
    }

}