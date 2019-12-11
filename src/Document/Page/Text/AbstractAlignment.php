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
 * Pdf page text abstract alignment class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
abstract class AbstractAlignment implements AlignmentInterface
{

    /**
     * Alignment constants
     */
    const LEFT   = 'LEFT';
    const RIGHT  = 'RIGHT';

    /**
     * Text alignment
     * @var string
     */
    protected $alignment = self::LEFT;

    /**
     * Left X boundary
     * @var int
     */
    protected $leftX = 0;

    /**
     * Right X boundary
     * @var int
     */
    protected $rightX = 0;

    /**
     * Text leading
     * @var int
     */
    protected $leading = 0;

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
    public function __construct($alignment = self::LEFT, $leftX = 0, $rightX = 0, $leading = 0)
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
    public function setAlignment($alignment)
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
    public function setLeftX($x)
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
    public function setRightX($x)
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
    public function setLeading($leading)
    {
        $this->leading = $leading;
        return $this;
    }

    /**
     * Get alignment
     *
     * @return string
     */
    public function getAlignment()
    {
        return $this->alignment;
    }

    /**
     * Get the left X
     *
     * @return int
     */
    public function getLeftX()
    {
        return $this->leftX;
    }

    /**
     * Get the right X
     *
     * @return int
     */
    public function getRightX()
    {
        return $this->rightX;
    }

    /**
     * Get the leading
     *
     * @return int
     */
    public function getLeading()
    {
        return $this->leading;
    }

    /**
     * Has left X
     *
     * @return boolean
     */
    public function hasLeftX()
    {
        return ($this->leftX > 0);
    }

    /**
     * Has right X
     *
     * @return boolean
     */
    public function hasRightX()
    {
        return ($this->rightX > 0);
    }

    /**
     * Has leading
     *
     * @return boolean
     */
    public function hasLeading()
    {
        return ($this->leading > 0);
    }

    /**
     * Is LEFT alignment
     *
     * @return boolean
     */
    public function isLeft()
    {
        return ($this->alignment == self::LEFT);
    }

    /**
     * Is RIGHT alignment
     *
     * @return boolean
     */
    public function isRight()
    {
        return ($this->alignment == self::RIGHT);
    }

}