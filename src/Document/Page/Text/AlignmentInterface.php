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
namespace Pop\Pdf\Document\Page\Text;

/**
 * Pdf page text alignment interface
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
interface AlignmentInterface
{

    /**
     * Set alignment
     *
     * @param  string $alignment
     * @return AlignmentInterface
     */
    public function setAlignment($alignment);

    /**
     * Set the left X boundary
     *
     * @param  int $x
     * @return AlignmentInterface
     */
    public function setLeftX($x);

    /**
     * Set the right X boundary
     *
     * @param  int $x
     * @return AlignmentInterface
     */
    public function setRightX($x);

    /**
     * Set the leading
     *
     * @param  int $leading
     * @return AlignmentInterface
     */
    public function setLeading($leading);

    /**
     * Get character wrap boundary
     *
     * @return string
     */
    public function getAlignment();

    /**
     * Get left X
     *
     * @return int
     */
    public function getLeftX();

    /**
     * Get left X
     *
     * @return int
     */
    public function getRightX();

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading();

    /**
     * Has left X
     *
     * @return boolean
     */
    public function hasLeftX();

    /**
     * Has right X
     *
     * @return boolean
     */
    public function hasRightX();

    /**
     * Has leading
     *
     * @return boolean
     */
    public function hasLeading();

    /**
     * Is LEFT alignment
     *
     * @return boolean
     */
    public function isLeft();

    /**
     * Is RIGHT alignment
     *
     * @return boolean
     */
    public function isRight();

}