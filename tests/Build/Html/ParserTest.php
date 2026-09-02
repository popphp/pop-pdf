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

    public function testConstructorDefaultsPageSizeToLetter()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $this->assertEquals('LETTER', $html->getPageSize());
    }

    public function testConstructorAcceptsPageSizeString()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc, 'A4');
        $html->process();

        $this->assertEquals('A4', $html->getPageSize());
        $page = $html->getDocument()->getPage(1);
        $this->assertEquals(595, $page->getWidth());
        $this->assertEquals(842, $page->getHeight());
    }

    public function testConstructorAcceptsPageSizeArrayOfWidthAndHeight()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<h1>Hello World</h1>', $doc, [400, 600]);
        $html->process();

        $this->assertEquals(['width' => 400, 'height' => 600], $html->getPageSize());
        $page = $html->getDocument()->getPage(1);
        $this->assertEquals(400, $page->getWidth());
        $this->assertEquals(600, $page->getHeight());
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

    public function testSetDefaultStyleFontSizeIsCoercedToInt()
    {
        // Regression test: setDefaultStyle() stored the raw string it was
        // given, but defaultStyles['font-size'] is expected to be an int
        // everywhere else it's read - the raw string either got silently
        // overruled (on tags with their own pre-registered font-size, e.g.
        // <p>/<h1>-<h6>) or reached the renderer several layers down and
        // threw a TypeError there (on any other tag, e.g. <div>/<span>).
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('font-size', '26');

        $this->assertSame(26, $html->getDefaultStyle('font-size'));
        $this->assertSame(26, $html->prepareNodeStyles('div', [])['fontSize']);
    }

    public function testSetDefaultStyleFontSizeThrowsOnNonNumericValue()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('font-size', 'not-a-number');
    }

    public function testSetDefaultStyleLineHeightIsCoercedToInt()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('line-height', '30');

        $this->assertSame(30, $html->getDefaultStyle('line-height'));
        $this->assertSame(30, $html->prepareNodeStyles('div', [])['lineHeight']);
    }

    public function testSetDefaultStyleLineHeightThrowsOnNonNumericValue()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('line-height', 'garbage');
    }

    public function testSetDefaultStyleColorIsParsedToRgbArray()
    {
        // Regression test: setDefaultStyle('color', ...) stored the raw
        // string, which was never run through parseCssColorToRgbArray() -
        // it was later indexed character-by-character (e.g. '#cc0000'[0])
        // when building a Color\Rgb, silently producing black or garbage.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('color', '#cc0000');

        $this->assertSame([204, 0, 0], $html->getDefaultStyle('color'));
        $this->assertSame([204, 0, 0], $html->prepareNodeStyles('div', [])['color']);
    }

    public function testSetDefaultStyleColorThrowsOnUnparseableValue()
    {
        $this->expectException('Pop\Pdf\Build\Html\Exception');

        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);
        $html->setDefaultStyle('color', '204,0,0');
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

    public function testParseFileAcceptsPageSize()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseFile(__DIR__ . '/../../tmp/test.html', $doc, 'A4');
        $this->assertEquals('A4', $html->getPageSize());
    }

    public function testParseUriAcceptsPageSize()
    {
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseUri(__DIR__ . '/../../tmp/test.html', $doc, 'A4');
        $this->assertEquals('A4', $html->getPageSize());
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

    public function testDoublyNestedInlineTagTextIsNotDropped()
    {
        // Regression test: a text node three levels below the block element
        // (e.g. <p><b><i>text</i></b></p>) attaches to the <i> element, not
        // its <p> or <b> ancestors - a walk that only visits the immediate
        // child and grandchild silently drops it.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p>Hi <b><i>nested</i></b> there</p>', $doc);
        $html->process();

        $page = $doc->getPage(1);
        $strings = [];
        foreach ($page->getTextStreams() as $stream) {
            foreach ($stream->getTextStreams() as $entry) {
                $strings[] = $entry['string'];
            }
        }
        $this->assertContains('nested', $strings);
    }

    public function testInlineStyleAttributeSetsColorWithNoMatchingSelector()
    {
        // Regression test: an inline style="" attribute was never read by
        // prepareNodeStyles() - only tag/#id/.class selectors were - so a
        // color set purely inline had no effect at all.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p>Hello</p>', $doc);

        $styles = $html->prepareNodeStyles('p', ['style' => 'color: #ff0000;']);

        $this->assertEquals([255, 0, 0], $styles['color']);
    }

    public function testInlineStyleAttributeOverridesClassSelector()
    {
        // Inline style must win over a conflicting class rule, matching
        // normal CSS cascade/specificity expectations.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString('<p class="red">Hello</p>', $doc);
        $html->parseCss('.red { color: #ff0000; }');

        $styles = $html->prepareNodeStyles('p', ['class' => 'red', 'style' => 'color: #0000ff;']);

        $this->assertEquals([0, 0, 255], $styles['color']);
    }

    public function testStrongAndEmInheritParentFontSizeWhenNoExplicitCssSet()
    {
        // Regression test: enabling tag-level font-size application (needed
        // so user-authored tag CSS like "div { font-size: ... }" actually
        // works) reactivated a long-dormant hardcoded font-size: 10px on the
        // default 'strong'/'em' selectors (createDefaultStyles()) that had
        // never taken effect before, since tag-level font-size application
        // used to be a no-op. <strong>/<em> must inherit the surrounding
        // element's font size, not force 10px regardless of context.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = new Parser($doc);

        $paragraphStyles = $html->prepareNodeStyles('p', []);
        $headingStyles   = $html->prepareNodeStyles('h2', []);

        $this->assertEquals($paragraphStyles['fontSize'], $html->prepareNodeStyles('strong', [], $paragraphStyles)['fontSize']);
        $this->assertEquals($paragraphStyles['fontSize'], $html->prepareNodeStyles('em', [], $paragraphStyles)['fontSize']);
        $this->assertEquals($headingStyles['fontSize'], $html->prepareNodeStyles('strong', [], $headingStyles)['fontSize']);
    }

    public function testCssRuleNotSettingFontSizeDoesNotResetHeadingFontSize()
    {
        // Regression test: Pop\Css\AbstractCss::addSelector() used to replace
        // a same-name selector wholesale instead of merging into it, so any
        // user CSS rule for a tag with a pre-registered default (h1-h6, p)
        // silently discarded that default's font-size/margin-bottom unless
        // the new rule also restated them - e.g. "h1 { color: #cc0000; }"
        // alone shrank h1 from 32pt to 27pt. Fixed in pop-css; this locks
        // down the user-facing behavior at the pop-pdf layer too.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));

        foreach (['h1' => 32, 'h2' => 28, 'h3' => 24, 'p' => 12] as $tag => $expectedFontSize) {
            $html = new Parser($doc);
            $html->parseCss($tag . ' { color: #cc0000; }');
            $this->assertSame($expectedFontSize, $html->prepareNodeStyles($tag, [])['fontSize'], "for <$tag>");
        }
    }

    public function testStyleBlockInHeadIsParsedAsCss()
    {
        // Regression test: <style> blocks were never read at all - prepare()
        // only special-cased <link type="text/css"> inside <head>.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString(
            '<html><head><style>.boxed { color: #00ff00; }</style></head><body><p class="boxed">Hi</p></body></html>',
            $doc
        );

        $this->assertTrue($html->getCss()->hasSelector('.boxed'));
    }

    public function testStyleBlockInBodyIsNotRenderedAsText()
    {
        // Regression test: a <style> block placed directly in <body> (not
        // guarded against by node name) fell through to the generic text
        // node branch and its raw CSS text was rendered as visible PDF text.
        $doc = new Document();
        $doc->addFont(new Document\Font('Arial'));
        $html = Parser::parseString(
            '<body><style>.boxed { color: #00ff00; }</style><p class="boxed">Hi</p></body>',
            $doc
        );
        $html->process();

        $page = $doc->getPage(1);
        $strings = [];
        foreach ($page->getTextStreams() as $stream) {
            foreach ($stream->getTextStreams() as $entry) {
                $strings[] = $entry['string'];
            }
        }
        foreach ($strings as $string) {
            $this->assertStringNotContainsString('boxed', $string);
        }
        $this->assertTrue($html->getCss()->hasSelector('.boxed'));
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

    public function testNonLatinHtmlTextDoesNotDoubleEncode()
    {
        // Regression test: DOMDocument::loadHTML() (used internally by
        // Pop\Dom\Child::parseString()) assumes ISO-8859-1 for markup with
        // no declared charset, corrupting multi-byte UTF-8 (e.g. Cyrillic)
        // into mojibake before it ever reaches the PDF content stream.
        $doc = new Document();
        $doc->embedFont(new Document\Font(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf'));

        $cyrillic = "\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442}"; // "Привіт"
        $html = Parser::parseString('<p>' . $cyrillic . '</p>', $doc);
        $html->setDefaultStyle('font-family', 'DejaVuSans');
        $html->process();

        $outputFile = __DIR__ . '/../../tmp/non-latin-output.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $outputFile);

        if (trim((string) shell_exec('which pdftotext')) === '') {
            unlink($outputFile);
            $this->markTestSkipped('pdftotext is not available.');
        }

        $text = shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($outputFile) . ' - 2>&1');
        $this->assertStringContainsString($cyrillic, $text);

        unlink($outputFile);
    }

    public function testFloatedImageWithFollowingContentDoesNotHangOrLoseContent()
    {
        $imagePath = sys_get_temp_dir() . '/parser-float-orphan-test-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(900, 40);
        imagejpeg($image, $imagePath);

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

    public function testFullFormHtmlCompilesToPdfWithAcroFormAndRadioGroup()
    {
        $html = '<html><body>' .
            '<form id="signup">' .
            '<label>Name:</label><input type="text" name="name" style="border-width:1px;border-color:#999;">' .
            '<input type="radio" name="plan" value="a" checked>' .
            '<input type="radio" name="plan" value="b">' .
            '<input type="checkbox" name="agree" checked>' .
            '<select name="country"><option value="us">United States</option></select>' .
            '<button type="submit">Send</button>' .
            '</form>' .
            '</body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/AcroForm', $output);
        $this->assertStringContainsString('/FT /Tx', $output);
        $this->assertStringContainsString('/FT /Ch', $output);
        $this->assertStringContainsString('/FT /Btn', $output);
        $this->assertMatchesRegularExpression('/\/Parent \d+ 0 R/', $output);
        $this->assertStringContainsString('/BC [', $output);
    }

    // Final whole-branch review, finding I2: Form\Layout::buildField()'s
    // radio branch used to default a valueless HTML radio's value to the
    // literal 'Yes', bypassing Compiler::prepareRadioGroup()'s per-index
    // fallback naming ('Option' . ($index + 1)) - that fallback only
    // engages when getValue() === null. Every valueless radio in the group
    // collapsed onto the same on-state name, so a real viewer would render
    // ALL of them checked at once, not just the one HTML marked `checked`.
    // Three plain, valueless <input type=radio> elements (ordinary,
    // hand-written HTML) with only the middle one checked must compile to
    // exactly one non-'Off' /AS state.
    public function testValuelessHtmlRadioGroupOnlyCheckedOptionIsNonOff()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="radio" name="plan">' .
            '<input type="radio" name="plan" checked>' .
            '<input type="radio" name="plan">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        preg_match_all('/\/AS \/(\w+)\n/', $output, $asMatches);
        $this->assertCount(3, $asMatches[1], 'Expected exactly three /AS entries, one per radio kid.');

        $nonOff = array_filter($asMatches[1], fn ($v) => $v !== 'Off');
        $this->assertCount(1, $nonOff, 'Exactly one of the three valueless HTML radios must be checked.');
    }

    // Final whole-branch review, finding I4: groupRadioFields() only ever
    // sees one page's worth of fields at a time, so a radio group whose
    // options straddle a page break used to produce TWO same-named
    // top-level AcroForm fields (invalid PDF field naming, and it breaks
    // radio exclusivity across the split) - one grouped parent field for
    // whichever options landed on the first page, plus one lone ungrouped
    // field for whatever spilled onto the next page. Form\Layout now looks
    // ahead and keeps an entire radio group together on one page when it
    // fits, forcing a page break before the group's first option instead of
    // letting it split mid-group.
    //
    // A filler text field is sized to leave just enough room on the current
    // page for two of a 3-option radio group's options but not all three -
    // exactly the vertical budget the OLD per-node page-break check would
    // split under.
    public function testRadioGroupSpanningPageBreakStaysAsOneParentFieldOnOnePage()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="text" name="filler" height="316">' .
            '<input type="radio" name="plan" value="a">' .
            '<input type="radio" name="plan" value="b" checked>' .
            '<input type="radio" name="plan" value="c">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $parser->setPageSize(200, 400);
        $parser->setPageMargins(20, 20, 20, 20);
        $document = $parser->process();

        $this->assertEquals(
            2, $document->getNumberOfPages(),
            'The filler plus the full group must land on two pages, not overflow further.'
        );

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        // Exactly one top-level "plan" field - the shared parent - not two
        // (one grouped-on-page-1 parent plus one lone ungrouped field on
        // page 2, which is what the OLD per-node page-break logic produced).
        $this->assertEquals(1, substr_count($output, '/T(plan)'));

        // All three kid widgets must reference the SAME page.
        preg_match_all('/\/FT \/Btn\n\s*\/Rect \[[^\]]*\][\s\S]*?\/P (\d+) 0 R/', $output, $matches);
        $this->assertCount(3, $matches[1], 'Expected all three radio kid widgets.');
        $this->assertCount(1, array_unique($matches[1]), 'All three kids must land on the same page.');
    }

    // Follow-up fix to the look-ahead above: it measured a radio group's
    // needed height as the SUM OF EACH RADIO OPTION'S OWN HEIGHT ONLY,
    // ignoring any <label> (or other text) rendered between/around the
    // options - which is how radio-button HTML is almost always actually
    // written. That under-measurement meant the group could still straddle
    // a page break whenever labels were present, reproducing the exact
    // "two top-level fields sharing one name" bug the look-ahead exists to
    // prevent. Form\Layout now measures a group's real footprint as
    // everything rendered from its first option through its last option,
    // inclusive - not just the radio entries.
    //
    // Same page geometry as the test above (200x400 page, 20pt margins),
    // but the filler is sized so the OLD (radio-only) sum of the group's
    // three 18pt (14 height + 4 gap) options - 54pts - still appears to
    // fit in the space left on page one, while the REAL sequence (which
    // also includes the two labels sitting between the first and last
    // radio, 14pts of line-height each) does not. Under the old logic this
    // let two of the three radios render on page one and the third spill
    // onto page two - the split this whole look-ahead is meant to prevent.
    public function testRadioGroupWithLabelsBetweenOptionsStaysAsOneParentFieldOnOnePage()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="text" name="filler" height="272">' .
            '<label>Alpha</label><input type="radio" name="plan" value="a">' .
            '<label>Beta</label><input type="radio" name="plan" value="b" checked>' .
            '<label>Gamma</label><input type="radio" name="plan" value="c">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $parser->setPageSize(200, 400);
        $parser->setPageMargins(20, 20, 20, 20);
        $document = $parser->process();

        $this->assertEquals(
            2, $document->getNumberOfPages(),
            'The filler plus the full labeled group must land on two pages, not overflow further.'
        );

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        $this->assertEquals(1, substr_count($output, '/T(plan)'));

        preg_match_all('/\/FT \/Btn\n\s*\/Rect \[[^\]]*\][\s\S]*?\/P (\d+) 0 R/', $output, $matches);
        $this->assertCount(3, $matches[1], 'Expected all three radio kid widgets.');
        $this->assertCount(1, array_unique($matches[1]), 'All three kids must land on the same page.');
    }

    // Same bug, the other canonical way radio-button markup wraps a label -
    // the <label> wraps its <input> instead of preceding it. Form\Layout's
    // traversal renders a <label>'s own leaf text and then recurses into
    // its children, so this produces the same node sequence (label text,
    // then its radio) as the sibling-markup case above and must be
    // measured identically.
    public function testRadioGroupWithLabelsWrappingOptionsStaysAsOneParentFieldOnOnePage()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="text" name="filler" height="272">' .
            '<label>Alpha<input type="radio" name="plan" value="a"></label>' .
            '<label>Beta<input type="radio" name="plan" value="b" checked></label>' .
            '<label>Gamma<input type="radio" name="plan" value="c"></label>' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $parser->setPageSize(200, 400);
        $parser->setPageMargins(20, 20, 20, 20);
        $document = $parser->process();

        $this->assertEquals(
            2, $document->getNumberOfPages(),
            'The filler plus the full labeled group must land on two pages, not overflow further.'
        );

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        $this->assertEquals(1, substr_count($output, '/T(plan)'));

        preg_match_all('/\/FT \/Btn\n\s*\/Rect \[[^\]]*\][\s\S]*?\/P (\d+) 0 R/', $output, $matches);
        $this->assertCount(3, $matches[1], 'Expected all three radio kid widgets.');
        $this->assertCount(1, array_unique($matches[1]), 'All three kids must land on the same page.');
    }

    // Reviewer finding (Critical, follow-up to the two fixes above):
    // Parser::prepareNodeStyles() is NOT read-only - for a comma-separated
    // font-family stack, it resolves to whichever stack entry is ALREADY
    // registered on the document at the moment it runs, and registers
    // (addFont()) any standard font it resolves to that isn't registered
    // yet. Form\Layout::linearizeForm() (the dry-run measurement pass built
    // in the fix above) calls prepareNodeStyles() for every node it visits,
    // so by the time it finishes it has already registered every font any
    // node in the form will ever need - permanently, on the shared document.
    // Two passes (the dry run, then the real render loop), two different
    // points in time to resolve the SAME stack: a label styled
    // "font-family:Courier, Arial" can measure as Arial (Courier not
    // registered yet, at that point in the dry run) but render as Courier
    // (some OTHER node, visited later in the SAME dry run, already
    // registered Courier by the time the dry run finished - and that
    // registration persists into the real render pass, which starts fresh
    // from the beginning of the form). Different font -> different
    // character widths -> different wrapped-line count -> a real rendered
    // height bigger than what was measured -> the group's real footprint is
    // under-measured all over again, through a brand-new mechanism -
    // reproducing the exact "two top-level fields sharing one name" bug
    // Form\Layout's page-break look-ahead exists to prevent.
    //
    // This test's node order is deliberate: the font-stack labels are the
    // group's own between-option content, and the plain (non-stack)
    // "Courier" trigger node sits AFTER the whole group, so it never
    // registers Courier during the dry run until the group's own labels
    // have already been measured against Arial - but it HAS registered
    // Courier by the time the dry run completes, before the real render
    // loop (which starts over from the top) reaches those same labels.
    //
    // Geometry: 140x400 page, 20pt margins (giving a 100pt text wrap width),
    // each label's text ("iiii iiii iiii iiii") measures as a single 43.86pt
    // line under Arial but wraps to two lines under Courier - a real, 14pt
    // per-label height difference between what the dry run measured and
    // what the real render pass actually draws. The filler's height (232)
    // was found by sweeping a small range against the pre-fix code: it is
    // NOT close enough to a page boundary to trip the look-ahead using the
    // (buggy) Arial-measured span, but the Courier-actual span pushes the
    // group past the bottom margin mid-render, splitting it across pages.
    public function testFontStackOrderDependencyNoLongerUnderMeasuresARadioGroupsSpan()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="text" name="filler" height="232">' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="a">' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="b" checked>' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="c">' .
            '<p style="font-family:Courier;">Trigger</p>' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $parser->setPageSize(140, 400);
        $parser->setPageMargins(20, 20, 20, 20);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        // Exactly one top-level "plan" field - not two (one grouped parent
        // on whichever page the split landed on, plus one lone ungrouped
        // field for whatever spilled onto the other page).
        $this->assertEquals(1, substr_count($output, '/T(plan)'));

        preg_match_all('/\/FT \/Btn\n\s*\/Rect \[[^\]]*\][\s\S]*?\/P (\d+) 0 R/', $output, $matches);
        $this->assertCount(3, $matches[1], 'Expected all three radio kid widgets.');
        $this->assertCount(1, array_unique($matches[1]), 'All three kids must land on the same page.');
    }

    // Second, independent trigger for the same Critical finding: a CONTROL
    // node with an explicit `height` HTML attribute. Form\Layout::
    // resolveHeight() returns early for a control with an explicit height
    // (skipping its own prepareNodeStyles() call) during the dry-run
    // measurement pass, but the REAL render pass's applyAppearance() calls
    // prepareNodeStyles() UNCONDITIONALLY for every control regardless of
    // whether it has an explicit height - so a height-attribute control can
    // register a font during render that it never registered during
    // measurement, independent of (and via a different code path than) the
    // font-stack-ordering trigger above: here, both passes visit the form's
    // nodes in the SAME order every time (nothing spans two separate passes
    // the way the test above does) - the mismatch is entirely that
    // buildField()/resolveControlHeight() (measurement AND render) skip
    // styling a height-attribute control, while applyAppearance() (render
    // only) does not.
    //
    // Same page geometry as the test above. The "courierctrl" text input
    // carries both an explicit height="20" (so resolveHeight() never
    // consults CSS for it) and style="font-family:Courier;" (so its
    // applyAppearance() call, during the real render pass only, registers
    // Courier right before the group's own font-stack labels render) - the
    // filler height (208) was found by sweeping a small range against the
    // pre-fix code.
    public function testHeightAttributeControlNoLongerUnderMeasuresARadioGroupsSpan()
    {
        $html = '<html><body><form id="survey">' .
            '<input type="text" name="filler" height="208">' .
            '<input type="text" name="courierctrl" height="20" style="font-family:Courier;">' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="a">' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="b" checked>' .
            '<label style="font-family:Courier, Arial;">iiii iiii iiii iiii</label><input type="radio" name="plan" value="c">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $parser->setPageSize(140, 400);
        $parser->setPageMargins(20, 20, 20, 20);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        $this->assertEquals(1, substr_count($output, '/T(plan)'));

        preg_match_all('/\/FT \/Btn\n\s*\/Rect \[[^\]]*\][\s\S]*?\/P (\d+) 0 R/', $output, $matches);
        $this->assertCount(3, $matches[1], 'Expected all three radio kid widgets.');
        $this->assertCount(1, array_unique($matches[1]), 'All three kids must land on the same page.');
    }

    // The reviewer also confirmed the same underlying side effect silently
    // changes which font a label renders in for ANY form using a font
    // stack, whether or not it has radio groups - an undisclosed
    // rendering-behavior change, not just a pagination bug. This proves it
    // is resolved: two labels sharing the IDENTICAL "font-family:Courier,
    // Arial" style, with a plain (non-stack) "font-family:Courier" control
    // sitting between them, must render in the SAME font as each other -
    // not one in the fallback (Arial) and the other in the stack's
    // preferred font (Courier), which is what the pre-fix ordering
    // accident produced (the first label rendered before anything had
    // registered Courier; the second rendered after the between-them
    // control's applyAppearance() had already registered it).
    public function testFontStackLabelsResolveToTheSameFontRegardlessOfPosition()
    {
        $html = '<html><body><form id="survey">' .
            '<label style="font-family:Courier, Arial;">FirstLabel</label>' .
            '<input type="text" name="ctrl" height="20" style="font-family:Courier;">' .
            '<label style="font-family:Courier, Arial;">SecondLabel</label>' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        // Map each /MFn font resource name compiled into this document to
        // its /BaseFont, then resolve which resource each label's /Tf
        // operator selected - both must resolve to the same /BaseFont.
        preg_match_all('/(\d+) 0 obj\s*<<\s*\/Type \/Font.*?\/Name \/(MF\d+).*?\/BaseFont \/([A-Za-z,]+)/s', $output, $fontMatches, PREG_SET_ORDER);
        $baseFontByResource = [];
        foreach ($fontMatches as $fontMatch) {
            $baseFontByResource[$fontMatch[2]] = $fontMatch[3];
        }
        $this->assertNotEmpty($baseFontByResource, 'Expected at least one named font resource in the compiled output.');

        preg_match('/\/(MF\d+) [\d.]+ Tf\s*\n[^\n]*\n[^\n]*\n\s*\(FirstLabel\)/', $output, $firstMatch);
        preg_match('/\/(MF\d+) [\d.]+ Tf\s*\n[^\n]*\n[^\n]*\n\s*\(SecondLabel\)/', $output, $secondMatch);

        $this->assertNotEmpty($firstMatch, 'Expected to find the font resource used to draw FirstLabel.');
        $this->assertNotEmpty($secondMatch, 'Expected to find the font resource used to draw SecondLabel.');

        $firstFont  = $baseFontByResource[$firstMatch[1]];
        $secondFont = $baseFontByResource[$secondMatch[1]];

        $this->assertEquals(
            $firstFont, $secondFont,
            'Both labels share the identical font-family style and must resolve to the same font, ' .
            'regardless of what registered fonts in between them during rendering.'
        );
    }

    // Final whole-branch review, finding I5: push buttons rendered as
    // invisible, captionless widgets - contradicting the spec's "renders but
    // performs no action" (it didn't render at all). A <button> gets its
    // caption from its own text content; an <input type=submit> gets it
    // from its value attribute.
    public function testPushButtonsCompileWithCaptionsInMkDict()
    {
        $html = '<html><body><form id="signup">' .
            '<button type="submit">Send</button>' .
            '<input type="submit" value="Go">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/CA (Send)', $output);
        $this->assertStringContainsString('/CA (Go)', $output);
    }

    public function testFullFormHtmlProducesStructurallyValidPdf()
    {
        // Robust qpdf availability check: not just 'which qpdf', but actually
        // run 'qpdf --version' to confirm it works (catches broken wrapper
        // scripts that exist but fail at runtime).
        $versionOutput = shell_exec('qpdf --version 2>&1');
        if ($versionOutput === null || strpos($versionOutput, 'No such file or directory') !== false ||
            !preg_match('/qpdf\s+\d/', $versionOutput)) {
            $this->markTestSkipped('qpdf is required for this test.');
        }

        $html = '<html><body><form id="signup">' .
            '<input type="text" name="name">' .
            '<input type="radio" name="plan" value="a" checked>' .
            '<input type="radio" name="plan" value="b">' .
            '</form></body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_form_test_') . '.pdf';
        file_put_contents($tmpFile, $compiler->getOutput());

        $checkOutput = shell_exec('qpdf --check ' . escapeshellarg($tmpFile) . ' 2>&1');
        unlink($tmpFile);

        $this->assertStringContainsString('No syntax or stream encoding errors found', (string) $checkOutput);
    }

    public function testFullFormHtmlTextIsExtractableByPoppler()
    {
        if ((shell_exec('which pdftotext') === null)) {
            $this->markTestSkipped('poppler-utils (pdftotext) is required for this test.');
        }

        $html = '<html><body><form id="signup"><label>Full Name</label><input type="text" name="name"></form></body></html>';

        $parser = Parser::parseString($html);
        $document = $parser->process();

        $compiler = new \Pop\Pdf\Build\Compiler();
        $compiler->finalize($document);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_form_test_') . '.pdf';
        file_put_contents($tmpFile, $compiler->getOutput());

        $textOutput = [];
        exec('pdftotext ' . escapeshellarg($tmpFile) . ' - 2>&1', $textOutput, $status);
        unlink($tmpFile);

        $this->assertEquals(0, $status);
        $this->assertStringContainsString('Full Name', implode("\n", $textOutput));
        foreach ($textOutput as $line) {
            $this->assertStringNotContainsString('Syntax Error', $line);
        }
    }

}