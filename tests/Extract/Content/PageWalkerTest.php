<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\PageWalker;
use Pop\Pdf\Extract\Document;
use PHPUnit\Framework\TestCase;

class PageWalkerTest extends TestCase
{

    protected function buildPdf(): string
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 5 0 R " .
            "/MediaBox [0 0 612 792] /Rotate 0 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [7 0 R 8 0 R] /Rotate 90 >>\nendobj\n";
        $obj5 = "5 0 obj\n<< /Font << /F1 9 0 R >> >>\nendobj\n";

        $stream6 = 'BT /F1 12 Tf (Hello) Tj ET';
        $obj6    = "6 0 obj\n<< /Length " . strlen($stream6) . " >>\nstream\n{$stream6}\nendstream\nendobj\n";

        $stream7 = 'BT (Part1) Tj ET';
        $obj7    = "7 0 obj\n<< /Length " . strlen($stream7) . " >>\nstream\n{$stream7}\nendstream\nendobj\n";

        $stream8 = 'BT (Part2) Tj ET';
        $obj8    = "8 0 obj\n<< /Length " . strlen($stream8) . " >>\nstream\n{$stream8}\nendstream\nendobj\n";

        $obj9 = "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $objs   = [$obj1, $obj2, $obj3, $obj4, $obj5, $obj6, $obj7, $obj8, $obj9];

        $offsets = [];
        $cursor  = strlen($header);
        foreach ($objs as $i => $obj) {
            $offsets[$i + 1] = $cursor;
            $cursor         += strlen($obj);
        }

        $body    = $header . implode('', $objs);
        $xrefPos = strlen($body);

        $xref = "xref\n0 10\n0000000000 65535 f \n";
        for ($i = 1; $i <= 9; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $body . $xref;
    }

    public function testWalkResolvesTwoPagesWithInheritance()
    {
        $doc   = new Document($this->buildPdf());
        $pages = PageWalker::walk($doc);

        $this->assertCount(2, $pages);

        $this->assertArrayHasKey('F1', $pages[0]->resources['Font']);
        $this->assertEquals([0, 0, 612, 792], $pages[0]->mediaBox);
        $this->assertEquals(0, $pages[0]->rotate);
        $this->assertEquals('BT /F1 12 Tf (Hello) Tj ET', $pages[0]->content);

        $this->assertEquals([0, 0, 612, 792], $pages[1]->mediaBox);
        $this->assertEquals(90, $pages[1]->rotate);
        $this->assertEquals("BT (Part1) Tj ET\nBT (Part2) Tj ET", $pages[1]->content);
    }

    public function testWalkTerminatesOnCyclicKidsReference()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        // Pages node whose Kids includes a reference back to itself - cyclic.
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 2 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);
        $offset3 = strlen($header . $obj1 . $obj2);
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            sprintf("%010d 00000 n \n", $offset3) .
            "trailer\n<< /Size 4 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $pages = PageWalker::walk($doc);

        $this->assertCount(1, $pages);
        $this->assertEquals([0, 0, 612, 792], $pages[0]->mediaBox);
    }

    public function testWalkDoesNotExhaustMemoryOnDeepAcyclicChain()
    {
        // A 200-deep chain of nested Pages nodes (well past MAX_TREE_DEPTH)
        // must be bounded, not walked to completion or exhausted for memory.
        $objs   = [];
        $header = "%PDF-1.4\n";
        $depth  = 200;

        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        for ($i = 0; $i < $depth; $i++) {
            $selfNum  = 2 + $i;
            $childNum = 3 + $i;
            $objs[$selfNum] = "{$selfNum} 0 obj\n<< /Type /Pages /Kids [{$childNum} 0 R] /Count 1 >>\nendobj\n";
        }
        $leafNum = 2 + $depth;
        $objs[$leafNum] = "{$leafNum} 0 obj\n<< /Type /Page /MediaBox [0 0 612 792] >>\nendobj\n";

        $body    = $header;
        $offsets = [];
        foreach ($objs as $num => $obj) {
            $offsets[$num] = strlen($body);
            $body         .= $obj;
        }

        $xrefPos = strlen($body);
        $maxNum  = $leafNum;
        $xref    = "xref\n0 " . ($maxNum + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxNum; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $xref .= "trailer\n<< /Size " . ($maxNum + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($body . $xref);
        $pages = PageWalker::walk($doc);

        // Past MAX_TREE_DEPTH (64), the walk bails out silently rather than
        // reaching the leaf - this proves the cap is actually enforced.
        $this->assertEquals([], $pages);
    }

    public function testWalkThrowsWhenPageTreeRootUnresolvable()
    {
        // Catalog has no /Pages entry at all, so the root page tree can't
        // be resolved to an array.
        $obj1 = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1;
        $offset1 = strlen($header);
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 2\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            "trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc = new Document($header . $body . $xref);

        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        PageWalker::walk($doc);
    }

    public function testWalkReturnsEmptyWhenPagesNodeHasNoResolvableKids()
    {
        // The Pages node resolves fine, but has no /Kids at all - walkNode()
        // must return without appending anything rather than crashing.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2;
        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 3\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $pages = PageWalker::walk($doc);

        $this->assertEquals([], $pages);
    }

}
