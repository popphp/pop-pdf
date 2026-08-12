<?php

namespace Pop\Pdf\Test\Build\Html\Table;

use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Table\Grid;
use PHPUnit\Framework\TestCase;

class GridTest extends TestCase
{

    /**
     * Child::parseString() returns an array of top-level nodes when the
     * given string doesn't contain <html>/<body> wrapper tags (as is the
     * case for every bare <table>...</table> fixture below), rather than a
     * single Child. Unwrap it here so Grid::build(), which is correctly
     * typed to accept only a Child, gets a Child.
     */
    protected function parseTable(string $html): Child
    {
        $parsed = Child::parseString($html);
        return is_array($parsed) ? $parsed[0] : $parsed;
    }

    public function testUniformTableMapsOneToOne()
    {
        $table = $this->parseTable(
            '<table><tr><td>A1</td><td>B1</td></tr><tr><td>A2</td><td>B2</td></tr></table>'
        );
        $grid = Grid::build($table);

        $this->assertEquals(2, $grid->getColumnCount());
        $this->assertEquals(2, $grid->getRowCount());

        $rows = $grid->getRows();
        $this->assertCount(2, $rows[0]['cells']);
        $this->assertEquals('A1', $rows[0]['cells'][0]->getNode()->getNodeValue());
        $this->assertEquals(0, $rows[0]['cells'][0]->getCol());
        $this->assertEquals(1, $rows[0]['cells'][1]->getCol());
    }

    public function testTheadRowsAreFlaggedAsHeaders()
    {
        $table = $this->parseTable(
            '<table><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Row</td></tr></tbody></table>'
        );
        $grid = Grid::build($table);
        $rows = $grid->getRows();

        $this->assertTrue($rows[0]['isHeader']);
        $this->assertFalse($rows[1]['isHeader']);
    }

    public function testAllThRowWithoutTheadIsImplicitlyAHeader()
    {
        $table = $this->parseTable(
            '<table><tr><th>Name</th><th>Value</th></tr><tr><td>Row</td><td>1</td></tr></table>'
        );
        $grid = Grid::build($table);
        $rows = $grid->getRows();

        $this->assertTrue($rows[0]['isHeader']);
        $this->assertFalse($rows[1]['isHeader']);
    }

    public function testColspanClaimsMultipleColumnsInTheSameRow()
    {
        $table = $this->parseTable(
            '<table><tr><td colspan="2">Wide</td><td>C</td></tr><tr><td>A2</td><td>B2</td><td>C2</td></tr></table>'
        );
        $grid = Grid::build($table);

        $this->assertEquals(3, $grid->getColumnCount());
        $rows = $grid->getRows();
        $this->assertCount(2, $rows[0]['cells']);
        $this->assertEquals(0, $rows[0]['cells'][0]->getCol());
        $this->assertEquals(2, $rows[0]['cells'][0]->getColSpan());
        $this->assertEquals(2, $rows[0]['cells'][1]->getCol());
    }

    public function testRowspanClaimsSlotsInSubsequentRows()
    {
        $table = $this->parseTable(
            '<table>' .
            '<tr><td rowspan="2">Tall</td><td>B1</td></tr>' .
            '<tr><td>B2</td></tr>' .
            '</table>'
        );
        $grid = Grid::build($table);
        $rows = $grid->getRows();

        // Row 0 has both cells; row 1's single actual <td> ("B2") must land
        // at column 1, not 0, since column 0 is claimed by row 0's rowspan.
        $this->assertCount(2, $rows[0]['cells']);
        $this->assertCount(1, $rows[1]['cells']);
        $this->assertEquals(1, $rows[1]['cells'][0]->getCol());
        $this->assertEquals('B2', $rows[1]['cells'][0]->getNode()->getNodeValue());
    }

    public function testEmptyTableHasZeroRowsAndColumns()
    {
        $table = $this->parseTable('<table></table>');
        $grid  = Grid::build($table);

        $this->assertEquals(0, $grid->getRowCount());
        $this->assertEquals(0, $grid->getColumnCount());
        $this->assertEquals([], $grid->getRows());
    }

    public function testExtremeRowspanAndColspanProductDoesNotExhaustMemory()
    {
        $table = $this->parseTable('<table><tr><td rowspan="65534" colspan="1000">x</td></tr></table>');

        $start = microtime(true);
        $grid = Grid::build($table);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, 'Grid::build() should complete quickly, not exhaust memory');
        $this->assertEquals(1, $grid->getRowCount());
    }

    public function testNonCellChildOfATrIsSkipped()
    {
        // A <span> stray inside a <tr> (invalid HTML, but the parser doesn't
        // reject it) isn't 'td' or 'th', so the loop in Grid::parse() must
        // skip over it (the `continue` branch) rather than treating it as a
        // cell - only the real <td> should end up in the row.
        $table = $this->parseTable('<table><tr><span>x</span><td>A</td></tr></table>');
        $grid  = Grid::build($table);
        $rows  = $grid->getRows();

        $this->assertCount(1, $rows[0]['cells']);
        $this->assertEquals('A', $rows[0]['cells'][0]->getNode()->getNodeValue());
        $this->assertEquals(0, $rows[0]['cells'][0]->getCol());
    }

    public function testColspanLargerThanRowspanIsTheDimensionScaledDownForAnOversizedProduct()
    {
        // Mirrors testExtremeRowspanAndColspanProductDoesNotExhaustMemory, but
        // with colspan the larger of the two spans (rather than rowspan), to
        // exercise the other side of the product-capping branch in
        // Grid::parse() - colspan gets scaled down instead of rowspan.
        $table = $this->parseTable('<table><tr><td rowspan="20" colspan="1000">x</td></tr></table>');
        $grid  = Grid::build($table);
        $rows  = $grid->getRows();

        $cell = $rows[0]['cells'][0];
        $this->assertEquals(20, $cell->getRowSpan());
        $this->assertLessThanOrEqual(500, $cell->getColSpan());
        $this->assertLessThanOrEqual(10000, $cell->getRowSpan() * $cell->getColSpan());
    }

}
