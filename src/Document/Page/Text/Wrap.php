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
namespace Pop\Pdf\Document\Page\Text;

/**
 * Pdf page text wrap class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Wrap
{

    /**
     * Wrap constants
     */
    const LEFT  = 'LEFT';
    const RIGHT = 'RIGHT';

    /**
     * Wrap direction
     * @var int
     */
    protected $direction = 'LEFT';

    /**
     * Box left X boundary
     * @var int
     */
    protected $leftX = 0;

    /**
     * Box right X boundary
     * @var int
     */
    protected $rightX = 0;

    /**
     * Box top Y boundary
     * @var int
     */
    protected $topY = 0;

    /**
     * Box bottom Y boundary
     * @var int
     */
    protected $bottomY = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF text alignment object.
     *
     */
    public function __construct()
    {

    }

    /**
     * Set wrap direction
     *
     * @param  string $direction
     * @throws \InvalidArgumentException
     * @return Wrap
     */
    public function setDirection($direction)
    {
        $direction = strtoupper($direction);

        if (($direction != self::LEFT) && ($direction != self::RIGHT)) {
            throw new \InvalidArgumentException('Error: The direction must be either LEFT or RIGHT');
        }

        $this->direction = $direction;
        return $this;
    }

    /**
     * Set left X boundary
     *
     * @param  int $leftX
     * @return Wrap
     */
    public function setLeftX($leftX)
    {
        $this->leftX = $leftX;
        return $this;
    }

    /**
     * Set right X boundary
     *
     * @param  int $rightX
     * @return Wrap
     */
    public function setRightX($rightX)
    {
        $this->rightX = $rightX;
        return $this;
    }

    /**
     * Set top Y boundary
     *
     * @param  int $topY
     * @return Wrap
     */
    public function setTopY($topY)
    {
        $this->topY = $topY;
        return $this;
    }

    /**
     * Set bottom Y boundary
     *
     * @param  int $bottomY
     * @return Wrap
     */
    public function setBottomY($bottomY)
    {
        $this->bottomY = $bottomY;
        return $this;
    }

    /**
     * Get wrap direction
     *
     * @return string
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * Get left X boundary
     *
     * @return int
     */
    public function getLeftX()
    {
        return $this->leftX;
    }

    /**
     * Get right X boundary
     *
     * @return int
     */
    public function getRightX()
    {
        return $this->rightX;
    }

    /**
     * Get top Y boundary
     *
     * @return int
     */
    public function getTopY()
    {
        return $this->topY;
    }

    /**
     * Get bottom Y boundary
     *
     * @return int
     */
    public function getBottomY()
    {
        return $this->bottomY;
    }

}