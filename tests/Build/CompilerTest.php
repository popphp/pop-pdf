<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Form;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Build\Compiler;

class CompilerTest extends \PHPUnit_Framework_TestCase
{

    public function testSetDocument()
    {
        $doc = new Document();
        $compiler = new Compiler();
        $this->assertEquals(0, $compiler->lastIndex());
        $compiler->setDocument($doc);
        $this->assertInstanceOf('Pop\Pdf\Document', $compiler->getDocument());
        $this->assertInstanceOf('Pop\Pdf\Build\Object\RootObject', $compiler->getRoot());
        $this->assertInstanceOf('Pop\Pdf\Build\Object\ParentObject', $compiler->getParent());
        $this->assertInstanceOf('Pop\Pdf\Build\Object\InfoObject', $compiler->getInfo());
    }

    public function testFinalize()
    {
        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $path = new Page\Path();
        $path->setFillColor(new Page\Color\Rgb(255, 0, 0));
        $path->drawRectangle(320, 320, 300, 150);

        $page2 = new Page(Page::LETTER);
        $page2->addPath($path);

        $page2->addUrl(new Page\Annotation\Url(150, 20, 'http://www.google.com/'));
        $page2->addLink(new Page\Annotation\Link(150, 20, 300, 300));

        $page2->addField(new Page\Field\Text('name', 'Arial', 10), 'contact_form', 50, 200);
        $page2->addField(new Page\Field\Text('email', 'Arial', 10), 'contact_form', 50, 175);

        $page3 = new Page(Page::LETTER);
        $page3->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);
        $page3->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page3->addPath($path);

        $doc->addPages([$page1, $page2, $page3]);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertContains('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginTopLeft()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_TOP_LEFT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertContains('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginTopRight()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_TOP_RIGHT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertContains('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginBottomRight()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_BOTTOM_RIGHT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertContains('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginCenter()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_CENTER);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(new Page\Image(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertContains('%PDF', $compiler->getOutput());
    }

}