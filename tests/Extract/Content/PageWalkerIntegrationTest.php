<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\Interpreter;
use Pop\Pdf\Extract\Content\PageWalker;
use Pop\Pdf\Extract\Document;
use PHPUnit\Framework\TestCase;

class PageWalkerIntegrationTest extends TestCase
{

    public function testWalkAndInterpretTestExtractFixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/test-extract.pdf');
        $pages = PageWalker::walk($doc);

        $this->assertCount(1, $pages);
        $this->assertNotEmpty($pages[0]->content);

        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, $pages[0]->content, $pages[0]->resources);

        $this->assertNotEmpty($runs);

        foreach ($runs as $run) {
            $this->assertNotNull($run->rawBytes);
            $this->assertIsFloat($run->x);
            $this->assertIsFloat($run->y);
        }
    }

    public function testWalkAndInterpretDocFixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/doc.pdf');
        $pages = PageWalker::walk($doc);

        $this->assertEquals(3, count($pages));

        $interpreter  = new Interpreter();
        $totalRunsSum = 0;

        foreach ($pages as $page) {
            $runs          = $interpreter->run($doc, $page->content, $page->resources);
            $totalRunsSum += count($runs);
        }

        $this->assertGreaterThan(0, $totalRunsSum);
    }

    public function testWalkAndInterpretPdf15Fixture()
    {
        $doc   = Document::fromFile(__DIR__ . '/../../tmp/test-extract-1.5.pdf');
        $pages = PageWalker::walk($doc);

        $this->assertEquals(3, count($pages));

        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, $pages[0]->content, $pages[0]->resources);

        $this->assertNotEmpty($runs);
    }

    public function testUnsupportedFilterOnOnePageDoesNotAbortOtherPages()
    {
        $page1Content = 'BT /F1 12 Tf (Page One Text) Tj ET';
        $page2Content = 'BT /F1 12 Tf (Page Two Text) Tj ET';

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length " . strlen($page1Content) . " >>\nstream\n{$page1Content}\nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 6 0 R >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Length " . strlen($page2Content) . " /Filter /JBIG2Decode >>\nstream\n{$page2Content}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6;
        $objs   = [1 => $obj1, 2 => $obj2, 3 => $obj3, 4 => $obj4, 5 => $obj5, 6 => $obj6];
        $offsets = [];
        $cur = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $xrefPos = strlen($header . $body);
        $xref = "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc = new Document($header . $body . $xref);

        $pages = PageWalker::walk($doc);

        $this->assertCount(2, $pages);
        $this->assertStringContainsString('Page One Text', $pages[0]->content);
        $this->assertSame('', $pages[1]->content); // unsupported filter degrades to empty, not a thrown exception
    }

}
