<?php

namespace Pop\Pdf\Test;

use Pop\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Security;
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

    public function testImportFromHtml()
    {
        $doc = Pdf\Pdf::importFromHtml('<h1>Hello World</h1><p>How are you?</p>');
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testImportFromHtmlProducesWritablePdf()
    {
        $doc     = Pdf\Pdf::importFromHtml('<h1>Hello World</h1><p>How are you?</p>');
        $tmpFile = tempnam(sys_get_temp_dir(), 'import_html_test_') . '.pdf';

        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString('Hello World', $text);
        $this->assertStringContainsString('How are you?', $text);
    }

    public function testImportFromHtmlFile()
    {
        $doc = Pdf\Pdf::importFromHtmlFile(__DIR__ . '/tmp/test.html');
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testImportFromHtmlFileException()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');
        $doc = Pdf\Pdf::importFromHtmlFile(__DIR__ . '/tmp/bad.html');
    }

    public function testImportFromHtmlUri()
    {
        // file_get_contents() doesn't distinguish a URI scheme from a plain
        // local path, so a local file path exercises importFromHtmlUri()
        // without any real network I/O.
        $doc = Pdf\Pdf::importFromHtmlUri(__DIR__ . '/tmp/test.html');
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testImportFromHtmlDefaultsToLetterPageSize()
    {
        $doc = Pdf\Pdf::importFromHtml('<h1>Hello World</h1>');
        $this->assertEquals(612, $doc->getPage(1)->getWidth());
        $this->assertEquals(792, $doc->getPage(1)->getHeight());
    }

    public function testImportFromHtmlAcceptsPageSize()
    {
        $doc = Pdf\Pdf::importFromHtml('<h1>Hello World</h1>', new Document(), 'A4');
        $this->assertEquals(595, $doc->getPage(1)->getWidth());
        $this->assertEquals(842, $doc->getPage(1)->getHeight());
    }

    public function testImportFromHtmlFileAcceptsPageSize()
    {
        $doc = Pdf\Pdf::importFromHtmlFile(__DIR__ . '/tmp/test.html', new Document(), 'A4');
        $this->assertEquals(595, $doc->getPage(1)->getWidth());
        $this->assertEquals(842, $doc->getPage(1)->getHeight());
    }

    public function testImportFromHtmlUriAcceptsPageSize()
    {
        $doc = Pdf\Pdf::importFromHtmlUri(__DIR__ . '/tmp/test.html', new Document(), 'A4');
        $this->assertEquals(595, $doc->getPage(1)->getWidth());
        $this->assertEquals(842, $doc->getPage(1)->getHeight());
    }

    public function testImportFromHtmlAcceptsPageSizeAsWidthAndHeightArray()
    {
        $doc = Pdf\Pdf::importFromHtml('<h1>Hello World</h1>', new Document(), [400, 600]);
        $this->assertEquals(400, $doc->getPage(1)->getWidth());
        $this->assertEquals(600, $doc->getPage(1)->getHeight());
    }

    public function testImportFromHtmlUsesProvidedDocument()
    {
        $doc = new Document();
        $doc->addFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));

        $result = Pdf\Pdf::importFromHtml('<h1>Hello World</h1>', $doc);

        // Passing a Document mutates and returns that same instance, rather
        // than the parser silently building an unrelated one internally -
        // and the pre-registered custom font survives alongside whatever
        // default standard fonts the parser adds on top of it.
        $this->assertSame($doc, $result);
        $this->assertTrue($result->hasFont('Times-Bold'));
    }

    public function testImportFromHtmlFileUsesProvidedDocument()
    {
        $doc = new Document();
        $doc->addFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));

        $result = Pdf\Pdf::importFromHtmlFile(__DIR__ . '/tmp/test.html', $doc);

        $this->assertSame($doc, $result);
        $this->assertTrue($result->hasFont('Times-Bold'));
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

    public function testExtractTextFromFileAllPagesJoinsWithBlankLineAndSkipsEmpty()
    {
        $text = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/doc.pdf');
        // Page 2 has two Tj runs whose text-space y differs by 270 units at a
        // 48pt font size (threshold = 12), so Interpreter classifies the second
        // run as SEPARATOR_NEWLINE (see InterpreterTest::testTdYChangeProducesNewlineSeparator
        // for the same rule at a smaller scale) - the runs are genuinely on very
        // different vertical positions on the page, not abutting text.
        $this->assertEquals("Hello World Again!\n\nHello World!\nHello World!", $text);
    }

    public function testExtractTextFromFileWithPageLimit()
    {
        $text = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/doc.pdf', null, 1);
        $this->assertEquals('Hello World Again!', $text);
    }

    public function testExtractTextFromFileWithSpecificPages()
    {
        $text = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/doc.pdf', [2]);
        // See testExtractTextFromFileAllPagesJoinsWithBlankLineAndSkipsEmpty() for
        // why the two runs on this page are separated by a newline.
        $this->assertEquals("Hello World!\nHello World!", $text);
    }

    public function testExtractTextFromDataWithPageLimit()
    {
        $text = Pdf\Pdf::extractTextFromData(file_get_contents(__DIR__ . '/tmp/doc.pdf'), null, 1);
        $this->assertEquals('Hello World Again!', $text);
    }

    public function testExtractTextFromFileThrowsExtractExceptionForMissingFile()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/does-not-exist.pdf');
    }

    public function testReversedCharsMarkedContentRestoresLogicalOrder()
    {
        $content = 'BT /F1 12 Tf /ReversedChars BMC (CBA) Tj EMC ET';
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4;
        $objs   = [1 => $obj1, 2 => $obj2, 3 => $obj3, 4 => $obj4];
        $offsets = [];
        $cur = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $xrefPos = strlen($header . $body);
        $xref = "xref\n0 5\n0000000000 65535 f \n";
        for ($i = 1; $i <= 4; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $tmpFile = tempnam(sys_get_temp_dir(), 'reversed_test_') . '.pdf';
        file_put_contents($tmpFile, $header . $body . $xref);

        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertEquals('ABC', $text);
    }

    public function testPageLimitAvoidsResolvingSkippedPagesContent()
    {
        $bigContent = 'BT /F1 12 Tf (' . str_repeat('A', 100000) . ') Tj ET';

        $objs = [];
        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $kidRefs = [];
        $numPages = 5;
        for ($i = 0; $i < $numPages; $i++) {
            $pageObjNum = 3 + $i * 2;
            $contentObjNum = $pageObjNum + 1;
            $kidRefs[] = "{$pageObjNum} 0 R";
            $objs[$pageObjNum] = "{$pageObjNum} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentObjNum} 0 R >>\nendobj\n";
            $objs[$contentObjNum] = "{$contentObjNum} 0 obj\n<< /Length " . strlen($bigContent) . " >>\nstream\n{$bigContent}\nendstream\nendobj\n";
        }
        $objs[2] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kidRefs) . "] /Count {$numPages} >>\nendobj\n";
        ksort($objs);

        $header = "%PDF-1.4\n";
        $offsets = [];
        $cur = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $maxObj = max(array_keys($objs));
        $xref = "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $tmpFile = tempnam(sys_get_temp_dir(), 'pagelimit_test_') . '.pdf';
        file_put_contents($tmpFile, $header . $body . $xref);

        $text = Pdf\Pdf::extractTextFromFile($tmpFile, null, 1);

        $this->assertStringContainsString(str_repeat('A', 100000), $text);

        // Deterministic check on the actual work-bounding, not a memory
        // measurement (memory_get_peak_usage(true) is monotonic and
        // polluted by whatever ran earlier in the process, so it can't
        // isolate this behavior). Pre-fix, PageWalker::walk() resolved
        // every page's content regardless of pageLimit; post-fix, only the
        // requested page's content is resolved - skipped pages still get a
        // PageInfo entry (preserving count/indexing) but with empty content.
        $doc   = \Pop\Pdf\Extract\Document::fromFile($tmpFile);
        $pages = \Pop\Pdf\Extract\Content\PageWalker::walk($doc, null, 1);
        unlink($tmpFile);

        $this->assertCount(5, $pages);
        $this->assertNotSame('', $pages[0]->content);
        $this->assertSame('', $pages[1]->content);
        $this->assertSame('', $pages[4]->content);
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

    public function testGetImageOnlyPagesForSynthetic3PageFixture()
    {
        $pages = Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/image-only-3page.pdf');
        $this->assertEquals([true, true, true], $pages);
    }

    public function testGetImageOnlyPagesForSynthetic2PageFixture()
    {
        $pages = Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/image-only-2page.pdf');
        $this->assertEquals([true, true], $pages);
    }

    public function testGetImageOnlyPagesForSynthetic5PageFixture()
    {
        $pages = Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/image-only-5page.pdf');
        $this->assertEquals([true, true, true, true, true], $pages);
    }

    public function testIsImageOnlyDocumentTrueForSyntheticFixtures()
    {
        $this->assertTrue(Pdf\Pdf::isImageOnlyDocument(__DIR__ . '/tmp/image-only-3page.pdf'));
        $this->assertTrue(Pdf\Pdf::isImageOnlyDocument(__DIR__ . '/tmp/image-only-2page.pdf'));
        $this->assertTrue(Pdf\Pdf::isImageOnlyDocument(__DIR__ . '/tmp/image-only-5page.pdf'));
    }

    public function testIsImageOnlyDataMatchesFileVariant()
    {
        $data = file_get_contents(__DIR__ . '/tmp/image-only-3page.pdf');
        $this->assertTrue(Pdf\Pdf::isImageOnlyData($data));
        $this->assertEquals([true, true, true], Pdf\Pdf::getImageOnlyPagesFromData($data));
    }

    public function testIsImageOnlyDocumentFalseForTextBasedFixture()
    {
        $this->assertFalse(Pdf\Pdf::isImageOnlyDocument(__DIR__ . '/tmp/doc.pdf'));
        $this->assertEquals([false, false, false], Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/doc.pdf'));
    }

    public function testIsImageOnlyDocumentThrowsExtractExceptionForMissingFile()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        Pdf\Pdf::isImageOnlyDocument(__DIR__ . '/tmp/does-not-exist.pdf');
    }

    public function testGetImageOnlyPagesRespectsPageLimit()
    {
        $pages = Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/image-only-5page.pdf', null, 2);
        $this->assertEquals([true, true], $pages);
    }

    public function testGetImageOnlyPagesRespectsSpecificPages()
    {
        $pages = Pdf\Pdf::getImageOnlyPages(__DIR__ . '/tmp/image-only-5page.pdf', [2, 4]);
        $this->assertEquals([true, true], $pages);
    }

    public function testContentsArrayAggregationStaysWithinDecodeBudgetRegardlessOfRefCount()
    {
        // A page's /Contents array can repeat the same indirect reference
        // any number of times - each individual decode is comfortably
        // under Flate's 64MB per-call cap, but concatenating them all
        // (PageWalker::resolveContent()'s implode()) has no running total
        // of its own. Only the per-document Budget bounds the aggregate.
        // 40 refs to a stream that decodes to 40MB each would be 1.6GB if
        // unbounded - this must degrade to empty content for the page
        // instead, with peak memory staying flat regardless of ref count.
        $bigChunk   = str_repeat('A', 40 * 1024 * 1024);
        $compressed = gzcompress($bigChunk, 1);
        unset($bigChunk);

        $objs = [];
        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objs[3] = "3 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n{$compressed}\nendstream\nendobj\n";

        $contentRefs = array_fill(0, 40, '3 0 R');
        $objs[4] = "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents [" . implode(' ', $contentRefs) . "] >>\nendobj\n";
        $objs[2] = "2 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";
        ksort($objs);

        $header  = "%PDF-1.4\n";
        $offsets = [];
        $cur     = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body   = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $maxObj  = max(array_keys($objs));
        $xref    = "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $pdfData = $header . $body . $xref;

        // Deterministic check on the actual work-bounding, not a memory
        // measurement (memory_get_peak_usage(true) is monotonic and
        // polluted by whatever ran earlier in the process, so it can't
        // isolate this test's contribution - same reasoning already used in
        // testPageLimitAvoidsResolvingSkippedPagesContent() above). Without
        // the fix, this decodes and concatenates 40 x 40MB = 1.6GB and
        // either exhausts memory or takes many seconds; with the fix, the
        // budget throws well before the 4th ref, so the page degrades to
        // empty content quickly.
        $start = microtime(true);
        $text  = Pdf\Pdf::extractTextFromData($pdfData);
        $elapsed = microtime(true) - $start;

        $this->assertEquals('', $text);
        $this->assertLessThan(5.0, $elapsed);
    }

    public function testExtractAsImagesWritesFileForEachPage()
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $location = sys_get_temp_dir() . '/pop-pdf-extract-as-images-' . uniqid();
        mkdir($location);

        $result = Pdf\Pdf::extractAsImages(__DIR__ . '/tmp/image-only-3page.pdf', $location);

        try {
            $this->assertCount(3, $result);
            $this->assertFileExists($result[1]);
            $this->assertFileExists($result[2]);
            $this->assertFileExists($result[3]);
        } finally {
            foreach (glob($location . '/*') as $file) {
                unlink($file);
            }
            rmdir($location);
        }
    }

    public function testExtractAsImagesRespectsPageLimit()
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $location = sys_get_temp_dir() . '/pop-pdf-extract-as-images-' . uniqid();
        mkdir($location);

        $result = Pdf\Pdf::extractAsImages(__DIR__ . '/tmp/image-only-3page.pdf', $location, 'jpg', 72, pageLimit: 2);

        try {
            $this->assertCount(2, $result);
            $this->assertArrayHasKey(1, $result);
            $this->assertArrayHasKey(2, $result);
            $this->assertArrayNotHasKey(3, $result);
        } finally {
            foreach (glob($location . '/*') as $file) {
                unlink($file);
            }
            rmdir($location);
        }
    }

    public function testExtractAsImagesRespectsSpecificPages()
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $location = sys_get_temp_dir() . '/pop-pdf-extract-as-images-' . uniqid();
        mkdir($location);

        $result = Pdf\Pdf::extractAsImages(__DIR__ . '/tmp/image-only-3page.pdf', $location, 'jpg', 72, pages: [2]);

        try {
            $this->assertCount(1, $result);
            $this->assertArrayHasKey(2, $result);
            $this->assertArrayNotHasKey(1, $result);
            $this->assertArrayNotHasKey(3, $result);
        } finally {
            foreach (glob($location . '/*') as $file) {
                unlink($file);
            }
            rmdir($location);
        }
    }

    public function testExtractAsImagesSupportsCustomFilenameFormat()
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $location = sys_get_temp_dir() . '/pop-pdf-extract-as-images-' . uniqid();
        mkdir($location);

        $result = Pdf\Pdf::extractAsImages(
            __DIR__ . '/tmp/image-only-3page.pdf', $location, 'jpg', 72, 'page_%2$d', pages: [1, 2]
        );

        try {
            $this->assertEquals('page_1.jpg', basename($result[1]));
            $this->assertEquals('page_2.jpg', basename($result[2]));
        } finally {
            foreach (glob($location . '/*') as $file) {
                unlink($file);
            }
            rmdir($location);
        }
    }

    public function testExtractAsImagesThrowsForMissingFile()
    {
        if (!class_exists('Imagick', false)) {
            $this->markTestSkipped('The Imagick extension is not available.');
        }

        $this->expectException(\Pop\Pdf\Build\Exception::class);
        Pdf\Pdf::extractAsImages(__DIR__ . '/tmp/does-not-exist.pdf', sys_get_temp_dir());
    }

    public function testMergeCombinesFiles()
    {
        $doc = Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', __DIR__ . '/tmp/test.pdf']);
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testMergeRawDataCombinesData()
    {
        $doc = Pdf\Pdf::mergeRawData([
            file_get_contents(__DIR__ . '/tmp/doc.pdf'),
            file_get_contents(__DIR__ . '/tmp/test.pdf'),
        ]);
        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testMergeIntoProvidedDocumentReturnsSameInstance()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $result = Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', __DIR__ . '/tmp/test.pdf'], $starter);

        $this->assertSame($starter, $result);
        $this->assertTrue($result->hasFont('Arial'));
    }

    public function testMergeRawDataIntoProvidedDocumentReturnsSameInstance()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $result = Pdf\Pdf::mergeRawData([
            file_get_contents(__DIR__ . '/tmp/doc.pdf'),
            file_get_contents(__DIR__ . '/tmp/test.pdf'),
        ], $starter);

        $this->assertSame($starter, $result);
        $this->assertTrue($result->hasFont('Arial'));
    }

    public function testMergeIntoProvidedDocumentKeepsThatDocumentsExistingPageContent()
    {
        // The "starter document" scenario: a caller that's already built up a
        // Document of its own wants the merged files' pages added to it,
        // rather than losing what they already had.
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('STARTER PAGE CONTENT', 12), 'Arial', 50, 700);
        $starter->addPage($page);

        $result = Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', __DIR__ . '/tmp/test.pdf'], $starter);

        // 1 starter page + however many the two merged files contribute.
        $this->assertGreaterThan(1, $result->getNumberOfPages());

        $tmpFile = tempnam(sys_get_temp_dir(), 'merge_into_document_test_') . '.pdf';
        Pdf\Pdf::writeToFile($result, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString('STARTER PAGE CONTENT', $text);
    }

    public function testMergeIntoProvidedDocumentPlacesItsExistingPagesBeforeMergedContent()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('STARTER PAGE MARKER', 12), 'Arial', 50, 700);
        $starter->addPage($page);

        $result = Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', __DIR__ . '/tmp/test.pdf'], $starter);

        $tmpFile = tempnam(sys_get_temp_dir(), 'merge_order_test_') . '.pdf';
        Pdf\Pdf::writeToFile($result, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $starterPosition = strpos($text, 'STARTER PAGE MARKER');
        $mergedPosition  = strpos($text, 'One More Time!');

        $this->assertNotFalse($starterPosition);
        $this->assertNotFalse($mergedPosition);
        $this->assertLessThan($mergedPosition, $starterPosition);
    }

    public function testMergeExtractedTextContainsBothSources()
    {
        // Reuses the already-built native text-extraction pipeline as an
        // independent correctness oracle for the merge, rather than
        // inspecting compiled bytes directly.
        $doc = Pdf\Pdf::merge([__DIR__ . '/tmp/test-extract.pdf', __DIR__ . '/tmp/doc.pdf']);

        $mergedFile = __DIR__ . '/tmp/merged-test.pdf';
        Pdf\Pdf::writeToFile($doc, $mergedFile);

        $mergedText   = Pdf\Pdf::extractTextFromFile($mergedFile);
        $sourceAText  = Pdf\Pdf::extractTextFromFile(__DIR__ . '/tmp/test-extract.pdf');

        unlink($mergedFile);

        $this->assertNotEmpty(trim($sourceAText));
        $this->assertStringContainsString(trim(explode("\n", $sourceAText)[0]), $mergedText);
    }

    public function testMergeRejectsEncryptedSourceWhenNoPasswordIsSupplied()
    {
        // Encrypted PDFs ARE supported now, but only when a password is
        // supplied - and Pdf::mergeRawData() has no way to supply one yet, so
        // an encrypted source must still be rejected here rather than merged
        // as ciphertext. This deliberately uses a REAL qpdf-encrypted file
        // rather than a hand-built /Encrypt stub: a stub would be rejected by
        // the missing-password check no matter how broken decryption was,
        // making the test pass for the wrong reason.
        $base      = tempnam(sys_get_temp_dir(), 'pop_pdf_merge_enc_');
        $encrypted = $base . '.pdf';
        $source    = __DIR__ . '/tmp/test-extract.pdf';

        exec(
            'qpdf ' . escapeshellarg('--encrypt') . ' ' . escapeshellarg('open-me') . ' ' .
            escapeshellarg('admin123') . ' ' . escapeshellarg('256') . ' ' . escapeshellarg('--') . ' ' .
            escapeshellarg($source) . ' ' . escapeshellarg($encrypted) . ' 2>&1',
            $output,
            $status
        );

        // qpdf exits 3 on warnings, so only a missing/empty output file
        // reliably means qpdf itself is unavailable.
        if (!file_exists($encrypted) || (filesize($encrypted) === 0)) {
            @unlink($base);
            $this->markTestSkipped('qpdf is not available to produce an encrypted fixture: ' . implode("\n", $output));
        }

        $encryptedData = file_get_contents($encrypted);

        // Positive control: the fixture really is openable given the password,
        // so the rejection below is specifically about the MISSING password
        // and not about a broken fixture or broken decryption.
        $this->assertTrue((new Pdf\Extract\Document($encryptedData, 'open-me'))->isEncrypted());

        @unlink($base);
        @unlink($encrypted);

        $this->expectException('Pop\Pdf\Build\Exception');
        $this->expectExceptionMessage('a password is required');
        Pdf\Pdf::mergeRawData([file_get_contents(__DIR__ . '/tmp/doc.pdf'), $encryptedData]);
    }

    public function testWriteThenReadRoundTripsAnEncryptedDocument()
    {
        // This proves this library's own write path (Document\Security /
        // Build\Compiler) and its own read path (Extract\Document /
        // Build\Security\StandardSecurityHandler) agree with each other -
        // it is NOT a substitute for the qpdf-fixture tests elsewhere in this
        // suite (e.g. testMergeRejectsEncryptedSourceWhenNoPasswordIsSupplied
        // and the Build\Security\StandardSecurityHandlerTest fixtures), which
        // prove interoperability with an INDEPENDENT encryptor. A bug shared
        // by both directions here (both sides agreeing on a wrong algorithm
        // detail) would pass this test while failing the qpdf-based ones -
        // that asymmetry is exactly why both kinds of test exist.
        $document = new Document();
        $document->addFont(new Font('Arial'));
        $document->setSecurity(new Security('open-me', 'admin123'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('Round trip content', 12), 'Arial', 50, 700);
        $document->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_roundtrip_') . '.pdf';
        Pdf\Pdf::writeToFile($document, $tmpFile);

        $text = Pdf\Pdf::extractTextFromFile($tmpFile, null, null, 'open-me');
        unlink($tmpFile);

        $this->assertStringContainsString('Round trip content', $text);
    }

    public function testCyrillicTextRoundTripsThroughEmbeddedCidFont()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/tmp/fonts/DejaVuSans.ttf'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('123 ПРИВІТ:', 36), $doc->getCurrentFont(), 50, 700);
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'cyrillic_test_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString('123 ПРИВІТ:', $text);
    }

    public function testGreekTextRoundTripsThroughEmbeddedCidFont()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/tmp/fonts/DejaVuSans.ttf'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('Καλημέρα', 36), $doc->getCurrentFont(), 50, 700);
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'greek_test_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString('Καλημέρα', $text);
    }

    public function testStandardFontWithCyrillicTextThrows()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');

        $doc = new Document();
        $doc->addFont(new Font(Font::ARIAL));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('ПРИВІТ', 36), 'Arial', 50, 700);
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'should_not_write_');

        try {
            Pdf\Pdf::writeToFile($doc, $tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function testStandardFontWithWinAnsiPunctuationWritesFile()
    {
        $doc = new Document();
        $doc->addFont(new Font(Font::ARIAL));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text("A\u{00A0}B\u{00AD}C\u{2018}D\u{2020}", 24), 'Arial', 50, 700);
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'winansi_test_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $this->assertFileExists($tmpFile);
        $this->assertStringStartsWith('%PDF', file_get_contents($tmpFile));
        unlink($tmpFile);
    }

    /**
     * Before the WinAnsi-transcoding fix, standard-font text was written to
     * the content stream as raw UTF-8 bytes inside a WinAnsiEncoding string -
     * any character outside plain ASCII (accented letters, curly quotes,
     * dashes) would mojibake even though requireGlyphCoverage() correctly
     * said the font supports it. This round trip is the real-world proof
     * the fix works: the text written must be exactly what's read back.
     */
    public function testStandardFontWinAnsiTextRoundTrips()
    {
        $doc = new Document();
        $doc->addFont(new Font(Font::ARIAL));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(
            new Document\Page\Text("café \u{2018}quoted\u{2019} \u{2020}dagger \u{00A0}\u{00AD}", 24),
            'Arial', 50, 700
        );
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'winansi_roundtrip_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringContainsString("café \u{2018}quoted\u{2019} \u{2020}dagger", $text);
    }

    /**
     * times.ttf's cmap maps both U+0020 (space) and U+00A1 (inverted
     * exclamation) to GID 3, so a last-write-wins /ToUnicode inversion
     * makes every space in the document extract back as a '¡'.
     */
    public function testAsciiTextRoundTripsWithoutToUnicodeCollisionCorruption()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/tmp/fonts/times.ttf'));

        $page = new Document\Page(Document\Page::LETTER);
        $page->addText(new Document\Page\Text('Hello World from times', 24), $doc->getCurrentFont(), 50, 700);
        $doc->addPage($page);

        $tmpFile = tempnam(sys_get_temp_dir(), 'tounicode_test_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = Pdf\Pdf::extractTextFromFile($tmpFile);
        unlink($tmpFile);

        $this->assertStringNotContainsString('¡', $text);
        $this->assertStringContainsString('Hello World from times', $text);
    }

    /**
     * Encrypt a fixture PDF with qpdf, returning the path to a temp
     * encrypted file - or marking the calling test skipped (and returning
     * an empty string, never reached by the caller) if qpdf isn't
     * available. Caller is responsible for unlink()-ing the returned path.
     *
     * 256-bit encryption forces AES automatically. qpdf 11+ refuses a bare
     * 128-bit request (it defaults to RC4 and rejects that outright), so
     * this deliberately sticks to 256 throughout rather than juggling a
     * --use-aes=y flag for a key length these tests don't otherwise care
     * about.
     *
     * @param  string $source
     * @param  string $userPassword
     * @param  string $ownerPassword
     * @return string
     */
    private function encryptFixture(
        string $source, string $userPassword = 'open-me', string $ownerPassword = 'admin123'
    ): string
    {
        $encrypted = tempnam(sys_get_temp_dir(), 'pop_pdf_enc_test_') . '.pdf';

        exec(
            'qpdf --encrypt ' . escapeshellarg($userPassword) . ' ' . escapeshellarg($ownerPassword) . ' 256 -- ' .
            escapeshellarg($source) . ' ' . escapeshellarg($encrypted) . ' 2>&1',
            $output,
            $status
        );

        // qpdf exits 3 on warnings, so only a missing/empty output file
        // reliably means qpdf itself is unavailable.
        if (!file_exists($encrypted) || (filesize($encrypted) === 0)) {
            @unlink($encrypted);
            $this->markTestSkipped('qpdf is not available to produce an encrypted fixture: ' . implode("\n", $output));
        }

        return $encrypted;
    }

    public function testImportFromFileOpensAnEncryptedPdfGivenTheCorrectPassword()
    {
        $encrypted = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');

        $doc = Pdf\Pdf::importFromFile($encrypted, null, 'open-me');
        unlink($encrypted);

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testImportFromFileThrowsForAnEncryptedPdfWithNoPassword()
    {
        $encrypted = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');

        $this->expectException('Pop\Pdf\Build\Exception');
        $this->expectExceptionMessage('a password is required');

        try {
            Pdf\Pdf::importFromFile($encrypted);
        } finally {
            unlink($encrypted);
        }
    }

    public function testImportRawDataOpensAnEncryptedPdfGivenTheCorrectPassword()
    {
        $encrypted     = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');
        $encryptedData = file_get_contents($encrypted);
        unlink($encrypted);

        $doc = Pdf\Pdf::importRawData($encryptedData, null, 'open-me');

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testImportRawDataThrowsForAnEncryptedPdfWithNoPassword()
    {
        $encrypted     = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');
        $encryptedData = file_get_contents($encrypted);
        unlink($encrypted);

        $this->expectException('Pop\Pdf\Build\Exception');
        $this->expectExceptionMessage('a password is required');

        Pdf\Pdf::importRawData($encryptedData);
    }

    public function testExtractTextFromFileRecoversTextFromAnEncryptedPdf()
    {
        $unencrypted = __DIR__ . '/tmp/test-extract.pdf';
        $encrypted   = $this->encryptFixture($unencrypted);

        $plainText     = Pdf\Pdf::extractTextFromFile($unencrypted);
        $decryptedText = Pdf\Pdf::extractTextFromFile($encrypted, null, null, 'open-me');
        unlink($encrypted);

        $this->assertNotEmpty(trim($plainText));
        $this->assertEquals($plainText, $decryptedText);
    }

    public function testExtractTextFromFileThrowsForAnEncryptedPdfWithNoPassword()
    {
        $encrypted = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');

        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $this->expectExceptionMessage('a password is required');

        try {
            Pdf\Pdf::extractTextFromFile($encrypted);
        } finally {
            unlink($encrypted);
        }
    }

    public function testExtractTextFromDataRecoversTextFromAnEncryptedPdf()
    {
        $unencrypted   = __DIR__ . '/tmp/test-extract.pdf';
        $encrypted     = $this->encryptFixture($unencrypted);
        $encryptedData = file_get_contents($encrypted);
        unlink($encrypted);

        $plainText     = Pdf\Pdf::extractTextFromData(file_get_contents($unencrypted));
        $decryptedText = Pdf\Pdf::extractTextFromData($encryptedData, null, null, 'open-me');

        $this->assertNotEmpty(trim($plainText));
        $this->assertEquals($plainText, $decryptedText);
    }

    public function testExtractTextFromDataThrowsForAnEncryptedPdfWithNoPassword()
    {
        $encrypted     = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');
        $encryptedData = file_get_contents($encrypted);
        unlink($encrypted);

        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $this->expectExceptionMessage('a password is required');

        Pdf\Pdf::extractTextFromData($encryptedData);
    }

    public function testMergeCombinesAnEncryptedFileGivenItsPassword()
    {
        $encrypted = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');

        $doc = Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', $encrypted], new Document(), [1 => 'open-me']);
        unlink($encrypted);

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(1, $doc->getNumberOfPages());
    }

    public function testMergeThrowsForAnEncryptedFileWithNoPassword()
    {
        $encrypted = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');

        $this->expectException('Pop\Pdf\Build\Exception');
        $this->expectExceptionMessage('a password is required');

        try {
            Pdf\Pdf::merge([__DIR__ . '/tmp/doc.pdf', $encrypted]);
        } finally {
            unlink($encrypted);
        }
    }

    public function testMergeRawDataCombinesEncryptedDataGivenItsPassword()
    {
        $encrypted     = $this->encryptFixture(__DIR__ . '/tmp/test-extract.pdf');
        $encryptedData = file_get_contents($encrypted);
        unlink($encrypted);

        $doc = Pdf\Pdf::mergeRawData(
            [file_get_contents(__DIR__ . '/tmp/doc.pdf'), $encryptedData],
            new Document(),
            [1 => 'open-me']
        );

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertGreaterThan(1, $doc->getNumberOfPages());
    }

}
