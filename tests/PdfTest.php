<?php

namespace Pop\Pdf\Test;

use Pop\Pdf;
use PHPUnit\Framework\TestCase;

class PdfTest extends TestCase
{

    public function testConstructor()
    {
        $pdf = new Pdf\Pdf();
        $this->assertInstanceOf('Pop\Pdf\Pdf', $pdf);

    }

    public function testImportFromFile()
    {
        $pdf = new Pdf\Pdf();
        $this->assertInstanceOf('Pop\Pdf\Document', $pdf->importFromFile(__DIR__ . '/tmp/doc.pdf', 1));
    }

    public function testWriteToFile()
    {
        $pdf = new Pdf\Pdf();
        $doc = $pdf->importFromFile(__DIR__ . '/tmp/doc.pdf', 1);
        $pdf->writeToFile($doc, __DIR__ . '/tmp/mytest.pdf');
        $this->assertFileExists(__DIR__ . '/tmp/mytest.pdf');
        unlink(__DIR__ . '/tmp/mytest.pdf');
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputToHttp()
    {
        $_SERVER['SERVER_PORT'] = 443;

        $pdf = new Pdf\Pdf();
        $doc = $pdf->importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        $pdf->outputToHttp($doc);
        $result = ob_get_clean();

        $this->assertContains('%PDF', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testToString()
    {
        $pdf = new Pdf\Pdf();
        $doc = $pdf->importFromFile(__DIR__ . '/tmp/doc.pdf', 1);

        ob_start();
        echo $doc;
        $result = ob_get_clean();

        $this->assertContains('%PDF', $result);
    }

}