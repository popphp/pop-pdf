<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
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
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
class Link extends AbstractAnnotation
{

    /**
     * Internal x-position target to link to on internal page
     * @var int
     */
    protected int $xTarget = 0;

    /**
     * Internal y-position target to link to on internal page
     * @var int
     */
    protected int $yTarget = 0;

    /**
     * Internal z-position target (zoom) to link to on internal page
     * @var int
     */
    protected int $zTarget = 0;

    /**
     * Internal page object index target to link to
     * @var ?int
     */
    protected ?int $pageTarget = null;

    /**
     * Constructor
     *
     * Instantiate a PDF link annotation object.
     *
     * @param  int $width
     * @param  int $height
     * @param  int $xTarget
     * @param  int $yTarget
     */
    public function __construct(int $width, int $height, int $xTarget, int $yTarget)
    {
        parent::__construct($width, $height);

        $this->setXTarget($xTarget);
        $this->setYTarget($yTarget);
    }

    /**
     * Set the X target
     *
     * @param  int $xTarget
     * @return Link
     */
    public function setXTarget(int $xTarget): Link
    {
        $this->xTarget = $xTarget;
        return $this;
    }

    /**
     * Set the Y target
     *
     * @param  int $yTarget
     * @return Link
     */
    public function setYTarget(int $yTarget): Link
    {
        $this->yTarget = $yTarget;
        return $this;
    }

    /**
     * Set the Z (zoom) target
     *
     * @param  int $zTarget
     * @return Link
     */
    public function setZTarget(int $zTarget): Link
    {
        $this->zTarget = $zTarget;
        return $this;
    }

    /**
     * Set the page target
     *
     * @param  int $pageTarget
     * @return Link
     */
    public function setPageTarget(int $pageTarget): Link
    {
        $this->pageTarget = $pageTarget;
        return $this;
    }

    /**
     * Get the X target
     *
     * @return int
     */
    public function getXTarget(): int
    {
        return $this->xTarget;
    }

    /**
     * Get the Y target
     *
     * @return int
     */
    public function getYTarget(): int
    {
        return $this->yTarget;
    }

    /**
     * Get the Z (zoom) target
     *
     * @return int
     */
    public function getZTarget(): int
    {
        return $this->zTarget;
    }

    /**
     * Get the page target
     *
     * @return ?int
     */
    public function getPageTarget(): ?int
    {
        return $this->pageTarget;
    }

    /**
     * Get the annotation stream
     *
     * @param  int   $i
     * @param  int   $x
     * @param  int   $y
     * @param  int   $pageIndex
     * @param  array $kids
     * @return string
     */
    public function getStream(int $i, int $x, int $y, int $pageIndex, array $kids): string
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
