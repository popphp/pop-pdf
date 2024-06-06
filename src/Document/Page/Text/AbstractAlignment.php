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
 * Pdf page text abstract alignment class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.2.0
 */
abstract class AbstractAlignment implements AlignmentInterface
{

    /**
     * Alignment constants
     */
    const LEFT  = 'LEFT';
    const RIGHT = 'RIGHT';

    /**
     * Text alignment
     * @var string
     */
    protected string $alignment = self::LEFT;

    /**
     * Left X boundary
     * @var int
     */
    protected int $leftX = 0;

    /**
     * Right X boundary
     * @var int
     */
    protected int $rightX = 0;

    /**
     * Text leading
     * @var int
     */
    protected int $leading = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF text alignment object.
     *
     * @param string $alignment
     * @param int    $leftX
     * @param int    $rightX
     * @param int    $leading
     */
    public function __construct(string $alignment = self::LEFT, int $leftX = 0, int $rightX = 0, int $leading = 0)
    {
        $this->setAlignment($alignment);
        $this->setLeftX($leftX);
        $this->setRightX($rightX);
        $this->setLeading($leading);
    }

    /**
     * Set alignment
     *
     * @param  string $alignment
     * @return AbstractAlignment
     */
    public function setAlignment(string $alignment): AbstractAlignment
    {
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Set the left X boundary
     *
     * @param  int $x
     * @return AbstractAlignment
     */
    public function setLeftX(int $x): AbstractAlignment
    {
        $this->leftX = $x;
        return $this;
    }

    /**
     * Set the right X boundary
     *
     * @param  int $x
     * @return AbstractAlignment
     */
    public function setRightX(int $x): AbstractAlignment
    {
        $this->rightX = $x;
        return $this;
    }

    /**
     * Set the leading
     *
     * @param  int $leading
     * @return AbstractAlignment
     */
    public function setLeading(int $leading): AbstractAlignment
    {
        $this->leading = $leading;
        return $this;
    }

    /**
     * Get alignment
     *
     * @return string
     */
    public function getAlignment(): string
    {
        return $this->alignment;
    }

    /**
     * Get the left X
     *
     * @return int
     */
    public function getLeftX(): int
    {
        return $this->leftX;
    }

    /**
     * Get the right X
     *
     * @return int
     */
    public function getRightX(): int
    {
        return $this->rightX;
    }

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading(): int
    {
        return $this->leading;
    }

    /**
     * Has left X
     *
     * @return bool
     */
    public function hasLeftX(): bool
    {
        return ($this->leftX > 0);
    }

    /**
     * Has right X
     *
     * @return bool
     */
    public function hasRightX(): bool
    {
        return ($this->rightX > 0);
    }

    /**
     * Has leading
     *
     * @return bool
     */
    public function hasLeading(): bool
    {
        return ($this->leading > 0);
    }

    /**
     * Is LEFT alignment
     *
     * @return bool
     */
    public function isLeft(): bool
    {
        return ($this->alignment == self::LEFT);
    }

    /**
     * Is RIGHT alignment
     *
     * @return bool
     */
    public function isRight(): bool
    {
        return ($this->alignment == self::RIGHT);
    }

}
