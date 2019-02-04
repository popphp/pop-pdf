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
class Wrap extends AbstractAlignment
{

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
     * Instantiate a PDF text wrap object.
     *
     * @param string $direction
     * @param array  $boundaries
     * @param int    $charWrap
     * @param int    $pageWrap
     * @param int    $leading
     */
    public function __construct($direction = 'LEFT', array $boundaries = [], $charWrap = 0, $pageWrap = 0, $leading = 0)
    {
        parent::__construct($charWrap, $pageWrap, $leading);

        $this->setDirection($direction);

        if (!empty($boundaries)) {
            if (isset($boundaries['leftX'])) {
                $this->setLeftX($boundaries['leftX']);
            }
            if (isset($boundaries['rightX'])) {
                $this->setRightX($boundaries['rightX']);
            }
            if (isset($boundaries['topY'])) {
                $this->setTopY($boundaries['topY']);
            }
            if (isset($boundaries['bottomY'])) {
                $this->setBottomY($boundaries['bottomY']);
            }
        }
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

    /**
     * Determine if wrap has direction
     *
     * @return boolean
     */
    public function hasDirection()
    {
        return !empty($this->direction);
    }

    /**
     * Determine if wrap has left X boundary
     *
     * @return boolean
     */
    public function hasLeftX()
    {
        return ($this->leftX > 0);
    }

    /**
     * Determine if wrap has right X boundary
     *
     * @return boolean
     */
    public function hasRightX()
    {
        return ($this->rightX > 0);
    }

    /**
     * Determine if wrap has top Y boundary
     *
     * @return boolean
     */
    public function hasTopY()
    {
        return ($this->topY > 0);
    }

    /**
     * Determine if wrap has bottom Y boundary
     *
     * @return boolean
     */
    public function hasBottomY()
    {
        return ($this->bottomY > 0);
    }

    /**
     * Determine if wrap has full boundary
     *
     * @return boolean
     */
    public function hasBoundary()
    {
        return ($this->hasLeftX() && $this->hasRightX() && $this->hasTopY() && $this->hasBottomY());
    }

}