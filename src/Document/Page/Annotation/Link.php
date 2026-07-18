<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page link annotation class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
class Link extends AbstractAnnotation
{

    /**
     * Internal x-position target to link to on internal page
     * @var int|float
     */
    protected int|float $xTarget = 0;

    /**
     * Internal y-position target to link to on internal page
     * @var int|float
     */
    protected int|float $yTarget = 0;

    /**
     * Internal z-position target (zoom) to link to on internal page
     * @var int|float
     */
    protected int|float $zTarget = 0;

    /**
     * Internal page object index target to link to
     * @var int|float|null
     */
    protected int|float|null $pageTarget = null;

    /**
     * Constructor
     *
     * Instantiate a PDF link annotation object.
     *
     * @param  int|float $width
     * @param  int|float $height
     * @param  int|float $xTarget
     * @param  int|float $yTarget
     */
    public function __construct(int|float $width, int|float $height, int|float $xTarget, int|float $yTarget)
    {
        parent::__construct($width, $height);

        $this->setXTarget($xTarget);
        $this->setYTarget($yTarget);
    }

    /**
     * Set the X target
     *
     * @param  int|float $xTarget
     * @return Link
     */
    public function setXTarget(int|float $xTarget): Link
    {
        $this->xTarget = $xTarget;
        return $this;
    }

    /**
     * Set the Y target
     *
     * @param  int|float $yTarget
     * @return Link
     */
    public function setYTarget(int|float $yTarget): Link
    {
        $this->yTarget = $yTarget;
        return $this;
    }

    /**
     * Set the Z (zoom) target
     *
     * @param  int|float $zTarget
     * @return Link
     */
    public function setZTarget(int|float $zTarget): Link
    {
        $this->zTarget = $zTarget;
        return $this;
    }

    /**
     * Set the page target
     *
     * @param  int|float $pageTarget
     * @return Link
     */
    public function setPageTarget(int|float $pageTarget): Link
    {
        $this->pageTarget = $pageTarget;
        return $this;
    }

    /**
     * Get the X target
     *
     * @return int|float
     */
    public function getXTarget(): int|float
    {
        return $this->xTarget;
    }

    /**
     * Get the Y target
     *
     * @return int|float
     */
    public function getYTarget(): int|float
    {
        return $this->yTarget;
    }

    /**
     * Get the Z (zoom) target
     *
     * @return int|float
     */
    public function getZTarget(): int|float
    {
        return $this->zTarget;
    }

    /**
     * Get the page target
     *
     * @return int|float|null
     */
    public function getPageTarget(): int|float|null
    {
        return $this->pageTarget;
    }

    /**
     * Get the annotation stream
     *
     * @param  int       $i
     * @param  int|float $x
     * @param  int|float $y
     * @param  int       $pageIndex
     * @param  array     $kids
     * @return string
     */
    public function getStream(int $i, int|float $x, int|float $y, int $pageIndex, array $kids): string
    {
        // Assemble the border parameters
        $border = $this->hRadius . ' ' . $this->vRadius . ' ' . $this->borderWidth;
        if (($this->dashGap != 0) && ($this->dashLength != 0)) {
            $border .= ' [' . $this->dashGap . ' ' . $this->dashLength . ']';
        }

        $pageTargetIndex = (($this->pageTarget !== null) && isset($kids[$this->pageTarget - 1])) ?
            $kids[$this->pageTarget - 1] :
            $pageIndex;

        return "{$i} 0 obj\n<<\n    /Type /Annot\n    /Subtype /Link\n    /Rect [{$x} {$y} " . ($this->width + $x) .
            " " . ($this->height + $y) . "]\n    /Border [" . $border .  "]\n    /Dest [" . $pageTargetIndex .
            " 0 R /XYZ {$this->xTarget} {$this->yTarget} {$this->zTarget}]\n>>\nendobj\n\n";
    }

}
