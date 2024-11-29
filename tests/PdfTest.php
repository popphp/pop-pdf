<?php

namespace Pop\Pdf\Test;

use Pop\Pdf;
use PHPUnit\Framework\TestCase;

class PdfTest extends TestCase
{

    public function testImportFromFile()
    {
        $this->assertInstanceOf('Pop\Pdf\Document', Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1));
    }

    public function testImportFromData()
    {
        $this->assertInstanceOf('Pop\Pdf\Document', Pdf\Pdf::importRawData(file_get_contents(__DIR__ . '/tmp/doc.pdf'), 1));
    }

    public function testImportFromImages()
    {
        $this->assertInstanceOf('Pop\Pdf\Document', Pdf\Pdf::importFromImages(__DIR__ . '/tmp/images/logo-rgb.jpg'));
    }

    public function testImportFromImagesException()
    {
        $this->expectException('Pop\Pdf\Document\Exception');
        $doc = Pdf\Pdf::importFromImages(__DIR__ . '/tmp/images/logo-BAD.jpg');
    }

    public function testExtractTextFromFile1()
    {
        $text = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/test-extract.pdf');
        $this->assertStringContainsString('Hello World!', $text);
        $this->assertStringContainsString('Lorem ipsum dolor', $text);
        $this->assertStringContainsString('Aliquet lectus proin', $text);
        $this->assertStringContainsString('Pharetra convallis posuere', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testExtractTextFromFile2()
    {
        $text = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/test-extract.pdf', 1);
        $this->assertStringContainsString('Hello World!', $text);
        $this->assertStringContainsString('Lorem ipsum dolor', $text);
        $this->assertStringContainsString('Aliquet lectus proin', $text);
        $this->assertStringContainsString('Pharetra convallis posuere', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testExtractTextFromData1()
    {
        $text = Pdf\Pdf::extractTextFromData(file_get_contents(__DIR__ . '/tmp/test-extract.pdf'));
        $this->assertStringContainsString('Hello World!', $text);
        $this->assertStringContainsString('Lorem ipsum dolor', $text);
        $this->assertStringContainsString('Aliquet lectus proin', $text);
        $this->assertStringContainsString('Pharetra convallis posuere', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testExtractTextFromData2()
    {
        $text = Pdf\Pdf::extractTextFromData(file_get_contents(__DIR__ . '/tmp/test-extract.pdf'), 1);
        $this->assertStringContainsString('Hello World!', $text);
        $this->assertStringContainsString('Lorem ipsum dolor', $text);
        $this->assertStringContainsString('Aliquet lectus proin', $text);
        $this->assertStringContainsString('Pharetra convallis posuere', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testWriteToFile()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);
        Pdf\Pdf::writeToFile($doc, __DIR__ . '/tmp/mytest.pdf');
        $this->assertFileExists(__DIR__ . '/tmp/mytest.pdf');
        unlink(__DIR__ . '/tmp/mytest.pdf');
    }

    #[runInSeparateProcess]
    public function testOutputToHttp()
    {
        $pdf = new Pdf\Pdf();
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        Pdf\Pdf::outputToHttp($doc);
        $result = ob_get_clean();

        $this->assertStringContainsString('%PDF', $result);
    }

    #[runInSeparateProcess]
    public function testToString()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        echo $doc;
        $result = ob_get_clean();

        $this->assertStringContainsString('%PDF', $result);
    }

}
