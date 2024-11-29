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
 * Pdf page annotation interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
interface AnnotationInterface
{

    /**
     * Set the width
     *
     * @param  int $width
     * @return AnnotationInterface
     */
    public function setWidth(int $width): AnnotationInterface;

    /**
     * Set the height
     *
     * @param  int $height
     * @return AnnotationInterface
     */
    public function setHeight(int $height): AnnotationInterface;

    /**
     * Set the horizontal border radius
     *
     * @param  int $radius
     * @return AnnotationInterface
     */
    public function setHRadius(int $radius): AnnotationInterface;

    /**
     * Set the vertical border radius
     *
     * @param  int $radius
     * @return AnnotationInterface
     */
    public function setVRadius(int $radius): AnnotationInterface;

    /**
     * Set the border width
     *
     * @param  int $width
     * @return AnnotationInterface
     */
    public function setBorderWidth(int $width): AnnotationInterface;

    /**
     * Set the border dash length
     *
     * @param  int $length
     * @return AnnotationInterface
     */
    public function setDashLength(int $length): AnnotationInterface;

    /**
     * Set the border dash gap
     *
     * @param  int $gap
     * @return AnnotationInterface
     */
    public function setDashGap(int $gap): AnnotationInterface;

    /**
     * Get the width
     *
     * @return int
     */
    public function getWidth(): int;

    /**
     * Get the height
     *
     * @return int
     */
    public function getHeight(): int;

    /**
     * Get the horizontal border radius
     *
     * @return int
     */
    public function getHRadius(): int;

    /**
     * Get the vertical border radius
     *
     * @return int
     */
    public function getVRadius(): int;

    /**
     * Get the border width
     *
     * @return int
     */
    public function getBorderWidth(): int;

    /**
     * Get the border dash length
     *
     * @return int
     */
    public function getDashLength(): int;

    /**
     * Get the border dash gap
     *
     * @return int
     */
    public function getDashGap(): int;

}
