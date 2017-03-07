<?php

namespace Pop\Pdf\Test;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Form;

class DocumentTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $doc = new Document(new Page(Page::LETTER));
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
    }

    public function testSetOrigin()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_TOP_RIGHT);
        $this->assertEquals(Document::ORIGIN_TOP_RIGHT, $doc->getOrigin());
    }

    public function testSetCompression()
    {
        $doc = new Document();
        $doc->setCompression(true);
        $this->assertTrue($doc->isCompressed());
    }

    public function testAddPages()
    {
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $this->assertEquals(4, $doc->getNumberOfPages());
        $this->assertTrue($doc->hasPages());
        $this->assertInstanceOf('Pop\Pdf\Document\Page', $doc->getPage(1));
        $this->assertEquals(1008, $doc->getPage(3)->getHeight());
    }

    public function testGetPageException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc  = new Document();
        $page = $doc->getPage(1);
    }

    public function testCopyPageException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(3);
    }

    public function testOrderPages()
    {
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $doc->orderPages([4, 3, 2, 1]);
        $this->assertEquals(4, $doc->getNumberOfPages());
    }

    public function testOrderPagesBadCountException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $doc->orderPages([4, 2, 1]);
    }

    public function testOrderPagesPageNotExistException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $doc->orderPages([4, 8, 3, 1]);
    }

    public function testDeletePage()
    {
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $this->assertEquals(3, $doc->getNumberOfPages());
        $doc->deletePage(3);
    }

    public function testDeletePageException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $this->assertEquals(3, $doc->getNumberOfPages());
        $doc->deletePage(8);
    }

    public function testAddFont()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $this->assertEquals(1, $doc->getNumberOfFonts());
    }

    public function testAddFontException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));
    }

    public function testEmbedFont()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));
        $this->assertInstanceOf('Pop\Pdf\Build\Font\AbstractFont', $doc->getFont('Times-Bold')->getParsedFont());
        $this->assertEquals(2, count($doc->getFont('Times-Bold')->getParsedFont()->getWidthsForGlyphs([0, 1])));
        $this->assertEquals(67, ceil($doc->getFont('Times-Bold')->getParsedFont()->getStringWidth('Hello World', 12)));
        $this->assertEquals(1, $doc->getNumberOfFonts());
        $this->assertFalse($doc->hasImportedFonts());
        $this->assertEquals(0, count($doc->getImportedFonts()));
    }

    public function testEmbedFontException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->embedFont(new Font('Arial'));
    }

    public function testGetFont()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $this->assertTrue($doc->hasFonts());
        $this->assertInstanceOf('Pop\Pdf\Document\Font', $doc->getFont('Arial'));
        $this->assertContains('Arial', $doc->getAvailableFonts());
        $this->assertTrue($doc->isFontAvailable('Arial'));
    }

    public function testGetFontException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $this->assertInstanceOf('Pop\Pdf\Document\Font', $doc->getFont('Arial'));
    }

    public function testSetCurrentPage()
    {
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->setCurrentPage(1);
        $this->assertEquals(2, $doc->getNumberOfPages());
        $this->assertEquals(1, $doc->getCurrentPage());
    }

    public function testSetCurrentPageException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->setCurrentPage(8);
    }

    public function testSetCurrentFont()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->setCurrentFont('Arial');
        $this->assertEquals('Arial', $doc->getCurrentFont());
    }

    public function testSetCurrentFontException()
    {
        $this->expectException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->setCurrentFont('Arial');
    }

    public function testAddForm()
    {
        $doc = new Document();
        $doc->addForm(new Form('contact'));
        $this->assertTrue($doc->hasForms());
        $this->assertEquals(1, count($doc->getForms()));
        $this->assertInstanceOf('Pop\Pdf\Document\Form', $doc->getForm('contact'));
    }

}