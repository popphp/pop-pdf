<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
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
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
abstract class AbstractAnnotation implements AnnotationInterface
{

    /**
     * Annotation width
     * @var int
     */
    protected $width = 0;

    /**
     * Annotation height
     * @var int
     */
    protected $height = 0;

    /**
     * Horizontal border radius
     * @var int
     */
    protected $hRadius = 0;

    /**
     * Vertical border radius
     * @var int
     */
    protected $vRadius = 0;

    /**
     * Border width
     * @var int
     */
    protected $borderWidth = 0;

    /**
     * Border dash length
     * @var int
     */
    protected $dashLength = 0;

    /**
     * Border dash gap
     * @var int
     */
    protected $dashGap = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF annotation object.
     *
     * @param  int $width
     * @param  int $height
     */
    public function __construct($width, $height)
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
    public function setWidth($width)
    {
        $this->width = (int)$width;
        return $this;
    }

    /**
     * Set the height
     *
     * @param  int $height
     * @return AbstractAnnotation
     */
    public function setHeight($height)
    {
        $this->height = (int)$height;
        return $this;
    }

    /**
     * Set the horizontal border radius
     *
     * @param  int $radius
     * @return AbstractAnnotation
     */
    public function setHRadius($radius)
    {
        $this->hRadius = (int)$radius;
        return $this;
    }

    /**
     * Set the vertical border radius
     *
     * @param  int $radius
     * @return AbstractAnnotation
     */
    public function setVRadius($radius)
    {
        $this->vRadius = (int)$radius;
        return $this;
    }

    /**
     * Set the border width
     *
     * @param  int $width
     * @return AbstractAnnotation
     */
    public function setBorderWidth($width)
    {
        $this->borderWidth = (int)$width;
        return $this;
    }

    /**
     * Set the border dash length
     *
     * @param  int $length
     * @return AbstractAnnotation
     */
    public function setDashLength($length)
    {
        $this->dashLength = (int)$length;
        return $this;
    }

    /**
     * Set the border dash gap
     *
     * @param  int $gap
     * @return AbstractAnnotation
     */
    public function setDashGap($gap)
    {
        $this->dashGap = (int)$gap;
        return $this;
    }

    /**
     * Get the width
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get the height
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get the horizontal border radius
     *
     * @return int
     */
    public function getHRadius()
    {
        return $this->hRadius;
    }

    /**
     * Get the vertical border radius
     *
     * @return int
     */
    public function getVRadius()
    {
        return $this->vRadius;
    }

    /**
     * Get the border width
     *
     * @return int
     */
    public function getBorderWidth()
    {
        return $this->borderWidth;
    }

    /**
     * Get the border dash length
     *
     * @return int
     */
    public function getDashLength()
    {
        return $this->dashLength;
    }

    /**
     * Get the border dash gap
     *
     * @return int
     */
    public function getDashGap()
    {
        return $this->dashGap;
    }

}