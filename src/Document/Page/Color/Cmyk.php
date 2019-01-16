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
namespace Pop\Pdf\Document\Page\Color;

/**
 * Pdf page CMYK color class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Cmyk extends AbstractColor
{

    /**
     * Cyan
     * @var float
     */
    protected $c = 0;

    /**
     * Magenta
     * @var float
     */
    protected $m = 0;

    /**
     * Yellow
     * @var float
     */
    protected $y = 0;

    /**
     * Black
     * @var float
     */
    protected $k = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF CMYK Color object
     *
     * @param  mixed $c   0 - 100
     * @param  mixed $m   0 - 100
     * @param  mixed $y   0 - 100
     * @param  mixed $k   0 - 100
     */
    public function __construct($c, $m, $y, $k)
    {
        $this->setC($c);
        $this->setM($m);
        $this->setY($y);
        $this->setK($k);
    }

    /**
     * Set the cyan value
     *
     * @param  mixed $c
     * @throws \OutOfRangeException
     * @return Cmyk
     */
    public function setC($c)
    {
        if (((float)$c < 0) || ((float)$c > 100)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 100');
        }
        $this->c = (float)$c;
        return $this;
    }

    /**
     * Set the magenta value
     *
     * @param  mixed $m
     * @throws \OutOfRangeException
     * @return Cmyk
     */
    public function setM($m)
    {
        if (((float)$m < 0) || ((float)$m > 100)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 100');
        }
        $this->m = (float)$m;
        return $this;
    }

    /**
     * Set the yellow value
     *
     * @param  mixed $y
     * @throws \OutOfRangeException
     * @return Cmyk
     */
    public function setY($y)
    {
        if (((float)$y < 0) || ((float)$y > 100)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 100');
        }
        $this->y = (float)$y;
        return $this;
    }

    /**
     * Set the black value
     *
     * @param  mixed $k
     * @throws \OutOfRangeException
     * @return Cmyk
     */
    public function setK($k)
    {
        if (((float)$k < 0) || ((float)$k > 100)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 100');
        }
        $this->k = (float)$k;
        return $this;
    }

    /**
     * Get the cyan value
     *
     * @return float
     */
    public function getC()
    {
        return $this->c;
    }

    /**
     * Get the magenta value
     *
     * @return float
     */
    public function getM()
    {
        return $this->m;
    }

    /**
     * Get the yellow value
     *
     * @return float
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * Get the black value
     *
     * @return float
     */
    public function getK()
    {
        return $this->k;
    }

    /**
     * Method to print the color object
     *
     * @return string
     */
    public function __toString()
    {
        return round(($this->c / 100), 2) . ' ' . round(($this->m / 100), 2) . ' ' .
            round(($this->y / 100), 2) . ' ' . round(($this->k / 100), 2);
    }

}