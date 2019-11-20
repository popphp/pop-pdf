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
namespace Pop\Pdf\Document\Page\Color;

/**
 * Pdf page RGB color class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Rgb extends AbstractColor
{

    /**
     * Red
     * @var float
     */
    protected $r = 0;

    /**
     * Green
     * @var float
     */
    protected $g = 0;

    /**
     * Blue
     * @var float
     */
    protected $b = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF RGB Color object
     *
     * @param  mixed $r   0 - 255
     * @param  mixed $g   0 - 255
     * @param  mixed $b   0 - 255
     */
    public function __construct($r, $g, $b)
    {
        $this->setR($r);
        $this->setG($g);
        $this->setB($b);
    }

    /**
     * Set the red value
     *
     * @param  mixed $r
     * @throws \OutOfRangeException
     * @return Rgb
     */
    public function setR($r)
    {
        if (((float)$r < 0) || ((float)$r > 255)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 255');
        }
        $this->r = (float)$r;
        return $this;
    }

    /**
     * Set the green value
     *
     * @param  mixed $g
     * @throws \OutOfRangeException
     * @return Rgb
     */
    public function setG($g)
    {
        if (((float)$g < 0) || ((float)$g > 255)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 255');
        }
        $this->g = (float)$g;
        return $this;
    }

    /**
     * Set the blue value
     *
     * @param  mixed $b
     * @throws \OutOfRangeException
     * @return Rgb
     */
    public function setB($b)
    {
        if (((float)$b < 0) || ((float)$b > 255)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 255');
        }
        $this->b = (float)$b;
        return $this;
    }

    /**
     * Get the red value
     *
     * @return float
     */
    public function getR()
    {
        return $this->r;
    }

    /**
     * Get the green value
     *
     * @return float
     */
    public function getG()
    {
        return $this->g;
    }

    /**
     * Get the blue value
     *
     * @return float
     */
    public function getB()
    {
        return $this->b;
    }

    /**
     * Method to print the color object
     *
     * @return string
     */
    public function __toString()
    {
        return round(($this->r / 255), 2) . ' ' . round(($this->g / 255), 2) . ' ' . round(($this->b / 255), 2);
    }

}