<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
interface AlignmentInterface
{

    /**
     * Set alignment
     *
     * @param  string $alignment
     * @return AlignmentInterface
     */
    public function setAlignment(string $alignment): AlignmentInterface;

    /**
     * Set the left X boundary
     *
     * @param  int $x
     * @return AlignmentInterface
     */
    public function setLeftX(int $x): AlignmentInterface;

    /**
     * Set the right X boundary
     *
     * @param  int $x
     * @return AlignmentInterface
     */
    public function setRightX(int $x): AlignmentInterface;

    /**
     * Set the leading
     *
     * @param  int $leading
     * @return AlignmentInterface
     */
    public function setLeading(int $leading): AlignmentInterface;

    /**
     * Get character wrap boundary
     *
     * @return string
     */
    public function getAlignment(): string;

    /**
     * Get left X
     *
     * @return int
     */
    public function getLeftX(): int;

    /**
     * Get left X
     *
     * @return int
     */
    public function getRightX(): int;

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading(): int;

    /**
     * Has left X
     *
     * @return bool
     */
    public function hasLeftX(): bool;

    /**
     * Has right X
     *
     * @return bool
     */
    public function hasRightX(): bool;

    /**
     * Has leading
     *
     * @return bool
     */
    public function hasLeading(): bool;

    /**
     * Is LEFT alignment
     *
     * @return bool
     */
    public function isLeft(): bool;

    /**
     * Is RIGHT alignment
     *
     * @return bool
     */
    public function isRight(): bool;

}
