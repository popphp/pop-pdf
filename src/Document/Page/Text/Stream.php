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

use Pop\Color\Color;

/**
 * Pdf page text stream class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    5.0.0
 */
class Stream
{

    /**
     * Start X
     * @var int|float|null
     */
    protected int|float|null $startX = null;

    /**
     * Start Y
     * @var int|float|null
     */
    protected int|float|null $startY = null;

    /**
     * Edge X boundary
     * @var ?int
     */
    protected int|float|null $edgeX = null;

    /**
     * Edge Y boundary
     * @var int|float|null
     */
    protected int|float|null $edgeY = null;
    /**
     * Current X
     * @var int|float|null
     */
    protected int|float|null $currentX = null;

    /**
     * Current Y
     * @var int|float|null
     */
    protected int|float|null $currentY = null;

    /**
    * Text streams
     * @var array
     */
    protected array $streams = [];

    /**
     * Text styles
     * @var array
     */
    protected array $styles = [];

    /**
     * Orphan index
     * @var array
     */
    protected array $orphanIndex = [];

    /**
     * Constructor
     *
     * Instantiate a PDF text stream object.
     *
     * @param int  $startX
     * @param int  $startY
     * @param int  $edgeX
     * @param ?int $edgeY
     */
    public function __construct(int $startX, int $startY, int $edgeX, ?int $edgeY = null)
    {
        $this->setStartX($startX);
        $this->setStartY($startY);
        $this->setEdgeX($edgeX);
        $this->setEdgeY($edgeY);
    }

    /**
     * Set start X
     *
     * @param  int|float $startX
     * @return Stream
     */
    public function setStartX(int|float $startX): Stream
    {
        $this->startX = $startX;
        return $this;
    }

    /**
     * Set start Y
     *
     * @param  int|float $startY
     * @return Stream
     */
    public function setStartY(int|float $startY): Stream
    {
        $this->startY = $startY;
        return $this;
    }

    /**
     * Set edge X boundary
     *
     * @param  int|float $edgeX
     * @return Stream
     */
    public function setEdgeX(int|float $edgeX): Stream
    {
        $this->edgeX = $edgeX;
        return $this;
    }

    /**
     * Set edge Y boundary
     *
     * @param  int|float $edgeY
     * @return Stream
     */
    public function setEdgeY(int|float $edgeY): Stream
    {
        $this->edgeY = $edgeY;
        return $this;
    }

    /**
     * Set current X
     *
     * @param  int|float $currentX
     * @return Stream
     */
    public function setCurrentX(int|float $currentX): Stream
    {
        $this->currentX = $currentX;
        return $this;
    }

    /**
     * Set current Y
     *
     * @param  int|float $currentY
     * @return Stream
     */
    public function setCurrentY(int|float $currentY): Stream
    {
        $this->currentY = $currentY;
        return $this;
    }

    /**
     * Get start X
     *
     * @return int|float|null
     */
    public function getStartX(): int|float|null
    {
        return $this->startX;
    }

    /**
     * Get start Y
     *
     * @return int|float|null
     */
    public function getStartY(): int|float|null
    {
        return $this->startY;
    }

    /**
     * Get edge X boundary
     *
     * @return int|float|null
     */
    public function getEdgeX(): int|float|null
    {
        return $this->edgeX;
    }

    /**
     * Get edge Y boundary
     *
     * @return int|float|null
     */
    public function getEdgeY(): int|float|null
    {
        return $this->edgeY;
    }

    /**
     * Get current X
     *
     * @return int|float|null
     */
    public function getCurrentX(): int|float|null
    {
        return $this->currentX;
    }

    /**
     * Get current Y
     *
     * @return int|float|null
     */
    public function getCurrentY(): int|float|null
    {
        return $this->currentY;
    }

    /**
     * Add text to the stream
     *
     * @param  string         $string
     * @param  int|float|null $y
     * @return Stream
     */
    public function addText(string $string, int|float|null $y = null): Stream
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
     * @param  string                $font
     * @param  int                   $size
     * @param  ?Color\ColorInterface $color
     * @return Stream
     */
    public function setCurrentStyle(string $font, int $size, ?Color\ColorInterface $color = null): Stream
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
     * Has text streams
     *
     * @return bool
     */
    public function hasTextStreams(): bool
    {
        return !empty($this->streams);
    }

    /**
     * Get text stream
     *
     * @return array
     */
    public function getTextStreams(): array
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
    public function getStream(array $fonts, array $fontReferences): string
    {
        if ($this->currentX === null) {
            $this->currentX = $this->startX;
        }
        if ($this->currentY === null) {
            $this->currentY = $this->startY;
        }
        $fontName       = null;
        $fontReference  = null;
        $fontSize       = null;
        $curFont        = null;

        foreach ($this->styles as $style) {
            if (($fontReference === null) && !empty($style['font']) && isset($fontReferences[$style['font']])) {
                $fontName      = $style['font'];
                $fontReference = substr($fontReferences[$fontName], 0, strpos($fontReferences[$fontName], ' '));
                $curFont       = $fonts[$fontName] ?? null;
            }
            if (($fontSize === null) && !empty($style['size'])) {
                $fontSize = $style['size'];
            }
        }

        $stream  = "\nBT\n    {$fontReference} {$fontSize} Tf\n    1 0 0 1 {$this->currentX} {$this->currentY} Tm\n    0 Tc 0 Tw 0 Tr\n";

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
                if (($this->edgeX !== null) && ($this->currentX >= $this->edgeX)) {
                    $nextY             = ($str['y'] !== null) ? $str['y'] : $fontSize;
                    $stream           .= "    0 -" . $nextY . " Td\n";
                    $this->currentX    = $this->startX;
                    $this->currentY   -= $nextY;
                    if (($this->edgeY !== null) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
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
                if ($curFont !== null) {
                    $this->currentX += $curFont->getStringWidth($string, $fontSize);
                }
            }
            if (($this->edgeY !== null) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
                $this->orphanIndex = (isset($j)) ? [$i, $j] : [$i, 0];
                break;
            }
        }

        $stream .= "ET\n";

        return $stream;
    }

    /**
     * Resume stream from orphaned index
     *
     * @return Stream
     */
    public function getOrphanStream(): Stream
    {
        $offset        = array_search($this->orphanIndex[0], array_keys($this->streams));
        $this->streams = array_slice($this->streams, $offset, null, true);

        if ($this->orphanIndex[1] > 0) {
            $strings = array_slice(explode(' ', $this->streams[$this->orphanIndex[0]]['string']), $this->orphanIndex[1], null, true);
            $this->streams[$this->orphanIndex[0]]['string'] = implode(' ', $strings);
        }

        $this->orphanIndex = [];
        return $this;
    }

    /**
     * Prepare stream
     *
     * @param  array $fonts
     * @return bool
     */
    public function hasOrphans(array $fonts): bool
    {
        $this->currentX = $this->startX;
        $this->currentY = $this->startY;
        $fontName       = null;
        $fontSize       = null;
        $curFont        = null;

        foreach ($this->styles as $style) {
            if (!empty($style['font'])) {
                $fontName = $style['font'];
                $curFont  = $fonts[$fontName] ?? null;
            }
            if (($fontSize === null) && !empty($style['size'])) {
                $fontSize = $style['size'];
            }
        }

        foreach ($this->streams as $i => $str) {
            if (isset($this->styles[$i]) && !empty($this->styles[$i]['font'])) {
                $fontName = $this->styles[$i]['font'];
                $fontSize = (!empty($this->styles[$i]['size'])) ? $this->styles[$i]['size'] : $fontSize;
                $curFont  = $fonts[$fontName] ?? null;
            }

            $curString = explode(' ', $str['string']);

            foreach ($curString as $j => $string) {
                if (($this->edgeX !== null) && ($this->currentX >= $this->edgeX)) {
                    $nextY             = ($str['y'] !== null) ? $str['y'] : $fontSize;
                    $this->currentX    = $this->startX;
                    $this->currentY   -= $nextY;
                    if (($this->edgeY !== null) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
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

                if ($curFont !== null) {
                    $this->currentX += $curFont->getStringWidth($string, $fontSize);
                }
            }
            if (($this->edgeY !== null) && ($this->currentY <= $this->edgeY) && ($this->currentX == $this->startX)) {
                $this->orphanIndex = (isset($j)) ? [$i, $j] : [$i, 0];
                break;
            }
        }

        return (!empty($this->orphanIndex));
    }

    /**
     * Get the partial color stream
     *
     * @param  Color\ColorInterface $color
     * @return string
     */
    public function getColorStream(Color\ColorInterface $color): string
    {
        $stream = '';

        if ($color instanceof Color\Rgb) {
            $stream .= '    ' . $color->render(Color\Rgb::PERCENT) . " rg\n";
        } else if ($color instanceof Color\Cmyk) {
            $stream .= '    ' . $color->render(Color\Cmyk::PERCENT) . " k\n";
        } else if ($color instanceof Color\Grayscale) {
            $stream .= '    ' . $color->render(Color\Grayscale::PERCENT) . " g\n";
        }

        return $stream;
    }

    /**
     * Check if the text stream has orphan streams due to the page bottom
     *
     * @return bool
     */
    public function hasOrphanIndex(): bool
    {
        return ($this->orphanIndex !== null);
    }

}
