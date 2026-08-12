<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Content\Interpreter;
use Pop\Pdf\Extract\Content\PageWalker;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Font\Resolver;
use PHPUnit\Framework\TestCase;

class ResolverIntegrationTest extends TestCase
{

    protected function decodePage(Document $doc, $page): string
    {
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, $page->content, $page->resources);

        $text = '';
        foreach ($runs as $run) {
            $text .= Resolver::decodeRun($run, $doc);
        }

        return $text;
    }

    public function testDecodesRealTextFromTestExtractFixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/test-extract.pdf');
        $pages = PageWalker::walk($doc);

        $text = $this->decodePage($doc, $pages[0]);

        $this->assertStringContainsString('Hello World!', $text);
        $this->assertStringContainsString('Lorem ipsum dolor', $text);
        $this->assertStringContainsString('Aliquet lectus proin', $text);
        $this->assertStringContainsString('Pharetra convallis posuere', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testDecodesRealTextFromDocFixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/doc.pdf');
        $pages = PageWalker::walk($doc);

        $text = '';
        foreach ($pages as $page) {
            $text .= $this->decodePage($doc, $page);
        }

        $this->assertNotEmpty(trim($text));
    }

    public function testDecodesRealTextFromPdf15Fixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/test-extract-1.5.pdf');
        $pages = PageWalker::walk($doc);

        $text = $this->decodePage($doc, $pages[0]);

        $this->assertNotEmpty(trim($text));
    }

    public function testDecodesRealCjkTextFromType0Fixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/test-extract-cjk.pdf');
        $pages = PageWalker::walk($doc);

        $text = $this->decodePage($doc, $pages[0]);

        $this->assertStringContainsString('你好世界', $text);
        $this->assertStringContainsString('こんにちは世界', $text);
        $this->assertStringContainsString('안녕하세요', $text);
    }

}
