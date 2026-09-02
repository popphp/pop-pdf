<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Html\Table;

use Pop\Color\Color;
use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Parser;
use Pop\Pdf\Document;

/**
 * Pdf HTML table layout class
 *
 * Two-pass table rendering: measures natural column widths from cell
 * content, distributes the available table width across columns, then lays
 * out rows top to bottom with per-row page-break checks and header-row
 * repeat on page break.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class Layout
{

    /**
     * Minimum row height
     */
    protected const MIN_ROW_HEIGHT = 20;

    /**
     * Render a <table> node onto the document
     *
     * @param  Parser $parser
     * @param  Child  $tableNode
     * @param  array  $styles
     * @param  int    $startX
     * @param  int    $tableWidth
     * @param  float  $startY
     * @return void
     */
    public static function render(Parser $parser, Child $tableNode, array $styles, int $startX, int $tableWidth, float $startY): void
    {
        $grid = Grid::build($tableNode);

        if ($grid->getRowCount() === 0) {
            return;
        }

        $columnWidths = self::calculateColumnWidths($parser, $grid, $tableWidth, $styles);
        $rows         = $grid->getRows();

        $rowHeights = [];
        foreach ($rows as $rowIndex => $row) {
            $rowHeights[$rowIndex] = self::measureRowHeight($parser, $row['cells'], $columnWidths, $styles);
        }

        $headerRows = [];
        foreach ($rows as $rowIndex => $row) {
            if ($row['isHeader']) {
                $headerRows[$rowIndex] = $row;
            }
        }

        $currentY   = $startY;
        $pageHeight = $parser->getPage()?->getHeight();
        $usableRowHeight = ($pageHeight !== null)
            ? ($pageHeight - $parser->getPageTopMargin() - $parser->getPageBottomMargin())
            : null;

        foreach ($rows as $rowIndex => $row) {
            $rowHeight = $rowHeights[$rowIndex];

            // A row taller than a full page's usable height can never fit
            // no matter how many times we break - breaking anyway would
            // just leave an empty page in front of it, so it's drawn
            // best-effort where we are instead (matches this project's
            // accepted no-cell/row-splitting-across-pages limitation).
            $fitsOnFreshPage = ($usableRowHeight === null) || ($rowHeight <= $usableRowHeight);

            if ((($currentY - $rowHeight) < $parser->getPageBottomMargin()) && $fitsOnFreshPage) {
                $currentY = $parser->newPage();

                if (!$row['isHeader']) {
                    foreach ($headerRows as $headerRowIndex => $headerRow) {
                        $headerHeight = $rowHeights[$headerRowIndex];
                        self::drawRow($parser, $headerRow['cells'], $columnWidths, $styles, $startX, $currentY, $rowHeights);
                        $currentY -= $headerHeight;
                    }
                }
            }

            self::drawRow($parser, $row['cells'], $columnWidths, $styles, $startX, $currentY, $rowHeights);
            $currentY -= $rowHeight;
        }

        $page = $parser->getPage();
        if ($page !== null) {
            // A best-effort oversized row above may have pushed $currentY
            // past the bottom margin - hand the next node a fresh page
            // rather than a Y position that would render it off-page and
            // effectively lose it.
            if ($currentY < $parser->getPageBottomMargin()) {
                $currentY = $parser->newPage();
                $page     = $parser->getPage();
            }

            $consumedY = ($page->getHeight() - $parser->getPageTopMargin()) - $currentY;
            $parser->setY((int) $consumedY);
            $parser->setYOverride((int) $currentY);
        }
    }

    /**
     * Calculate each column's width, distributing the table's available width
     *
     * @param  Parser $parser
     * @param  Grid   $grid
     * @param  int    $tableWidth
     * @param  array  $styles
     * @return array
     */
    protected static function calculateColumnWidths(Parser $parser, Grid $grid, int $tableWidth, array $styles): array
    {
        $columnCount = $grid->getColumnCount();
        if ($columnCount === 0) {
            return [];
        }

        $natural  = array_fill(0, $columnCount, 0.0);
        $explicit = array_fill(0, $columnCount, null);

        foreach ($grid->getRows() as $row) {
            foreach ($row['cells'] as $cell) {
                $cellStyles = self::cellStyles($parser, $cell, $styles);
                $fontObject = $parser->getDocument()->getFont($cellStyles['currentFont']);
                $text       = self::cellText($cell);
                $width      = ($text !== '') ? $fontObject->getStringWidth($text, $cellStyles['fontSize']) : 0;
                $width     += $cellStyles['paddingLeft'] + $cellStyles['paddingRight'];

                $perColumn = $width / $cell->getColSpan();
                for ($c = $cell->getCol(); $c < ($cell->getCol() + $cell->getColSpan()); $c++) {
                    if ($c < $columnCount) {
                        $natural[$c] = max($natural[$c], $perColumn);
                    }
                }

                if ($cell->getColSpan() === 1) {
                    $widthValue = !empty($cellStyles['width']) ? (string) $cellStyles['width'] : $cell->getNode()->getAttribute('width');
                    if (!empty($widthValue)) {
                        $resolved = self::resolveExplicitWidth((string) $widthValue, $tableWidth);
                        if ($resolved !== null) {
                            $explicit[$cell->getCol()] = max($explicit[$cell->getCol()] ?? 0.0, $resolved);
                        }
                    }
                }
            }
        }

        $explicitTotal    = 0.0;
        $naturalRemaining = 0.0;
        $autoColumnCount  = 0;

        foreach ($natural as $i => $w) {
            if ($explicit[$i] !== null) {
                $explicitTotal += $explicit[$i];
            } else {
                $naturalRemaining += $w;
                $autoColumnCount++;
            }
        }

        // If the explicit-width columns alone already exceed the table's
        // available width, scale them all down proportionally to fit -
        // otherwise a column renders past the page's right edge, and every
        // auto column collapses to 0 width.
        $explicitScale = (($explicitTotal > $tableWidth) && ($explicitTotal > 0))
            ? ($tableWidth / $explicitTotal) : 1.0;

        $remainingWidth = max(0.0, $tableWidth - min($explicitTotal, $tableWidth));

        $widths = [];
        foreach ($natural as $i => $w) {
            if ($explicit[$i] !== null) {
                $widths[$i] = $explicit[$i] * $explicitScale;
            } else if ($naturalRemaining > 0) {
                $widths[$i] = ($w / $naturalRemaining) * $remainingWidth;
            } else {
                $widths[$i] = $remainingWidth / max(1, $autoColumnCount);
            }
        }

        return $widths;
    }

    /**
     * Resolve a CSS width value (px or %) against the table's available width
     *
     * @param  string $value
     * @param  int    $tableWidth
     * @return ?float
     */
    protected static function resolveExplicitWidth(string $value, int $tableWidth): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_ends_with($value, '%')) {
            return $tableWidth * ((float) rtrim($value, '%') / 100);
        }

        $numeric = (float) $value;
        return ($numeric > 0) ? $numeric : null;
    }

    /**
     * Measure a row's height as the max wrapped height across its cells
     *
     * @param  Parser $parser
     * @param  array  $cells
     * @param  array  $columnWidths
     * @param  array  $styles
     * @return float
     */
    protected static function measureRowHeight(Parser $parser, array $cells, array $columnWidths, array $styles): float
    {
        $maxHeight = 0.0;

        foreach ($cells as $cell) {
            $cellStyles = self::cellStyles($parser, $cell, $styles);
            $fontObject = $parser->getDocument()->getFont($cellStyles['currentFont']);
            $cellWidth  = max(1.0, self::spanWidth($columnWidths, $cell) - $cellStyles['paddingLeft'] - $cellStyles['paddingRight']);

            $text      = self::cellText($cell);
            $lines     = ($text !== '') ? $parser->getStringLines($text, $cellStyles['fontSize'], (int) $cellWidth, $fontObject) : [];
            $lineCount = max(1, count($lines));

            $height    = ($lineCount * $cellStyles['lineHeight']) + $cellStyles['paddingTop'] + $cellStyles['paddingBottom'];
            $maxHeight = max($maxHeight, $height);
        }

        return max($maxHeight, self::MIN_ROW_HEIGHT);
    }

    /**
     * Draw one row: each cell's box, then its wrapped text
     *
     * @param  Parser $parser
     * @param  array  $cells
     * @param  array  $columnWidths
     * @param  array  $styles
     * @param  int    $startX
     * @param  float  $rowTopY
     * @param  array  $rowHeights
     * @return void
     */
    protected static function drawRow(Parser $parser, array $cells, array $columnWidths, array $styles, int $startX, float $rowTopY, array $rowHeights): void
    {
        foreach ($cells as $cell) {
            $cellX      = $startX + self::widthBeforeColumn($columnWidths, $cell->getCol());
            $cellWidth  = self::spanWidth($columnWidths, $cell);
            $cellHeight = self::spanHeight($rowHeights, $cell);
            $cellStyles = self::cellStyles($parser, $cell, $styles);

            $parser->drawBox($cellX, $rowTopY, $cellWidth, $cellHeight, $cellStyles);

            $text = self::cellText($cell);
            if ($text === '') {
                continue;
            }

            $fontObject = $parser->getDocument()->getFont($cellStyles['currentFont']);
            $textWidth  = max(1.0, $cellWidth - $cellStyles['paddingLeft'] - $cellStyles['paddingRight']);
            $lines      = $parser->getStringLines($text, $cellStyles['fontSize'], (int) $textWidth, $fontObject);

            $textX = $cellX + $cellStyles['paddingLeft'];
            $textY = $rowTopY - $cellStyles['paddingTop'] - $cellStyles['fontSize'];

            foreach ($lines as $i => $line) {
                $lineText = new Document\Page\Text($line, $cellStyles['fontSize']);
                $lineText->setFillColor(new Color\Rgb($cellStyles['color'][0], $cellStyles['color'][1], $cellStyles['color'][2]));
                $parser->getPage()->addText($lineText, $cellStyles['currentFont'], $textX, $textY - ($i * $cellStyles['lineHeight']));
            }
        }
    }

    /**
     * Resolve one cell's styles from its own tag/attributes, inheriting the table's styles
     *
     * @param  Parser $parser
     * @param  Cell   $cell
     * @param  array  $parentStyles
     * @return array
     */
    protected static function cellStyles(Parser $parser, Cell $cell, array $parentStyles): array
    {
        $node = $cell->getNode();
        return $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes(), $parentStyles);
    }

    /**
     * Resolve a cell's trimmed, whitespace-collapsed text content
     *
     * Uses getTextContent(false) rather than pop-dom's ignoreWhiteSpace=true
     * mode, which normalizes every '.' into '. ' (sentence-punctuation
     * spacing) - correct for prose, but it mangles decimal numbers like
     * "$19.99" into "$19. 99". Whitespace/newlines from HTML source
     * formatting are collapsed here instead, without that side effect.
     *
     * @param  Cell $cell
     * @return string
     */
    protected static function cellText(Cell $cell): string
    {
        return trim(preg_replace('/\s+/', ' ', $cell->getNode()->getTextContent(false)));
    }

    /**
     * Sum the widths of every column a cell spans
     *
     * @param  array $columnWidths
     * @param  Cell  $cell
     * @return float
     */
    protected static function spanWidth(array $columnWidths, Cell $cell): float
    {
        $width = 0.0;
        for ($c = $cell->getCol(); $c < ($cell->getCol() + $cell->getColSpan()); $c++) {
            $width += $columnWidths[$c] ?? 0.0;
        }
        return $width;
    }

    /**
     * Sum the heights of every row a cell spans
     *
     * @param  array $rowHeights
     * @param  Cell  $cell
     * @return float
     */
    protected static function spanHeight(array $rowHeights, Cell $cell): float
    {
        $height = 0.0;
        for ($r = $cell->getRow(); $r < ($cell->getRow() + $cell->getRowSpan()); $r++) {
            $height += $rowHeights[$r] ?? 0.0;
        }
        return $height;
    }

    /**
     * Sum the widths of every column before a given column index
     *
     * @param  array $columnWidths
     * @param  int   $col
     * @return float
     */
    protected static function widthBeforeColumn(array $columnWidths, int $col): float
    {
        $width = 0.0;
        for ($c = 0; $c < $col; $c++) {
            $width += $columnWidths[$c] ?? 0.0;
        }
        return $width;
    }

}
