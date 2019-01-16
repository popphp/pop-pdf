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
 * Pdf page gray color class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
class Gray extends AbstractColor
{

    /**
     * Gray
     * @var float
     */
    protected $gray = 0;

    /**
     * Constructor
     *
     * Instantiate a PDF Gray Color object
     *
     * @param  mixed $gray   0 - 100
     */
    public function __construct($gray)
    {
        $this->setGray($gray);
    }

    /**
     * Set the gray value
     *
     * @param  mixed $gray
     * @throws \OutOfRangeException
     * @return Gray
     */
    public function setGray($gray)
    {
        if (((float)$gray < 0) || ((float)$gray > 100)) {
            throw new \OutOfRangeException('Error: The value must be between 0 and 100');
        }
        $this->gray = (float)$gray;
        return $this;
    }

    /**
     * Get the gray value
     *
     * @return float
     */
    public function getGray()
    {
        return $this->gray;
    }

    /**
     * Method to print the color object
     *
     * @return string
     */
    public function __toString()
    {
        return (string)round(($this->gray / 100), 2);
    }

}