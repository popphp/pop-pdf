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

use Pop\Pdf\Document\Page\Color;

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
     * @param  string               $font
     * @param  int                  $size
     * @param  Color\ColorInterface $color
     * @return Stream
     */
    public function setCurrentStyle($font, $size, Color\ColorInterface $color = null)
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
     * Get text stream
     *
     * @return array
     */
    public function getTextStreams()
    {
        $streams      = $this->streams;
        $currentFont  = 'Arial';
        $currentSize  = 10;
        $currentColor = new Color\Rgb(0, 0, 0);

        if (isset($this->styles[0])) {
            $currentFont  = $this->styles[0]['font'] ?? 'Arial';
            $currentSize  = $this->styles[0]['size'] ?? 10;
            $currentColor = $this->styles[0]['color'] ?? new Color\Rgb(0, 0, 0);
        }

        foreach ($streams as $i => $stream) {
            if (isset($this->styles[$i])) {
                $currentFont  = $this->styles[$i]['font'] ?? $currentFont;
                $currentSize  = $this->styles[$i]['size'] ?? $currentSize;
                $currentColor = $this->styles[$i]['color'] ?? $currentColor;
            }
            $streams[$i]['font']  = $currentFont;
            $streams[$i]['size']  = $currentSize;
            $streams[$i]['color'] = $currentColor;
        }

        return $streams;
    }

    /**
     * Get stream
     *
     * @param  array $fontReferences
     * @return string
     */
    public function getStream(array $fontReferences)
    {
        $x = $this->startX;
        $y = $this->startY;

        $fontReference = null;
        $fontSize      = null;
        foreach ($this->styles as $style) {
            if ((null === $fontReference) && !empty($style['font']) && isset($fontReferences[$style['font']])) {
                $fontReference = substr($fontReferences[$style['font']], 0, strpos($fontReferences[$style['font']], ' '));
            }
            if ((null === $fontSize) && !empty($style['size'])) {
                $fontSize = $style['size'];
            }
        }

        $stream = "\nBT\n    {$fontReference} {$fontSize} Tf\n    1 0 0 1 {$x} {$y} Tm\n    0 Tc 0 Tw 0 Tr\n";

        foreach ($this->streams as $i => $str) {
            if (isset($this->styles[$i]) && !empty($this->styles[$i]['font']) && isset($fontReferences[$this->styles[$i]['font']])) {
                $fRef    = substr($fontReferences[$this->styles[$i]['font']], 0, strpos($fontReferences[$this->styles[$i]['font']], ' '));
                $fSize   = (!empty($this->styles[$i]['size'])) ? $this->styles[$i]['size'] : $fontSize;
                $stream .= "    {$fRef} {$fSize} Tf\n";
            }
            if (isset($this->styles[$i]) && !empty($this->styles[$i]['color'])) {
                $stream .= $this->getColorStream($this->styles[$i]['color']);
            }
            $stream .= "    (" . $str['string'] . ")Tj\n";
        }

        $stream .= "ET\n";

        return $stream;
    }

    /**
     * Get the partial color stream
     *
     * @param  Color\ColorInterface $color
     * @return string
     */
    public function getColorStream(Color\ColorInterface $color)
    {
        $stream = '';

        if ($color instanceof Color\Rgb) {
            $stream .= '    ' . $color . " rg\n";
        } else if ($color instanceof Color\Cmyk) {
            $stream .= '    ' . $color . " k\n";
        } else if ($color instanceof Color\Gray) {
            $stream .= '    ' . $color . " g\n";
        }

        return $stream;
    }

}