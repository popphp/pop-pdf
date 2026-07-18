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
 * Pdf page annotation interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.7
 */
interface AnnotationInterface
{

    /**
     * Set the width
     *
     * @param  int|float $width
     * @return AnnotationInterface
     */
    public function setWidth(int|float $width): AnnotationInterface;

    /**
     * Set the height
     *
     * @param  int|float $height
     * @return AnnotationInterface
     */
    public function setHeight(int|float $height): AnnotationInterface;

    /**
     * Set the horizontal border radius
     *
     * @param  int|float $radius
     * @return AnnotationInterface
     */
    public function setHRadius(int|float $radius): AnnotationInterface;

    /**
     * Set the vertical border radius
     *
     * @param  int|float $radius
     * @return AnnotationInterface
     */
    public function setVRadius(int|float $radius): AnnotationInterface;

    /**
     * Set the border width
     *
     * @param  int|float $width
     * @return AnnotationInterface
     */
    public function setBorderWidth(int|float $width): AnnotationInterface;

    /**
     * Set the border dash length
     *
     * @param  int|float $length
     * @return AnnotationInterface
     */
    public function setDashLength(int|float $length): AnnotationInterface;

    /**
     * Set the border dash gap
     *
     * @param  int|float $gap
     * @return AnnotationInterface
     */
    public function setDashGap(int|float $gap): AnnotationInterface;

    /**
     * Get the width
     *
     * @return int|float
     */
    public function getWidth(): int|float;

    /**
     * Get the height
     *
     * @return int|float
     */
    public function getHeight(): int|float;

    /**
     * Get the horizontal border radius
     *
     * @return int|float
     */
    public function getHRadius(): int|float;

    /**
     * Get the vertical border radius
     *
     * @return int|float
     */
    public function getVRadius(): int|float;

    /**
     * Get the border width
     *
     * @return int|float
     */
    public function getBorderWidth(): int|float;

    /**
     * Get the border dash length
     *
     * @return int|float
     */
    public function getDashLength(): int|float;

    /**
     * Get the border dash gap
     *
     * @return int|float
     */
    public function getDashGap(): int|float;

}
