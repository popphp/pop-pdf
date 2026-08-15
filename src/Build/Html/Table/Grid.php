<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build\Html\Table;

use Pop\Dom\Child;

/**
 * Pdf HTML table grid class
 *
 * Walks a <table> DOM node into an explicit [row][col] grid, handling
 * colspan/rowspan slot-claiming and header-row detection (<thead>, or a
 * <tr> whose cells are all <th>).
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Grid
{

    /**
     * Rows, keyed by zero-based row index: ['cells' => Cell[], 'isHeader' => bool]
     * @var array
     */
    protected array $rows = [];

    /**
     * Total column count across the whole table
     * @var int
     */
    protected int $columnCount = 0;

    /**
     * Total row count
     * @var int
     */
    protected int $rowCount = 0;

    /**
     * Build a grid from a <table> DOM node
     *
     * @param  Child $tableNode
     * @return Grid
     */
    public static function build(Child $tableNode): Grid
    {
        $grid = new self();
        $grid->parse($tableNode);
        return $grid;
    }

    /**
     * Get the rows
     *
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Get the total column count
     *
     * @return int
     */
    public function getColumnCount(): int
    {
        return $this->columnCount;
    }

    /**
     * Get the total row count
     *
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Parse the <table> DOM node into the grid
     *
     * @param  Child $tableNode
     * @return void
     */
    protected function parse(Child $tableNode): void
    {
        $occupied = [];
        $rowIndex = 0;

        foreach (self::collectRowNodes($tableNode) as $rowInfo) {
            [$trNode, $isHeaderGroup] = $rowInfo;

            $colIndex = 0;
            $rowCells = [];
            $isAllTh  = $trNode->hasChildNodes();

            foreach ($trNode->getChildNodes() as $cellNode) {
                $name = $cellNode->getNodeName();
                if (($name !== 'td') && ($name !== 'th')) {
                    continue;
                }
                if ($name !== 'th') {
                    $isAllTh = false;
                }

                while (isset($occupied[$rowIndex . ':' . $colIndex])) {
                    $colIndex++;
                }

                // Clamped to the HTML living standard's own caps (rowspan
                // 65534, colspan 1000) here, at the point the raw attribute
                // is read - Cell's constructor clamps too, but by then the
                // slot-claiming loop below would already have iterated the
                // unclamped value, which is unbounded author-provided input.
                $colSpan = min(1000, max(1, (int) ($cellNode->getAttribute('colspan') ?? 1)));
                $rowSpan = min(65534, max(1, (int) ($cellNode->getAttribute('rowspan') ?? 1)));

                // Each span is individually clamped above, but their PRODUCT
                // still isn't bounded - rowspan=65534 * colspan=1000 alone
                // claims 65.5 million occupied-slot entries. Cap the product
                // a single cell can claim, scaling down whichever dimension
                // is larger, so one cell can never allocate more than a few
                // thousand slots regardless of how the two attributes combine.
                if (($rowSpan * $colSpan) > 10000) {
                    if ($rowSpan > $colSpan) {
                        $rowSpan = (int) max(1, floor(10000 / $colSpan));
                    } else {
                        $colSpan = (int) max(1, floor(10000 / $rowSpan));
                    }
                }

                $cell = new Cell($cellNode, $rowIndex, $colIndex, $rowSpan, $colSpan, ($name === 'th'));
                $rowCells[] = $cell;

                for ($r = $rowIndex; $r < ($rowIndex + $rowSpan); $r++) {
                    for ($c = $colIndex; $c < ($colIndex + $colSpan); $c++) {
                        $occupied[$r . ':' . $c] = true;
                    }
                }

                $colIndex += $colSpan;
            }

            if (!empty($rowCells)) {
                $this->rows[$rowIndex] = [
                    'cells'    => $rowCells,
                    'isHeader' => ($isHeaderGroup || $isAllTh)
                ];
                $this->columnCount = max($this->columnCount, $colIndex);
                $rowIndex++;
            }
        }

        $this->rowCount = $rowIndex;
    }

    /**
     * Collect every <tr> node in document order, alongside whether it's inside a <thead>
     *
     * @param  Child $tableNode
     * @return array
     */
    protected static function collectRowNodes(Child $tableNode): array
    {
        $result = [];

        foreach ($tableNode->getChildNodes() as $child) {
            $name = $child->getNodeName();
            if (($name === 'thead') || ($name === 'tbody') || ($name === 'tfoot')) {
                foreach ($child->getChildNodes() as $tr) {
                    if ($tr->getNodeName() === 'tr') {
                        $result[] = [$tr, ($name === 'thead')];
                    }
                }
            } else if ($name === 'tr') {
                $result[] = [$child, false];
            }
        }

        return $result;
    }

}
