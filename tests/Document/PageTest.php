<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Page;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{

    public function testConstructor()
    {
        $page = new Page(Page::LETTER);
        $this->assertInstanceOf('Pop\Pdf\Document\Page', $page);
    }

    public function testConstructorBadSize()
    {
        $this->expectException('Pop\Pdf\Document\Exception');
        $page = new Page('BAD_SIZE');
    }

    public function testGetWidth()
    {
        $page = new Page(Page::LETTER);
        $this->assertEquals(612, $page->getWidth());
    }

    public function testGetIndex()
    {
        $page = new Page(Page::LETTER);
        $this->assertNull($page->getIndex());
    }

    public function testAddImage()
    {
        $page = new Page(Page::LETTER);
        $page->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'));
        $this->assertEquals(1, count($page->getImages()));
    }

    public function testAddText()
    {
        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial');
        $this->assertEquals(1, count($page->getText()));
    }

    public function testAddMultiText()
    {
        $text1 = new Page\Text('Hello World', 12, 'Arial');
        $text2 = new Page\Text('Foo Bar', 12, 'Arial');
        $page = new Page(Page::LETTER);
        $page->addMultiText([$text1, $text2], 50, 50);
        $this->assertEquals(3, count($page->getMultiText()));
    }

    public function testAddAnnotation()
    {
        $page = new Page(Page::LETTER);
        $page->addAnnotation(new Page\Annotation\Url(120, 20, 'http://www.google.com'));
        $this->assertEquals(1, count($page->getAnnotations()));
    }

    public function testAddUrl()
    {
        $page = new Page(Page::LETTER);
        $page->addUrl(new Page\Annotation\Url(120, 20, 'http://www.google.com'));
        $this->assertEquals(1, count($page->getAnnotations()));
    }

    public function testAddLink()
    {
        $page = new Page(Page::LETTER);
        $page->addLink(new Page\Annotation\Link(120, 20, 400, 300));
        $this->assertEquals(1, count($page->getAnnotations()));
    }

    public function testAddPath()
    {
        $page = new Page(Page::LETTER);
        $page->addPath(new Page\Path());
        $this->assertEquals(1, count($page->getPaths()));
    }

    public function testAddField()
    {
        $page = new Page(Page::LETTER);
        $page->addField(new Page\Field\Text('name'), 'contact');
        $this->assertEquals(1, count($page->getFields()));
    }

}