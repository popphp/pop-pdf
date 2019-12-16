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

use Pop\Pdf\Document\Page\Color\ColorInterface;

/**
 * Pdf page text stream class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Stream
{

    /**
     * Start X
     * @var int
     */
    protected $startX = null;

    /**
     * Start Y
     * @var int
     */
    protected $startY = null;

    /**
     * Edge X boundary
     * @var int
     */
    protected $edgeX = null;

   /**
     * Text streams
     * @var array
     */
    protected $streams = [];

    /**
     * Text styles
     * @var array
     */
    protected $styles = [];

    /**
     * Constructor
     *
     * Instantiate a PDF text stream object.
     *
     * @param int $startX
     * @param int $startY
     * @param int $edgeX
     */
    public function __construct($startX, $startY, $edgeX)
    {
        $this->startX = $startX;
        $this->startY = $startY;
        $this->edgeX  = $edgeX;
    }

    /**
     * Add text to the stream
     *
     * @param string $string
     * @param int    $x
     * @param int    $y
     * @return Stream
     */
    public function addText($string, $x = null, $y = null)
    {
        $this->streams[] = [
            'string' => $string,
            'x'      => $x,
            'y'      => $y
        ];

        return $this;
    }

    /**
     * Set the current style
     *
     * @param  string         $font
     * @param  int            $size
     * @param  ColorInterface $color
     * @return Stream
     */
    public function setCurrentStyle($font, $size, ColorInterface $color = null)
    {
        $key = (!empty($this->streams)) ? count($this->streams) : 0;
        $this->styles[$key] = [
            'font'  => $font,
            'size'  => $size,
            'color' => $color
        ];

        return $this;
    }


    /**
     * Get stream
     *
     * @return string
     */
    public function getStream()
    {
        $stream = '';

        foreach ($this->streams as $i => $string) {
            $stream .= "    ({$string})Tj\n";
            //if ($i < count($strings)) {
            //    $stream .= "    0 -" . $this->leading . " Td\n";
            //}
        }

        return $stream;
    }


}