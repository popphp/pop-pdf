<?php

namespace Pop\Pdf\Test\Build\Html;

use Pop\Pdf\Build\Html\Parser;
use Pop\Pdf\Document;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    public function testConstructor()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $this->assertInstanceOf('Pop\Pdf\Build\Html\Parser', $html);
    }

    public function testParseString()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc);
        $this->assertNotNull($html->getHtml());
    }

    public function testParseFile()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $this->assertNotNull($html->getHtml());
    }

    public function testParseFileException()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/bad.html', $doc);
    }

    public function testCssString()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc);
        $html->parseCss('p {margin: 0; padding: 0;}');
        $this->assertNotNull($html->getCss());
    }

    public function testCssFile()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->parseCssFile(__DIR__ . '/../../tmp/test.css');
        $this->assertNotNull($html->getCss());
    }

    public function testGetDocument()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $this->assertInstanceOf('Pop\Pdf\Document', $html->getDocument());
        $this->assertInstanceOf('Pop\Pdf\Document', $html->document());
    }

    public function testSetAndGetPageSize()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setPageSize('LETTER');
        $this->assertEquals('LETTER', $html->getPageSize());
    }

    public function testSetAndGetPageMargins()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setPageMargins(0, 0, 0, 0);
        $html->setPageTopMargin(10);
        $html->setPageRightMargin(15);
        $html->setPageBottomMargin(20);
        $html->setPageLeftMargin(25);

        $this->assertEquals(10, $html->getPageTopMargin());
        $this->assertEquals(15, $html->getPageRightMargin());
        $this->assertEquals(20, $html->getPageBottomMargin());
        $this->assertEquals(25, $html->getPageLeftMargin());
        $this->assertEquals(4, count($html->getPageMargins()));
    }

    public function testSetAndGetXandY()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setX(50);
        $html->setY(75);

        $this->assertEquals(50, $html->getX());
        $this->assertEquals(75, $html->getY());
    }

}