<?php

namespace Pop\Pdf\Test\Build\Html;

use Pop\Pdf\Build\Html\Parser;
use Pop\Pdf\Document;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    public function testConstructor()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $this->assertInstanceOf('Pop\Pdf\Build\Html\Parser', $html);
    }

    public function testParseString()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc);
        $this->assertNotNull($html->getHtml());
    }

    public function testParseFile()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $this->assertNotNull($html->getHtml());
    }

    public function testParseFileException()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/bad.html', $doc);
    }

    public function testDefaultStyles()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->setDefaultStyle('font-family', 'Times');
        $this->assertEquals('Times', $html->getDefaultStyle('font-family'));
        $this->assertIsArray($html->getDefaultStyles());
    }

    public function testCssString()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc);
        $html->parseCss('p {margin: 0; padding: 0;}');
        $this->assertNotNull($html->getCss());
    }

    public function testCssFile()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->parseCssFile(__DIR__ . '/../../tmp/test.css');
        $this->assertNotNull($html->getCss());
    }

    public function testParseUri()
    {
        // file_get_contents() doesn't distinguish a URI scheme from a plain
        // local path, so a local file path exercises parseUri()/
        // parseHtmlUri() without any real network I/O.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseUri(__DIR__ . '/../../tmp/test.html', $doc);
        $this->assertNotNull($html->getHtml());
    }

    public function testParseHtmlUri()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->parseHtmlUri(__DIR__ . '/../../tmp/test.html');
        $this->assertNotNull($html->getHtml());
    }

    public function testParseCssCalledTwiceAccumulatesOnTheSameCssObject()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc);
        $html->parseCss('p { margin: 0; }');
        $html->parseCss('h1 { color: red; }');
        $this->assertNotNull($html->getCss());
        $this->assertTrue($html->getCss()->hasSelector('p'));
        $this->assertTrue($html->getCss()->hasSelector('h1'));
    }

    public function testParseCssFileCalledTwiceUsesExistingCssObject()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->parseCssFile(__DIR__ . '/../../tmp/test.css');
        $html->parseCssFile(__DIR__ . '/../../tmp/test.css');
        $this->assertNotNull($html->getCss());
    }

    public function testParseCssUri()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->parseCssUri(__DIR__ . '/../../tmp/test.css');
        $this->assertNotNull($html->getCss());
    }

    public function testParseCssUriCalledTwiceUsesExistingCssObject()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->parseCssUri(__DIR__ . '/../../tmp/test.css');
        $html->parseCssUri(__DIR__ . '/../../tmp/test.css');
        $this->assertNotNull($html->getCss());
    }

    public function testGetDocument()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $this->assertInstanceOf('Pop\Pdf\Document', $html->getDocument());
        $this->assertInstanceOf('Pop\Pdf\Document', $html->document());
    }

    /**
     * The constructor's Document parameter defaults to a fresh new Document()
     * instance rather than null, so the parser is always immediately usable
     * without the caller having to construct and pass one.
     */
    public function testConstructorWithoutDocumentDefaultsToNewDocument()
    {
        $html = new Parser();
        $this->assertInstanceOf('Pop\Pdf\Document', $html->getDocument());

        $html->parseHtml('<h1>Hello World</h1>');
        $doc = $html->process();

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
    }

    public function testParseStringWithoutDocumentDefaultsToNewDocument()
    {
        $html = Parser::parseString('<h1>Hello World</h1>');
        $doc  = $html->process();

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
    }

    public function testParseFileWithoutDocumentDefaultsToNewDocument()
    {
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html');
        $doc  = $html->process();

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
    }

    public function testParseUriWithoutDocumentDefaultsToNewDocument()
    {
        $html = Parser::parseUri(__DIR__ . '/../../tmp/test.html');
        $doc  = $html->process();

        $this->assertInstanceOf('Pop\Pdf\Document', $doc);
        $this->assertTrue($doc->hasPages());
    }

    public function testSetAndGetPageSize()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setPageSize('LETTER');
        $this->assertEquals('LETTER', $html->getPageSize());
    }

    public function testSetAndGetPageMargins()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setPageMargins(0, 0, 0, 0);
        $html->setPageTopMargin(10);
        $html->setPageRightMargin(15);
        $html->setPageBottomMargin(20);
        $html->setPageLeftMargin(25);

        $this->assertEquals(10, $html->getPageTopMargin());
        $this->assertEquals(15, $html->getPageRightMargin());
        $this->assertEquals(20, $html->getPageBottomMargin());
        $this->assertEquals(25, $html->getPageLeftMargin());
        $this->assertEquals(4, count($html->getPageMargins()));
    }

    public function testSetAndGetXandY()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setX(50);
        $html->setY(75);

        $this->assertEquals(50, $html->getX());
        $this->assertEquals(75, $html->getY());
    }

    public function testProcess()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc);
        $html->process();
        $this->assertInstanceOf('Pop\Pdf\Build\Html\Parser', $html);
    }

    public function testDivWithBorderAndBackgroundRendersAPath()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<div class="boxed">Hello</div>', $doc);
        $html->parseCss('.boxed { border-width: 2px; border-color: #ff0000; background-color: #eeeeee; }');
        $html->process();

        $page = $doc->getPage(1);
        $this->assertTrue($page->hasPaths());
        $this->assertGreaterThanOrEqual(2, count($page->getPaths())); // background fill + border stroke
    }

    public function testDivWithRgbFunctionColorsRendersAPath()
    {
        // Color::parse() resolves an rgb(...) string directly to a Color\Rgb
        // instance, which has no toRgb() method to convert from itself -
        // this must not fatal when resolving border/background/text color.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<div class="boxed">Hello</div>', $doc);
        $html->parseCss(
            '.boxed { border-width: 2px; border-color: rgb(255, 0, 0); ' .
            'background-color: rgb(238, 238, 238); color: rgb(0, 0, 255); }'
        );
        $html->process();

        $page = $doc->getPage(1);
        $this->assertTrue($page->hasPaths());
        $this->assertGreaterThanOrEqual(2, count($page->getPaths())); // background fill + border stroke
    }

    public function testDrawBoxSkipsPathsWhenNoBorderOrBackgroundSet()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);

        $html->drawBox(10, 700, 100, 20, [
            'borderWidth'     => 0,
            'borderColor'     => [0, 0, 0],
            'backgroundColor' => null,
        ]);

        // drawBox() with no border/background must not touch the document's
        // page at all - confirmed by there being no page yet (drawBox()
        // itself must not lazily create one when it has nothing to draw).
        $this->assertFalse($doc->hasPages());
    }

    public function testProcessRendersATableWithBordersAndAdvancesTheCursor()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString(
            '<table><tr><th>Name</th><th>Value</th></tr><tr><td>Widgets</td><td>42</td></tr></table>' .
            '<p>After the table</p>',
            $doc
        );
        $html->parseCss('table { border-width: 1px; border-color: #000000; }');
        $html->parseCss('th { border-width: 1px; border-color: #000000; }');
        $html->parseCss('td { border-width: 1px; border-color: #000000; }');
        $html->process();

        $page = $doc->getPage(1);
        $this->assertTrue($page->hasText());
        $this->assertTrue($page->hasPaths());

        $strings = array_map(fn($t) => $t['text']->getString(), $page->getText());
        $this->assertContains('Name', $strings);
        $this->assertContains('Widgets', $strings);

        // The <p> after the table renders via Page\Text\Stream
        // (Page::addTextStream()) - a separate mechanism from the table
        // cells' Page::addText() calls, so it never shows up in
        // Page::getText(). "After the table" must not visually collide
        // with the table - i.e. the parser's cursor was actually advanced
        // by the table's height, closing the pre-existing bug where the
        // old table code never touched $this->y at all.
        $textStreams = $page->getTextStreams();
        $this->assertCount(1, $textStreams);
        $streamStrings = array_map(fn($s) => $s['string'], $textStreams[0]->getTextStreams());
        $this->assertContains('After the table', $streamStrings);

        $tableYPositions = array_map(fn($t) => $t['y'], $page->getText());
        $this->assertLessThan(min($tableYPositions), $textStreams[0]->getStartY());
    }

    public function testColspanAndRowspanRenderWithoutError()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString(
            '<table>' .
            '<tr><td colspan="2">Header spans two</td></tr>' .
            '<tr><td rowspan="2">Tall</td><td>B1</td></tr>' .
            '<tr><td>B2</td></tr>' .
            '</table>',
            $doc
        );
        $html->process();

        $page    = $doc->getPage(1);
        $strings = array_map(fn($t) => $t['text']->getString(), $page->getText());
        $this->assertContains('Header spans two', $strings);
        $this->assertContains('Tall', $strings);
        $this->assertContains('B1', $strings);
        $this->assertContains('B2', $strings);
    }

    public function testRealPdfOutputExtractsTableTextInRowOrder()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test-table.html', $doc);
        $html->process();

        // The fixture's CSS lives in a linked test-table.css file rather
        // than an inline <style> block, because Parser::prepare() only
        // reads <link type="text/css"> in <head> - inline <style> content
        // is silently dropped by pop-dom's own HTML parsing before it ever
        // reaches this library. An inline <style> block here would make
        // this assertion pass vacuously (0 paths, no exception) without
        // actually exercising border/background rendering at all.
        $page = $doc->getPage(1);
        $this->assertGreaterThan(0, count($page->getPaths()));

        $outputFile = __DIR__ . '/../../tmp/test-table-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);

        $this->assertFileExists($outputFile);

        if (trim((string) shell_exec('which pdftotext')) !== '') {
            $text = shell_exec('pdftotext ' . escapeshellarg($outputFile) . ' - 2>/dev/null');
            $this->assertStringContainsString('Item', $text);
            $this->assertStringContainsString('Quantity', $text);
            $this->assertStringContainsString('Widgets', $text);
            $this->assertStringContainsString('Bulk Order', $text);

            $pdfinfo = shell_exec('pdfinfo ' . escapeshellarg($outputFile) . ' 2>&1');
            $this->assertStringNotContainsString('Error', $pdfinfo);
        }

        unlink($outputFile);
    }

    public function testOrdinaryMultiParagraphDocumentDoesNotLoseContent()
    {
        $html = '';
        for ($i = 1; $i <= 14; $i++) {
            $words = [];
            for ($w = 1; $w <= 40; $w++) {
                $words[] = "w{$i}_{$w}";
            }
            $html .= '<p>PARA' . $i . ' ' . implode(' ', $words) . ' end' . $i . '</p>';
        }

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString($html, $doc);
        $parser->process();

        $outputFile = __DIR__ . '/../../tmp/multipara-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);
        $this->assertFileExists($outputFile);

        if (trim((string) shell_exec('which pdftotext')) !== '') {
            $text = shell_exec('pdftotext ' . escapeshellarg($outputFile) . ' - 2>/dev/null');
            for ($i = 1; $i <= 14; $i++) {
                $this->assertMatchesRegularExpression('/\bPARA' . $i . '\b/', $text, "PARA{$i} should survive extraction");
                $this->assertMatchesRegularExpression('/\bend' . $i . '\b/', $text, "end{$i} should survive extraction");
            }

            $pageCount = (int) trim((string) shell_exec('pdfinfo ' . escapeshellarg($outputFile) . ' 2>/dev/null | grep Pages | awk \'{print $2}\''));
            $this->assertEquals(2, $pageCount, 'Should be exactly 2 pages, not inflated by a page-break cascade');

            for ($i = 1; $i <= 14; $i++) {
                // Word-boundary regex, not substr_count: "end1" is a substring of "end10".."end14",
                // so a plain substr_count would over-count end1 as 6 instead of 1.
                $paraCount = preg_match_all('/\bPARA' . $i . '\b/', $text);
                $endCount  = preg_match_all('/\bend' . $i . '\b/', $text);
                $this->assertEquals(1, $paraCount, "PARA{$i} should appear exactly once, not duplicated");
                $this->assertEquals(1, $endCount, "end{$i} should appear exactly once, not duplicated");
            }
        }

        unlink($outputFile);
    }

    public function testFloatedImageFollowedByMultipleParagraphsDoesNotOverlap()
    {
        $imagePath = sys_get_temp_dir() . '/parser-float-test-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        $html = '<img src="' . basename($imagePath) . '" align="left" width="100" />';
        for ($i = 1; $i <= 5; $i++) {
            $html .= "<p>FLOATPARA{$i} some text here</p>";
        }

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml($html, dirname($imagePath));
        $parser->process();

        unlink($imagePath);

        $page = $doc->getPage(1);
        $startYs = [];
        foreach ($page->getTextStreams() as $stream) {
            $startYs[] = $stream->getStartY();
        }

        $this->assertCount(5, $startYs);
        // Every paragraph must land at a distinct, strictly descending Y -
        // before this fix, all 5 rendered at the exact same startY.
        for ($i = 1; $i < count($startYs); $i++) {
            $this->assertLessThan($startYs[$i - 1], $startYs[$i], "Paragraph {$i} should render below the previous one, not overlapping it");
        }
    }

    public function testContentAfterAPageSpanningParagraphDoesNotOverlapItself()
    {
        $words = [];
        for ($i = 1; $i <= 900; $i++) {
            $words[] = "w{$i}";
        }
        $html = '<p>' . implode(' ', $words) . '</p><p>AFTER1 text here</p><p>AFTER2 text here</p>';

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString($html, $doc);
        $parser->process();

        $lastPage = $doc->getPage($doc->getNumberOfPages());
        $after1Y = null;
        $after2Y = null;
        foreach ($lastPage->getTextStreams() as $stream) {
            $first = $stream->getTextStreams()[0]['string'] ?? '';
            if (str_starts_with($first, 'AFTER1')) {
                $after1Y = $stream->getStartY();
            } elseif (str_starts_with($first, 'AFTER2')) {
                $after2Y = $stream->getStartY();
            }
        }

        $this->assertNotNull($after1Y);
        $this->assertNotNull($after2Y);
        $this->assertLessThan($after1Y, $after2Y, 'AFTER2 should render below AFTER1, not above/overlapping it');
    }

    public function testAnchorElementDoesNotThrow()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p>Hi <a href="http://example.com">link</a> there</p>', $doc);
        $html->process();

        $page = $doc->getPage(1);
        $strings = [];
        foreach ($page->getTextStreams() as $stream) {
            foreach ($stream->getTextStreams() as $entry) {
                $strings[] = $entry['string'];
            }
        }
        $this->assertContains('link', $strings);
    }

    public function testUnescapedPunctuationDoesNotCorruptOutput()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p>Smiley :) and more text after it that should appear.</p>', $doc);
        $html->process();

        $outputFile = __DIR__ . '/../../tmp/escape-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);

        if (trim((string) shell_exec('which pdftotext')) === '') {
            unlink($outputFile);
            $this->markTestSkipped('pdftotext is not available.');
        }

        $text = shell_exec('pdftotext ' . escapeshellarg($outputFile) . ' - 2>&1');
        $this->assertStringNotContainsString('Syntax Error', $text);
        $this->assertStringContainsString(':)', $text);
        $this->assertStringContainsString('and more text after it that should appear.', $text);

        unlink($outputFile);
    }

    public function testBackslashSequencesSurviveOutputUnchanged()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p>Path is C:\Users\name and 50% off</p><p>Ratio 3\4 done</p>', $doc);
        $html->process();

        $outputFile = __DIR__ . '/../../tmp/backslash-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);

        if (trim((string) shell_exec('which pdftotext')) === '') {
            unlink($outputFile);
            $this->markTestSkipped('pdftotext is not available.');
        }

        $text = shell_exec('pdftotext ' . escapeshellarg($outputFile) . ' - 2>/dev/null');
        $this->assertStringContainsString('C:\Users\name', $text);
        $this->assertStringContainsString('Ratio 3\4 done', $text);

        unlink($outputFile);
    }

    public function testFloatedImageWithFollowingContentDoesNotHangOrLoseContent()
    {
        $imagePath = sys_get_temp_dir() . '/parser-float-orphan-test-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(900, 40);
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        // 60 divs (not the more modest count that might first come to mind) -
        // this needs to push $this->y down far enough that the paragraph
        // below the float starts close enough to the bottom margin that its
        // *very first* orphan-loop iteration needs a new page while still
        // under the float's narrow/short box. That's the exact state that
        // exposes the missing startX/edgeX/edgeY reset: too little content
        // above the image and the loop never enters that branch while the
        // float box is still in effect, masking the bug.
        $html = '';
        for ($i = 1; $i <= 60; $i++) {
            $html .= "<div>Line {$i}</div>";
        }
        $html .= '<img src="' . basename($imagePath) . '" align="left" width="450" />';
        $html .= '<p>' . implode(' ', array_map(fn($i) => "word{$i}", range(1, 200))) . '</p>';

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml($html, dirname($imagePath));

        $start = microtime(true);
        $parser->process();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, 'Processing should complete quickly, not hang');

        $outputFile = __DIR__ . '/../../tmp/float-orphan-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);

        // Unlink only after writeToFile(): the compiled image is re-read from
        // disk at compile time (Document\Page\Image just stores the path;
        // Build\Image\Parser::loadImageFromFile() reads it during
        // Pdf::writeToFile()), not eagerly during parse()/process().
        unlink($imagePath);

        if (trim((string) shell_exec('which pdftotext')) !== '') {
            $text = shell_exec('pdftotext ' . escapeshellarg($outputFile) . ' - 2>/dev/null');
            for ($i = 1; $i <= 200; $i++) {
                // Word-boundary regex, not substr_count: "word1" is a substring
                // of "word10".."word19", "word100".."word199", etc.
                $this->assertMatchesRegularExpression('/\bword' . $i . '\b/', $text, "word{$i} should survive extraction");
                // Exactly once, not just present: the stale float geometry bug
                // doesn't just garble wrapping, it duplicates a run of words
                // onto the page that follows (the loop re-emits the tail of
                // the still-narrow-constrained segment once the geometry
                // self-corrects), so a presence-only check would pass even
                // with the bug still there.
                $count = preg_match_all('/\bword' . $i . '\b/', $text);
                $this->assertEquals(1, $count, "word{$i} should appear exactly once, not duplicated");
            }
        }

        unlink($outputFile);
    }

    public function testOversizedLineHeightDoesNotHang()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="tall">' . str_repeat('A', 150) . ' then some words</p>', $doc);
        $parser->parseCss('.tall { line-height: 900px; }');

        $start = microtime(true);
        $parser->process();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, 'Processing should complete quickly, not hang');
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    protected function makeTestImage(): string
    {
        $imagePath = sys_get_temp_dir() . '/parser-img-test-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));
        imagejpeg($image, $imagePath, 85);
        imagedestroy($image);
        return $imagePath;
    }

    public function testPlainInlineImageWithNoAlignIsNotTreatedAsAFloat()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml('<img src="' . basename($imagePath) . '" width="100" />', dirname($imagePath));
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
    }

    public function testImageSizedByHeightAttributeOnly()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml('<img src="' . basename($imagePath) . '" height="50" />', dirname($imagePath));
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
    }

    public function testImageSizedByCssWidthClass()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml('<img src="' . basename($imagePath) . '" class="w" />', dirname($imagePath));
        $parser->parseCss('.w { width: 80px; }');
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
    }

    public function testImageSizedByCssHeightClass()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml('<img src="' . basename($imagePath) . '" class="h" />', dirname($imagePath));
        $parser->parseCss('.h { height: 40px; }');
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
    }

    public function testImageAlignRightFloatsTextToTheLeft()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml(
            '<img src="' . basename($imagePath) . '" align="right" width="80" /><p>text beside the image</p>',
            dirname($imagePath)
        );
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testAllHeadingLevelsRenderWithoutError()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<h1>1</h1><h2>2</h2><h3>3</h3><h4>4</h4><h5>5</h5><h6>6</h6>', $doc);
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testConstructorSkipsReRegisteringFontsAlreadyOnTheDocument()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $doc->addFont(new Document\Font('Arial,Bold'));
        $doc->addFont(new Document\Font('Arial,Italic'));
        $doc->addFont(new Document\Font('Arial,BoldItalic'));
        $doc->addFont(new Document\Font('TimesNewRoman'));
        $doc->addFont(new Document\Font('TimesNewRoman,Bold'));
        $doc->addFont(new Document\Font('TimesNewRoman,Italic'));
        $doc->addFont(new Document\Font('TimesNewRoman,BoldItalic'));

        // The constructor's createDefaultStyles() checks hasFont() before
        // adding each default font - with all 8 already present, every
        // check takes the "already registered" branch.
        $html = new Parser($doc);
        $this->assertInstanceOf(Parser::class, $html);
    }

    public function testEmptyBorderedElementStillDrawsABox()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<div class="empty-box"></div>', $doc);
        $parser->parseCss('.empty-box { border-width: 1px; border-color: #000000; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasPaths());
    }

    public function testImageFloatsViaCssFloatPropertyNotJustTheAlignAttribute()
    {
        $imagePath = $this->makeTestImage();

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = new Parser($doc);
        $parser->parseHtml(
            '<img src="' . basename($imagePath) . '" class="f" width="80" /><p>text beside the image</p>',
            dirname($imagePath)
        );
        $parser->parseCss('.f { float: left; }');
        $parser->process();

        unlink($imagePath);

        $this->assertCount(1, $doc->getPage(1)->getImages());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testSetXPastLeftMarginNarrowsTheWrapLength()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p>hello world this is a longer line of text to wrap</p>', $doc);
        $parser->setX(100);
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testIdSelectorAppliesFullStyleCascade()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<div id="box">text</div>', $doc);
        $parser->parseCss(
            '#box { font-family: Arial; font-size: 14px; font-weight: bold; color: #ff0000; ' .
            'float: left; width: 100px; height: 50px; line-height: 20px; margin-top: 5px; ' .
            'padding-top: 5px; margin-right: 5px; padding-right: 5px; margin-bottom: 5px; ' .
            'padding-bottom: 5px; margin-left: 5px; padding-left: 5px; text-align: center; ' .
            'border-width: 2px; border-color: #000000; background-color: #cccccc; }'
        );
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasPaths());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testClassSelectorAppliesFullStyleCascade()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<div class="box2">text</div>', $doc);
        $parser->parseCss(
            '.box2 { font-family: Arial; font-size: 14px; font-weight: bold; color: #ff0000; ' .
            'float: left; width: 100px; height: 50px; line-height: 20px; margin-top: 5px; ' .
            'padding-top: 5px; margin-right: 5px; padding-right: 5px; margin-bottom: 5px; ' .
            'padding-bottom: 5px; margin-left: 5px; padding-left: 5px; text-align: center; ' .
            'border-width: 2px; border-color: #000000; background-color: #cccccc; }'
        );
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasPaths());
        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testFontFamilyListFallsBackToALaterAvailableFont()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="ff">text</p>', $doc);
        $parser->parseCss('.ff { font-family: "NotAFont", Arial; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testSansSerifAndSerifFontFamilyKeywordsResolveToDefaultFonts()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $doc->addFont(new Document\Font('TimesNewRoman'));
        $parser = Parser::parseString('<p class="ss">a</p><p class="sr">b</p>', $doc);
        $parser->parseCss('.ss { font-family: sans-serif; } .sr { font-family: serif; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testUnresolvableFontFamilyThrowsException()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="missing">a</p>', $doc);
        $parser->parseCss('.missing { font-family: TotallyMissingFont; }');
        $parser->process();
    }

    public function testFontFamilyListWhereNoEntryMatchesAnyVariantThrowsException()
    {
        // Distinct from testUnresolvableFontFamilyThrowsException: a COMMA-separated
        // list where every entry fails all 4 match variants (plain/hyphen/comma/
        // space-stripped) leaves $styles['currentFont'] at its initial null,
        // hitting the earlier "No available font has been detected" branch -
        // not the later "not added to the document" branch a single bare name hits.
        $this->expectExceptionMessage('Error: No available font has been detected.');

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="none">a</p>', $doc);
        $parser->parseCss('.none { font-family: "Nope One", "Nope Two"; }');
        $parser->process();
    }

    public function testFontFamilyListFallsBackAfterFirstEntryMatchesNoVariant()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $doc->addFont(new Document\Font('TimesNewRoman'));
        $parser = Parser::parseString('<p class="mix">text</p>', $doc);
        $parser->parseCss('.mix { font-family: "Nope Font", "Times New Roman"; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testUnregisteredFontMatchesStandardFontsOnlyAfterHyphenSubstitution()
    {
        // "Times Roman" (space) isn't itself a registered font or a plain
        // standardFonts entry - it only resolves once spaces are replaced
        // with hyphens, matching the standard font "Times-Roman".
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="tr">text</p>', $doc);
        $parser->parseCss('.tr { font-family: "Times Roman"; }');
        $parser->process();

        $this->assertTrue($doc->hasFont('Times-Roman'));
    }

    public function testUnregisteredFontMatchesStandardFontsOnlyAfterCommaSubstitution()
    {
        // "TimesNewRoman Bold" (space) only resolves once the space is
        // replaced with a comma, matching the standard font "TimesNewRoman,Bold".
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="tnrb">text</p>', $doc);
        $parser->parseCss('.tnrb { font-family: "TimesNewRoman Bold"; }');
        $parser->process();

        $this->assertTrue($doc->hasFont('TimesNewRoman,Bold'));
    }

    public function testBoldResolvesToHyphenSuffixWhenFontIsAlreadyRegistered()
    {
        // Distinct from testUnregisteredStandardFontIsAutoAddedAndBoldVariantResolvesToHyphenSuffix:
        // pre-registering both "Courier" and "Courier-Bold" up front means bold
        // resolution succeeds in the FIRST resolution block (font already on the
        // document), never reaching the second block's auto-add fallback.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $doc->addFont(new Document\Font('Courier'));
        $doc->addFont(new Document\Font('Courier-Bold'));
        $parser = Parser::parseString('<p class="cb">text</p>', $doc);
        $parser->parseCss('.cb { font-family: Courier; font-weight: bold; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testTagNameSelectorAppliesFullStyleCascade()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p>text</p>', $doc);
        $parser->parseCss(
            'p { font-family: Arial; color: #ff0000; float: left; width: 100px; height: 50px; ' .
            'line-height: 20px; margin-top: 5px; padding-top: 5px; margin-right: 5px; padding-right: 5px; ' .
            'margin-bottom: 5px; padding-bottom: 5px; margin-left: 5px; padding-left: 5px; text-align: center; ' .
            'border-width: 2px; border-color: #000000; background-color: #cccccc; }'
        );
        $parser->process();

        $this->assertCount(2, $doc->getPage(1)->getPaths());
    }

    public function testFontFamilyWithSpacesMatchesRegisteredFontOnlyAfterStrippingSpaces()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $doc->addFont(new Document\Font('TimesNewRoman'));
        $parser = Parser::parseString('<p class="tnr">text</p>', $doc);
        $parser->parseCss('.tnr { font-family: "Times New Roman"; }');
        $parser->process();

        $this->assertTrue($doc->getPage(1)->hasTextStreams());
    }

    public function testUnregisteredStandardFontIsAutoAddedAndBoldVariantResolvesToHyphenSuffix()
    {
        // 'Courier' isn't in createDefaultStyles()'s auto-registered set (only
        // Arial/TimesNewRoman are), so requesting it exercises the fallback
        // that adds a standard font on demand - and PDF's standard fonts
        // register bold as "Courier-Bold" (hyphen), not "CourierBold" or
        // "Courier,Bold", so this also exercises that specific suffix match.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="c">text</p>', $doc);
        $parser->parseCss('.c { font-family: Courier; font-weight: bold; }');
        $parser->process();

        $this->assertTrue($doc->hasFont('Courier'));
        $this->assertTrue($doc->hasFont('Courier-Bold'));
    }

    public function testCourierNewFontFamilyCompilesEndToEnd()
    {
        // CourierNew is a valid Document\Font::standardFonts() entry, but
        // until Build\Font\Standard\CourierNew* existed this crashed with
        // "That standard font class was not found" the moment the compiler
        // tried to measure the text width - not at font registration, at
        // compile time. Compile all the way to bytes to prove the whole
        // pipeline works, not just font resolution.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $parser = Parser::parseString('<p class="cn">text</p>', $doc);
        $parser->parseCss('.cn { font-family: CourierNew; font-weight: bold; }');
        $parser->process();

        $this->assertTrue($doc->hasFont('CourierNew,Bold'));

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($doc);
        $bytes = $compiler->getOutput();

        $this->assertStringContainsString('/BaseFont /CourierNew,Bold', $bytes);
    }

    public function testBackgroundColorWithoutBorderOnMultiLineTextDrawsAFilledBox()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $longText = str_repeat('word ', 60);
        $parser = Parser::parseString('<div class="bg">' . $longText . '</div>', $doc);
        $parser->parseCss('.bg { background-color: #eeeeee; }');
        $parser->process();

        $this->assertCount(1, $doc->getPage(1)->getPaths());
    }

    public function testManySiblingParagraphsOverflowOntoNewPagesBetweenNodes()
    {
        // Distinct from the single-node orphan-splitting tests: this overflows
        // BETWEEN separate top-level nodes (each paragraph fits on its own),
        // so the new page is created by getCurrentY()'s own overflow check
        // and resetY() - not by addNodeToDocument()'s orphan while-loop.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = str_repeat('<p>short paragraph of text</p>', 10);
        $parser = Parser::parseString($html, $doc);
        $parser->setPageSize(200, 200);
        $parser->process();

        $this->assertGreaterThan(1, $doc->getNumberOfPages());
    }

    public function testSetPageSizeWithWidthAndHeightUsesACustomArrayPageSize()
    {
        // setPageSize($width, $height) stores ['width' => ..., 'height' => ...]
        // on $this->pageSize - previously typed as plain `string`, so this
        // 2-argument form fatally errored (TypeError: Cannot assign array to
        // property of type string) the instant it was called.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $longText = str_repeat('word ', 400);
        $parser = Parser::parseString('<p>' . $longText . '</p>', $doc);
        $parser->setPageSize(300, 300);
        $parser->process();

        $this->assertEquals(['width' => 300, 'height' => 300], $parser->getPageSize());
        $this->assertGreaterThan(1, $doc->getNumberOfPages());
    }

}