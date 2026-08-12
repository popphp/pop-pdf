<?php

namespace Pop\Pdf\Test\Build\Import;

use Pop\Pdf\Build\Import\ObjectGraphReader;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Build\PdfObject\PageObject;
use PHPUnit\Framework\TestCase;

class ObjectGraphReaderTest extends TestCase
{

    protected function buildPdf(string $extraPageDictEntries = '', string $extraResourceEntries = ''): string
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] {$extraPageDictEntries}" .
            "/Resources << /Font << /F1 5 0 R >> {$extraResourceEntries} >> /Contents 4 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length 6 >>\nstream\nBT ET \nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Info /Title (Test Doc) >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6;

        $offsets = [];
        $cur     = $header;
        foreach ([1 => $obj1, 2 => $obj2, 3 => $obj3, 4 => $obj4, 5 => $obj5, 6 => $obj6] as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }

        $xrefPos = strlen($header . $body);
        $xref    = "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 7 /Root 1 0 R /Info 6 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $header . $body . $xref;
    }

    public function testReadAtZeroOffsetRenumbersDensely()
    {
        $doc   = new Document($this->buildPdf());
        $graph = ObjectGraphReader::read($doc, 0);

        // Object 1 (Root/Catalog), 2 (top Pages, exposed separately via
        // topPagesObjNum), 3 (the Page itself, goes into 'pageObjects' not
        // 'objects'), and 6 (Info) are all excluded from 'objects'.
        // Remaining: 4 (content stream), 5 (font) = 2 entries.
        // 6 total objects renumbered densely from offset 0 -> nextOffset = 6.
        $this->assertCount(1, $graph['pageObjects']);
        $this->assertCount(2, $graph['objects']);
        $this->assertEquals(2, $graph['topPagesObjNum']);
        $this->assertEquals(6, $graph['nextOffset']);
    }

    public function testReadAtNonZeroOffsetShiftsEveryNumber()
    {
        $doc   = new Document($this->buildPdf());
        $graph = ObjectGraphReader::read($doc, 100);

        $this->assertEquals(102, $graph['topPagesObjNum']);
        $this->assertEquals(106, $graph['nextOffset']);

        $page = $graph['pageObjects'][0];
        $this->assertGreaterThan(100, $page->getIndex());
        $this->assertEquals(102, $page->getParentIndex());
    }

    public function testPageObjectHasCorrectDimensionsAndContent()
    {
        $doc   = new Document($this->buildPdf());
        $graph = ObjectGraphReader::read($doc, 0);
        $page  = $graph['pageObjects'][0];

        $this->assertInstanceOf(PageObject::class, $page);
        $this->assertEquals(612, $page->getWidth());
        $this->assertEquals(792, $page->getHeight());
        $this->assertCount(1, $page->getContent());
    }

    public function testFontReferenceIsRenumbered()
    {
        $doc   = new Document($this->buildPdf());
        $graph = ObjectGraphReader::read($doc, 0);
        $page  = $graph['pageObjects'][0];

        $fonts = $page->getFonts();
        $this->assertCount(1, $fonts);
        $this->assertStringContainsString('/F1', reset($fonts));
        // The font object (source #5) is not Root/Info/topPages, so it's a
        // plain +1-per-object renumbering after the Pages node (#2 -> new).
        $this->assertMatchesRegularExpression('/\/F1 \d+ 0 R/', reset($fonts));
    }

    public function testRotateAndExtraResourcesArePreservedOnThePage()
    {
        $doc   = new Document($this->buildPdf('/Rotate 90 ', '/ColorSpace << /CS0 5 0 R >>'));
        $graph = ObjectGraphReader::read($doc, 0);
        $page  = $graph['pageObjects'][0];

        $rendered = (string) $page;
        $this->assertStringContainsString('/Rotate 90', $rendered);
        $this->assertStringContainsString('/ColorSpace', $rendered);
    }

    public function testWidgetAnnotationIsDroppedButOtherAnnotationsKept()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] " .
            "/Annots [5 0 R 6 0 R] >>\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Annot /Subtype /Widget >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Annot /Subtype /Link >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $objects = [1 => $obj1, 2 => $obj2, 3 => $obj3, 5 => $obj5, 6 => $obj6];
        $offsets = [];
        $cur     = $header;
        foreach ($objects as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objects);
        $xrefPos = strlen($header . $body);
        $xref    = "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);
        $page  = $graph['pageObjects'][0];

        $this->assertCount(1, $page->getAnnots());
    }

    public function testGenericStreamObjectRendersWellFormedPdfSyntax()
    {
        $doc   = new Document($this->buildPdf());
        $graph = ObjectGraphReader::read($doc, 0);

        $rendered = (string) $graph['objects'][4];

        $this->assertStringContainsString('/Length 7', $rendered);
        $this->assertStringNotContainsString('>><<', $rendered);
        $this->assertMatchesRegularExpression('/stream\r?\nBT ET \r?\nendstream/', $rendered);
    }

    public function testRootPagesPointingDirectlyAtAPageThrowsCleanException()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Page /Parent 1 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2;
        $offsets = [1 => strlen($header), 2 => strlen($header . $obj1)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 3\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $this->expectException(\Pop\Pdf\Build\Exception::class);

        $doc = new Document($header . $body . $xref);
        ObjectGraphReader::read($doc, 0);
    }

    public function testFontResourceResolvesThroughAnIndirectFontDict()
    {
        // /Resources itself is indirect (object 4), and /Font *within* that
        // resolved Resources dict is ALSO independently indirect (object 5)
        // - a distinct, valid, real-world PDF structure (producers commonly
        // share one Font dict object across many pages' Resources), seen in
        // tests/tmp/test-extract.pdf. Both levels of indirection must be
        // resolved for the font to actually reach the translated page.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources 4 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /ProcSet [/PDF /Text] /Font 5 0 R >>\nendobj\n";
        $obj5 = "5 0 obj\n<< /F1 6 0 R >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $objs   = [1 => $obj1, 2 => $obj2, 3 => $obj3, 4 => $obj4, 5 => $obj5, 6 => $obj6];

        $offsets = [];
        $cur     = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $xref    = "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);
        $page  = $graph['pageObjects'][0];

        $fonts = $page->getFonts();
        $this->assertCount(1, $fonts);
        $this->assertMatchesRegularExpression('/\/F1 \d+ 0 R/', reset($fonts));
    }

    public function testTrailerRootThatIsNotAReferenceThrowsCleanException()
    {
        $data = "%PDF-1.4\ntrailer\n<< /Root << /Type /Catalog >> >>\n%%EOF";

        $this->expectException(\Pop\Pdf\Build\Exception::class);
        $this->expectExceptionMessage('Error: Could not resolve the source PDF document catalog (Root).');

        $doc = new Document($data);
        ObjectGraphReader::read($doc, 0);
    }

    public function testCatalogPagesThatIsNotAReferenceThrowsCleanException()
    {
        $data = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages << /Type /Pages /Kids [] /Count 0 >> >>\nendobj\n" .
            "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $this->expectException(\Pop\Pdf\Build\Exception::class);
        $this->expectExceptionMessage('Error: Could not resolve the source PDF page tree (Pages).');

        $doc = new Document($data);
        ObjectGraphReader::read($doc, 0);
    }

    public function testTopPagesNodeWithoutKidsProducesNoPagesInsteadOfError()
    {
        // walkPagesTree()'s "Kids isn't an array" guard - a /Type /Pages
        // node with no /Kids at all must degrade to zero leaf pages rather
        // than fatal.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2;
        $offsets = [1 => strlen($header), 2 => strlen($header . $obj1)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 3\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);

        $this->assertCount(0, $graph['pageObjects']);
    }

    public function testKidReferencingAMissingObjectIsSkippedNotFatal()
    {
        // walkPagesTree()'s "node isn't an array" guard - a /Kids entry
        // pointing at an object number absent from the xref must be
        // skipped, leaving the other, valid, kid still processed.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $objects = [1 => $obj1, 2 => $obj2, 4 => $obj4];
        $offsets = [];
        $cur     = $header;
        foreach ($objects as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objects);
        $xrefPos = strlen($header . $body);
        $xref    = "xref\n0 5\n0000000000 65535 f \n";
        for ($i = 1; $i <= 4; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);

        $this->assertCount(1, $graph['pageObjects']);
    }

    public function testSelfReferencingPagesNodeDoesNotInfiniteLoop()
    {
        // walkPagesTree()'s depth/visited guard - a /Pages node whose own
        // /Kids points back at itself must terminate via the visited-set
        // check rather than recursing forever.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [2 0 R] /Count 1 >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2;
        $offsets = [1 => strlen($header), 2 => strlen($header . $obj1)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 3\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);

        $this->assertCount(0, $graph['pageObjects']);
    }

    public function testTopLevelArrayObjectIsSerializedAsAnArrayNotADict()
    {
        // A standalone indirect object whose value is a PDF *array* (not a
        // dict) is a common, real structure in scanned/image-heavy PDFs -
        // most notably a colorspace array like [/Separation /Black
        // /DeviceCMYK 7 0 R] referenced directly from an image XObject's
        // own /ColorSpace entry (confirmed present in real-world scanned
        // documents encountered during development). Both PDF arrays and
        // dicts are plain PHP arrays in this codebase's model -
        // translateGeneric() must not assume every array is a dict.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $obj4 = "4 0 obj\n[/Separation /Black /DeviceCMYK 5 0 R]\nendobj\n";
        $obj5 = "5 0 obj\n<< /FunctionType 2 /Domain [0 1] /C0 [0 0 0 0] /C1 [0 0 0 1] /N 1 >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $objs   = [1 => $obj1, 2 => $obj2, 3 => $obj3, 4 => $obj4, 5 => $obj5];

        $offsets = [];
        $cur     = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $xref    = "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc   = new Document($header . $body . $xref);
        $graph = ObjectGraphReader::read($doc, 0);

        // Object 4 (the colorspace array) is at new number 4 - offset 0,
        // objects 1..5 renumber densely/identically since already dense.
        $rendered = (string) $graph['objects'][4];

        $this->assertStringContainsString('[ /Separation /Black /DeviceCMYK 5 0 R ]', $rendered);
        $this->assertStringNotContainsString('/0 /Separation', $rendered);
        $this->assertStringNotContainsString('<<', $rendered);
    }

}
