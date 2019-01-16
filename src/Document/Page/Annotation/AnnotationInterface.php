<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page\Annotation;

/**
 * Pdf page annotation interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
interface AnnotationInterface
{

    /**
     * Set the width
     *
     * @param  int $width
     * @return AnnotationInterface
     */
    public function setWidth($width);

    /**
     * Set the height
     *
     * @param  int $height
     * @return AnnotationInterface
     */
    public function setHeight($height);

    /**
     * Set the horizontal border radius
     *
     * @param  int $radius
     * @return AnnotationInterface
     */
    public function setHRadius($radius);

    /**
     * Set the vertical border radius
     *
     * @param  int $radius
     * @return AnnotationInterface
     */
    public function setVRadius($radius);

    /**
     * Set the border width
     *
     * @param  int $width
     * @return AnnotationInterface
     */
    public function setBorderWidth($width);

    /**
     * Set the border dash length
     *
     * @param  int $length
     * @return AnnotationInterface
     */
    public function setDashLength($length);

    /**
     * Set the border dash gap
     *
     * @param  int $gap
     * @return AnnotationInterface
     */
    public function setDashGap($gap);

    /**
     * Get the width
     *
     * @return int
     */
    public function getWidth();

    /**
     * Get the height
     *
     * @return int
     */
    public function getHeight();

    /**
     * Get the horizontal border radius
     *
     * @return int
     */
    public function getHRadius();

    /**
     * Get the vertical border radius
     *
     * @return int
     */
    public function getVRadius();

    /**
     * Get the border width
     *
     * @return int
     */
    public function getBorderWidth();

    /**
     * Get the border dash length
     *
     * @return int
     */
    public function getDashLength();

    /**
     * Get the border dash gap
     *
     * @return int
     */
    public function getDashGap();

}