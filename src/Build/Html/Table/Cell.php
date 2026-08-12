<?php
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
 * Pdf HTML table cell class
 *
 * A value object describing one table cell's grid position/span and the DOM
 * node its content/attributes come from.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Cell
{

    /**
     * The <td>/<th> DOM node
     * @var Child
     */
    protected Child $node;

    /**
     * Zero-based row index this cell starts at
     * @var int
     */
    protected int $row;

    /**
     * Zero-based column index this cell starts at
     * @var int
     */
    protected int $col;

    /**
     * Number of rows this cell spans
     * @var int
     */
    protected int $rowSpan;

    /**
     * Number of columns this cell spans
     * @var int
     */
    protected int $colSpan;

    /**
     * Whether this cell belongs to a header row
     * @var bool
     */
    protected bool $isHeader;

    /**
     * Constructor
     *
     * Instantiate a table cell.
     *
     * @param Child $node
     * @param int   $row
     * @param int   $col
     * @param int   $rowSpan
     * @param int   $colSpan
     * @param bool  $isHeader
     */
    public function __construct(Child $node, int $row, int $col, int $rowSpan = 1, int $colSpan = 1, bool $isHeader = false)
    {
        // Upper-bounded to the HTML living standard's own caps (rowspan
        // 65534, colspan 1000) - an author-provided but unbounded span
        // attribute would otherwise let a single cell allocate unbounded
        // grid slots/row-height sums.
        $this->node     = $node;
        $this->row      = $row;
        $this->col      = $col;
        $this->rowSpan  = min(65534, max(1, $rowSpan));
        $this->colSpan  = min(1000, max(1, $colSpan));
        $this->isHeader = $isHeader;
    }

    /**
     * Get the cell's DOM node
     *
     * @return Child
     */
    public function getNode(): Child
    {
        return $this->node;
    }

    /**
     * Get the row this cell starts at
     *
     * @return int
     */
    public function getRow(): int
    {
        return $this->row;
    }

    /**
     * Get the column this cell starts at
     *
     * @return int
     */
    public function getCol(): int
    {
        return $this->col;
    }

    /**
     * Get the number of rows this cell spans
     *
     * @return int
     */
    public function getRowSpan(): int
    {
        return $this->rowSpan;
    }

    /**
     * Get the number of columns this cell spans
     *
     * @return int
     */
    public function getColSpan(): int
    {
        return $this->colSpan;
    }

    /**
     * Determine if this cell belongs to a header row
     *
     * @return bool
     */
    public function isHeader(): bool
    {
        return $this->isHeader;
    }

}
