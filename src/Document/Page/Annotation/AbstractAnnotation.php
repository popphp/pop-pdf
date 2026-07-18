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
 * Pdf abstract page annotation class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
abstract class AbstractAnnotation implements AnnotationInterface
{

    /**
     * Annotation width
     * $var int|float
     */
    protected int|float $width = 0;

    /**
     * Annotation height
     * $var int|float
     */
    protected int|float $height = 0;

    /**
     * Horizontal border radius
     * $var int|float
     */
    protected int|float $hRadius = 0;

    /**
     * Vertical border radius
     * $var int|float
     */
    protected int|float $vRadius = 0;

    /**
     * Border width
     * $var int|float
     */
    protected int|float $borderWidth = 0;

    /**
     * Border dash length
     * $var int|float
     */
    protected int|float $dashLength = 0;

    /**
     * Border dash gap
     * $var int|float
     */
    protected int|float $dashGap = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF annotation object.
     *
     * @param  int|float $width
     * @param  int|float $height
     */
    public function __construct(int|float $width, int|float $height)
    {
        $this->setWidth($width);
        $this->setHeight($height);
    }

    /**
     * Set the width
     *
     * @param  int|float $width
     * @return AbstractAnnotation
     */
    public function setWidth(int|float $width): AbstractAnnotation
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Set the height
     *
     * @param  int|float $height
     * @return AbstractAnnotation
     */
    public function setHeight(int|float $height): AbstractAnnotation
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Set the horizontal border radius
     *
     * @param  int|float $radius
     * @return AbstractAnnotation
     */
    public function setHRadius(int|float $radius): AbstractAnnotation
    {
        $this->hRadius = $radius;
        return $this;
    }

    /**
     * Set the vertical border radius
     *
     * @param  int|float $radius
     * @return AbstractAnnotation
     */
    public function setVRadius(int|float $radius): AbstractAnnotation
    {
        $this->vRadius = $radius;
        return $this;
    }

    /**
     * Set the border width
     *
     * @param  int|float $width
     * @return AbstractAnnotation
     */
    public function setBorderWidth(int|float $width): AbstractAnnotation
    {
        $this->borderWidth = $width;
        return $this;
    }

    /**
     * Set the border dash length
     *
     * @param  int|float $length
     * @return AbstractAnnotation
     */
    public function setDashLength(int|float $length): AbstractAnnotation
    {
        $this->dashLength = $length;
        return $this;
    }

    /**
     * Set the border dash gap
     *
     * @param  int|float $gap
     * @return AbstractAnnotation
     */
    public function setDashGap(int|float $gap): AbstractAnnotation
    {
        $this->dashGap = $gap;
        return $this;
    }

    /**
     * Get the width
     *
     * @return int|float
     */
    public function getWidth(): int|float
    {
        return $this->width;
    }

    /**
     * Get the height
     *
     * @return int|float
     */
    public function getHeight(): int|float
    {
        return $this->height;
    }

    /**
     * Get the horizontal border radius
     *
     * @return int|float
     */
    public function getHRadius(): int|float
    {
        return $this->hRadius;
    }

    /**
     * Get the vertical border radius
     *
     * @return int|float
     */
    public function getVRadius(): int|float
    {
        return $this->vRadius;
    }

    /**
     * Get the border width
     *
     * @return int|float
     */
    public function getBorderWidth(): int|float
    {
        return $this->borderWidth;
    }

    /**
     * Get the border dash length
     *
     * @return int|float
     */
    public function getDashLength(): int|float
    {
        return $this->dashLength;
    }

    /**
     * Get the border dash gap
     *
     * @return int|float
     */
    public function getDashGap(): int|float
    {
        return $this->dashGap;
    }

}
