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

    public function testWriteToFile()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);
        Pdf\Pdf::writeToFile($doc, __DIR__ . '/tmp/mytest.pdf');
        $this->assertFileExists(__DIR__ . '/tmp/mytest.pdf');
        unlink(__DIR__ . '/tmp/mytest.pdf');
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputToHttp()
    {
        $pdf = new Pdf\Pdf();
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        Pdf\Pdf::outputToHttp($doc);
        $result = ob_get_clean();

        $this->assertContains('%PDF', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testToString()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        echo $doc;
        $result = ob_get_clean();

        $this->assertContains('%PDF', $result);
    }

}