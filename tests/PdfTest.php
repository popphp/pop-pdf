<?php

namespace Pop\Pdf\Test;

use Pop\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
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

    public function testMergeRejectsEncryptedSource()
    {
        // No fixture fixture of an actual encrypted PDF exists in this repo
        // (Extract\Document has never supported decryption) - simulate one
        // directly against a minimal hand-built PDF with /Encrypt in its
        // trailer, matching Extract\Document's own existing rejection.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2 . $obj3;
        $offsets = [strlen($header), strlen($header . $obj1), strlen($header . $obj1 . $obj2)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[0]) .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 4 /Root 1 0 R /Encrypt << /Filter /Standard >> >>\nstartxref\n{$xrefPos}\n%%EOF";

        $encryptedData = $header . $body . $xref;

        $this->expectException('Pop\Pdf\Build\Exception');
        Pdf\Pdf::mergeRawData([file_get_contents(__DIR__ . '/tmp/doc.pdf'), $encryptedData]);
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

}
