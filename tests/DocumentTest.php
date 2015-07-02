<?php

namespace Pop\Pdf\Test;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;

class DocumentTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $doc = new Document(new Page(Page::LETTER));
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
    }

    public function testAddPages()
    {
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $this->assertEquals(4, $doc->getNumberOfPages());
    }

    public function testCopyPageException()
    {
        $this->setExpectedException('Pop\Pdf\Exception');
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
        $this->setExpectedException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addPages([new Page(Page::LETTER), new Page(Page::LETTER)]);
        $doc->createPage(Page::LEGAL);
        $doc->copyPage(1);
        $doc->orderPages([4, 2, 1]);
    }

    public function testOrderPagesPageNotExistException()
    {
        $this->setExpectedException('Pop\Pdf\Exception');
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
        $this->setExpectedException('Pop\Pdf\Exception');
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
        $this->setExpectedException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->addFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));
    }

    public function testEmbedFont()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));
        $this->assertEquals(1, $doc->getNumberOfFonts());
        $this->assertFalse($doc->hasImportedFonts());
        $this->assertEquals(0, count($doc->getImportedFonts()));
    }

    public function testEmbedFontException()
    {
        $this->setExpectedException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->embedFont(new Font('Arial'));
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
        $this->setExpectedException('Pop\Pdf\Exception');
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
        $this->setExpectedException('Pop\Pdf\Exception');
        $doc = new Document();
        $doc->setCurrentFont('Arial');
    }

}