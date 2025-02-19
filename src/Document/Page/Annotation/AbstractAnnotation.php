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
 * Pdf abstract page annotation class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
abstract class AbstractAnnotation implements AnnotationInterface
{

    /**
     * Annotation width
     * @var int
     */
    protected int $width = 0;

    /**
     * Annotation height
     * @var int
     */
    protected int $height = 0;

    /**
     * Horizontal border radius
     * @var int
     */
    protected int $hRadius = 0;

    /**
     * Vertical border radius
     * @var int
     */
    protected int $vRadius = 0;

    /**
     * Border width
     * @var int
     */
    protected int $borderWidth = 0;

    /**
     * Border dash length
     * @var int
     */
    protected int $dashLength = 0;

    /**
     * Border dash gap
     * @var int
     */
    protected int $dashGap = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF annotation object.
     *
     * @param  int $width
     * @param  int $height
     */
    public function __construct(int $width, int $height)
    {
        $this->setWidth($width);
        $this->setHeight($height);
    }

    /**
     * Set the width
     *
     * @param  int $width
     * @return AbstractAnnotation
     */
    public function setWidth(int $width): AbstractAnnotation
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Set the height
     *
     * @param  int $height
     * @return AbstractAnnotation
     */
    public function setHeight(int $height): AbstractAnnotation
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Set the horizontal border radius
     *
     * @param  int $radius
     * @return AbstractAnnotation
     */
    public function setHRadius(int $radius): AbstractAnnotation
    {
        $this->hRadius = $radius;
        return $this;
    }

    /**
     * Set the vertical border radius
     *
     * @param  int $radius
     * @return AbstractAnnotation
     */
    public function setVRadius(int $radius): AbstractAnnotation
    {
        $this->vRadius = $radius;
        return $this;
    }

    /**
     * Set the border width
     *
     * @param  int $width
     * @return AbstractAnnotation
     */
    public function setBorderWidth(int $width): AbstractAnnotation
    {
        $this->borderWidth = $width;
        return $this;
    }

    /**
     * Set the border dash length
     *
     * @param  int $length
     * @return AbstractAnnotation
     */
    public function setDashLength(int $length): AbstractAnnotation
    {
        $this->dashLength = $length;
        return $this;
    }

    /**
     * Set the border dash gap
     *
     * @param  int $gap
     * @return AbstractAnnotation
     */
    public function setDashGap(int $gap): AbstractAnnotation
    {
        $this->dashGap = $gap;
        return $this;
    }

    /**
     * Get the width
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get the height
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get the horizontal border radius
     *
     * @return int
     */
    public function getHRadius(): int
    {
        return $this->hRadius;
    }

    /**
     * Get the vertical border radius
     *
     * @return int
     */
    public function getVRadius(): int
    {
        return $this->vRadius;
    }

    /**
     * Get the border width
     *
     * @return int
     */
    public function getBorderWidth(): int
    {
        return $this->borderWidth;
    }

    /**
     * Get the border dash length
     *
     * @return int
     */
    public function getDashLength(): int
    {
        return $this->dashLength;
    }

    /**
     * Get the border dash gap
     *
     * @return int
     */
    public function getDashGap(): int
    {
        return $this->dashGap;
    }

}
