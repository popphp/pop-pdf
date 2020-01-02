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
     * Edge Y boundary
     * @var int
     */
    protected $edgeY = null;
    /**
     * Current X
     * @var int
     */
    protected $currentX = null;

    /**
     * Current Y
     * @var int
     */
    protected $currentY = null;

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
     * Orphan index
     * @var int
     */
    protected $orphanIndex = null;

    /**
     * Constructor
     *
     * Instantiate a PDF text stream object.
     *
     * @param int $startX
     * @param int $startY
     * @param int $edgeX
     * @param int $edgeY
     */
    public function __construct($startX, $startY, $edgeX, $edgeY = null)
    {
        $this->startX = $startX;
        $this->startY = $startY;
        $this->edgeX  = $edgeX;
        $this->edgeY  = $edgeY;
    }

    /**
     * Get start X
     *
     * @return int
     */
    public function getStartX()
    {
        return $this->startX;
    }

    /**
     * Get start Y
     *
     * @return int
     */
    public function getStartY()
    {
        return $this->startY;
    }

    /**
     * Get edge X boundary
     *
     * @return int
     */
    public function getEdgeX()
    {
        return $this->edgeX;
    }

    /**
     * Get edge Y boundary
     *
     * @return int
     */
    public function getEdgeY()
    {
        return $this->edgeY;
    }

    /**
     * Get current X
     *
     * @return int
     */
    public function getCurrentX()
    {
        return $this->currentX;
    }

    /**
     * Get current Y
     *
     * @return int
     */
    public function getCurrentY()
    {
        return $this->currentY;
    }

    /**
     * Add text to the stream
     *
     * @param string $string
     * @param int    $y
     * @return Stream
     */
    public function addText($string, $y = null)
    {
        $this->streams[] = [
            'string' => $string,
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
     * @param  array $fonts
     * @param  array $fontReferences
     * @return string
     */
    public function getStream(array $fonts, array $fontReferences)
    {
        $this->currentX = $this->startX;
        $this->currentY = $this->startY;
        $fontName       = null;
        $fontReference  = null;
        $fontSize       = null;
        $curFont        = null;

        foreach ($this->styles as $style) {
            if ((null === $fontReference) && !empty($style['font']) && isset($fontReferences[$style['font']])) {
                $fontName      = $style['font'];
                $fontReference = substr($fontReferences[$fontName], 0, strpos($fontReferences[$fontName], ' '));
                $curFont       = $fonts[$fontName] ?? null;
            }
            if ((null === $fontSize) && !empty($style['size'])) {
                $fontSize = $style['size'];
            }
        }

        $stream  = "\nBT\n    {$fontReference} {$fontSize} Tf\n    1 0 0 1 {$this->startX} {$this->startY} Tm\n    0 Tc 0 Tw 0 Tr\n";

        foreach ($this->streams as $i => $str) {
            if (isset($this->styles[$i]) && !empty($this->styles[$i]['font']) && isset($fontReferences[$this->styles[$i]['font']])) {
                $fontName      = $this->styles[$i]['font'];
                $fontReference = substr($fontReferences[$fontName], 0, strpos($fontReferences[$fontName], ' '));
                $fontSize      = (!empty($this->styles[$i]['size'])) ? $this->styles[$i]['size'] : $fontSize;
                $curFont       = $fonts[$fontName] ?? null;
                $stream       .= "    {$fontReference} {$fontSize} Tf\n";
            }
            if (isset($this->styles[$i]) && !empty($this->styles[$i]['color'])) {
                $stream .= $this->getColorStream($this->styles[$i]['color']);
            }
            $curString = explode(' ', $str['string']);

            foreach ($curString as $j => $string) {
                if ((null !== $this->edgeX) && ($this->currentX >= $this->edgeX)) {
                    $nextY   = (null !== $str['y']) ? $str['y'] : $fontSize;
                    $stream .= "    0 -" . $nextY . " Td\n";
                    $this->currentX    = $this->startX;
                    $this->currentY   -= $nextY;
                    if ((null !== $this->edgeY) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
                        break;
                    }
                }

                if (!isset($curString[$j + 1])) {
                    if (isset($this->streams[$i + 1]) &&
                        preg_match('/[a-zA-Z0-9]/', substr($this->streams[$i + 1]['string'], 0, 1))) {
                        $string .= ' ';
                    }
                } else {
                    $string .= ' ';
                }

                $stream .= "    (" . $string . ")Tj\n";
                if (null !== $curFont) {
                    $this->currentX += $curFont->getStringWidth($string, $fontSize);
                }
            }
            if ((null !== $this->edgeY) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
                $this->orphanIndex = $i;
                break;
            }
        }

        $stream .= "ET\n";

        return $stream;
    }

    /**
     * Resume stream from orphaned index
     *
     * @param  array $fonts
     * @param  array $fontReferences
     * @throws Exception
     * @return string
     */
    public function resumeStream(array $fonts, array $fontReferences)
    {
        if (null === $this->orphanIndex) {
            throw new Exception('Error: This text stream object does not have an orphan index.');
        }
        if (!isset($this->streams[$this->orphanIndex])) {
            throw new Exception('Error: The orphan index (' . $this->orphanIndex . ') does not exist.');
        }

        $this->streams = array_slice($this->streams, $this->orphanIndex, null, true);
        return $this->getStream($fonts, $fontReferences);
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

    /**
     * Check if the text stream has orphan streams due to the page bottom
     *
     * @return boolean
     */
    public function hasOrphanIndex()
    {
        return (null !== $this->orphanIndex);
    }

}