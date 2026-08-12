<?php

namespace Pop\Pdf\Test\Build\Html\Table;

use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Parser;
use Pop\Pdf\Build\Html\Table\Grid;
use Pop\Pdf\Build\Html\Table\Layout;
use Pop\Pdf\Document;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase
{

    /**
     * Child::parseString() returns an array of top-level nodes when the
     * given string doesn't contain <html>/<body> wrapper tags (as is the
     * case for every bare <table>...</table> fixture below), rather than a
     * single Child. Unwrap it here so Layout::render(), which is correctly
     * typed to accept only a Child, gets a Child. (Same fact discovered
     * during Task 3's GridTest - see that file's identical helper.)
     */
    protected function parseTable(string $html): Child
    {
        $parsed = Child::parseString($html);
        return is_array($parsed) ? $parsed[0] : $parsed;
    }

    protected function makeParser(): Parser
    {
        $doc = new Document();
        return new Parser($doc);
    }

    protected function baseStyles(Parser $parser): array
    {
        return $parser->prepareNodeStyles('table');
    }

    /**
     * Invoke one of Layout's protected static helpers directly - used only
     * for the couple of guard clauses (an empty-grid short-circuit, a
     * blank/percentage width parse) that Grid's own invariants make
     * unreachable through Layout::render()'s public entry point.
     */
    protected function callLayoutStatic(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(Layout::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }

    public function testRendersATextCellOnThePage()
    {
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table><tr><td>Hello</td></tr></table>');
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page = $parser->getDocument()->getPage(1);
        $this->assertTrue($page->hasText());
    }

    public function testColumnWidthsRespectExplicitCssWidth()
    {
        $parser = $this->makeParser();
        $parser->parseCss('.wide { width: 300px; }');
        $table  = $this->parseTable(
            '<table><tr><td class="wide">A</td><td>B</td></tr></table>'
        );
        $styles = $this->baseStyles($parser);

        // Rendering must not throw and must produce two distinctly-x-positioned
        // text runs (the exact widths are covered by ColumnWidth-focused
        // assertions in the integration test in Task 6 - this test's job is
        // just to confirm the explicit-width path doesn't crash and DOES
        // differentiate column 1 from column 0 meaningfully).
        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page  = $parser->getDocument()->getPage(1);
        $texts = $page->getText();
        $this->assertCount(2, $texts);
        $this->assertNotEquals($texts[0]['x'], $texts[1]['x']);
    }

    public function testLongTableSpansMultiplePagesWithRepeatedHeader()
    {
        $parser = $this->makeParser();

        $rows = '<tr><th>Col</th></tr>';
        for ($i = 0; $i < 80; $i++) {
            $rows .= '<tr><td>Row ' . $i . '</td></tr>';
        }
        $table  = $this->parseTable('<table>' . $rows . '</table>');
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $doc = $parser->getDocument();
        $this->assertGreaterThan(1, $doc->getNumberOfPages());

        // The header text ("Col") must appear on more than one page.
        $pagesWithHeader = 0;
        for ($p = 1; $p <= $doc->getNumberOfPages(); $p++) {
            foreach ($doc->getPage($p)->getText() as $entry) {
                if ($entry['text']->getString() === 'Col') {
                    $pagesWithHeader++;
                    break;
                }
            }
        }
        $this->assertGreaterThan(1, $pagesWithHeader);
    }

    public function testEmptyTableRendersNothingWithoutError()
    {
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table></table>');
        $styles = $this->baseStyles($parser);

        // A dummy $startY is fine here - render() returns early for an
        // empty grid, before $startY (or getCurrentY(), which would create
        // a page as a side effect) is ever touched. Calling getCurrentY()
        // here instead would defeat the assertion below.
        Layout::render($parser, $table, $styles, 60, 400, 0.0);

        $this->assertFalse($parser->getDocument()->hasPages());
    }

    public function testColumnWidthsRespectHtmlWidthAttribute()
    {
        $parser = $this->makeParser();
        $table  = $this->parseTable(
            '<table><tr><td width="300">A</td><td>bb</td></tr></table>'
        );
        $styles = $this->baseStyles($parser);

        // No CSS width is set anywhere here - only the HTML `width` attribute
        // on the first <td>. Column 0 should get ~300pt (of the 400pt table
        // width), so the second cell's text should land at x ~= 360
        // (startX 60 + 300), not at the ~210 it would land at if the
        // attribute were ignored and the width were split naturally.
        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page  = $parser->getDocument()->getPage(1);
        $texts = $page->getText();
        $this->assertCount(2, $texts);
        $this->assertEquals(60, $texts[0]['x']);
        $this->assertEquals(360, $texts[1]['x']);
    }

    public function testRowspanCellDrawnBoxSpansCombinedRowHeight()
    {
        $parser = $this->makeParser();
        $parser->parseCss('td { border-width: 1px; border-color: #000000; }');
        $table  = $this->parseTable(
            '<table><tr><td rowspan="2">Tall</td><td>B1</td></tr><tr><td>B2</td></tr></table>'
        );
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page = $parser->getDocument()->getPage(1);

        $heights = [];
        foreach ($page->getPaths() as $path) {
            foreach ($path->getStreams() as $stream) {
                if (preg_match('/\{x\}\]\s*\[\{y\}\]\s*([\d.]+)\s+([\d.]+)\s+re/', $stream['stream'], $matches)) {
                    $heights[] = (float) $matches[2];
                }
            }
        }

        // Three cells drawn (rowspan "Tall", "B1", "B2"). The rowspan cell's
        // rectangle height must be double a non-spanning cell's height.
        $this->assertCount(3, $heights);
        $this->assertEquals($heights[1], $heights[2]);
        $this->assertEquals($heights[1] * 2, $heights[0]);
    }

    public function testDecimalNumberInCellDoesNotGetSpuriousSpaceAfterDecimalPoint()
    {
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table><tr><td>$19.99</td></tr></table>');
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page  = $parser->getDocument()->getPage(1);
        $texts = $page->getText();
        $this->assertCount(1, $texts);
        $this->assertEquals('$19.99', $texts[0]['text']->getString());
    }

    public function testRendersWhenNoPageHasBeenCreatedYet()
    {
        // The parser's own getCurrentY() creates a page as a side effect, so
        // for this test $startY is passed in raw (0.0) without calling it -
        // meaning Parser::getPage() is still null when render() starts.
        // That makes the page-height lookup in render() null too, which
        // exercises the "no usable-row-height cap" branch: a row that
        // wouldn't fit is still allowed to trigger a fresh page rather than
        // being compared against a real page height.
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table><tr><td>Hello</td></tr></table>');
        $styles = $this->baseStyles($parser);

        $this->assertFalse($parser->getDocument()->hasPages());

        Layout::render($parser, $table, $styles, 60, 400, 0.0);

        $page = $parser->getDocument()->getPage(1);
        $this->assertNotNull($page);
        $this->assertTrue($page->hasText());
    }

    public function testOversizedRowIsDrawnBestEffortAndHandsBackAFreshPage()
    {
        // A single row whose wrapped content is taller than any page's
        // usable height can never be made to fit by breaking - render()'s
        // own comment documents this as an accepted limitation, drawing it
        // in place instead. That pushes $currentY deep past the bottom
        // margin, so once the (only) row is done, render() must still hand
        // back a fresh page for whatever content comes after the table.
        $parser = $this->makeParser();
        $startY = $parser->getCurrentY();
        $table  = $this->parseTable('<table><tr><td>' . str_repeat('word ', 2000) . '</td></tr></table>');
        $styles = $this->baseStyles($parser);

        $pagesBefore = $parser->getDocument()->getNumberOfPages();

        Layout::render($parser, $table, $styles, 60, 400, $startY);

        $this->assertGreaterThan($pagesBefore, $parser->getDocument()->getNumberOfPages());
    }

    public function testEmptyCellGetsFullTableWidthAndDrawsNoText()
    {
        // A single column whose only cell is empty has zero natural width
        // (no text, no padding) - calculateColumnWidths()'s proportional
        // split divides zero by zero, so it must fall back to splitting the
        // remaining width evenly across the auto columns instead. drawRow()
        // must also skip creating a Text object for the empty cell (while
        // still drawing its box).
        $parser = $this->makeParser();
        $parser->parseCss('td { border-width: 1px; border-color: #000000; }');
        $table  = $this->parseTable('<table><tr><td></td></tr></table>');
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $page = $parser->getDocument()->getPage(1);
        $this->assertFalse($page->hasText());

        $widths = [];
        foreach ($page->getPaths() as $path) {
            foreach ($path->getStreams() as $stream) {
                if (preg_match('/\{x\}\]\s*\[\{y\}\]\s*([\d.]+)\s+([\d.]+)\s+re/', $stream['stream'], $matches)) {
                    $widths[] = (float) $matches[1];
                }
            }
        }
        $this->assertCount(1, $widths);
        $this->assertEquals(400.0, $widths[0]);
    }

    public function testBlankWidthAttributeIsTreatedAsNoExplicitWidth()
    {
        // A width="   " attribute is non-empty as a raw string, so it still
        // reaches resolveExplicitWidth(), but trims down to '' there - that
        // must resolve to null (no explicit width) rather than being parsed
        // as a numeric 0, so the column falls back to the natural/auto
        // split, landing "B" at the same x as if width had been omitted
        // entirely.
        $parser    = $this->makeParser();
        $table     = $this->parseTable('<table><tr><td width="   ">A</td><td>B</td></tr></table>');
        $styles    = $this->baseStyles($parser);
        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());
        $texts = $parser->getDocument()->getPage(1)->getText();

        $parser2 = $this->makeParser();
        $table2  = $this->parseTable('<table><tr><td>A</td><td>B</td></tr></table>');
        $styles2 = $this->baseStyles($parser2);
        Layout::render($parser2, $table2, $styles2, 60, 400, $parser2->getCurrentY());
        $texts2 = $parser2->getDocument()->getPage(1)->getText();

        $this->assertEquals($texts2[1]['x'], $texts[1]['x']);
    }

    public function testPercentageWidthAttributeIsResolvedAgainstTableWidth()
    {
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table><tr><td width="50%">A</td><td>B</td></tr></table>');
        $styles = $this->baseStyles($parser);

        Layout::render($parser, $table, $styles, 60, 400, $parser->getCurrentY());

        $texts = $parser->getDocument()->getPage(1)->getText();
        // 50% of a 400pt table is 200pt, so the second cell's text starts
        // at startX (60) + 200 = 260.
        $this->assertEquals(260, $texts[1]['x']);
    }

    public function testResolveExplicitWidthReturnsNullForBlankValue()
    {
        $result = $this->callLayoutStatic('resolveExplicitWidth', ['   ', 400]);
        $this->assertNull($result);
    }

    public function testResolveExplicitWidthResolvesPercentage()
    {
        $result = $this->callLayoutStatic('resolveExplicitWidth', ['50%', 400]);
        $this->assertEquals(200.0, $result);
    }

    public function testCalculateColumnWidthsReturnsEmptyArrayForAZeroColumnGrid()
    {
        // Grid's own invariants mean a real, parsed grid never has rows
        // without columns - this exercises calculateColumnWidths()'s own
        // guard clause directly, for the one grid shape (no rows at all)
        // where columnCount is genuinely 0.
        $parser = $this->makeParser();
        $table  = $this->parseTable('<table></table>');
        $grid   = Grid::build($table);

        $result = $this->callLayoutStatic('calculateColumnWidths', [$parser, $grid, 400, []]);
        $this->assertEquals([], $result);
    }

}
