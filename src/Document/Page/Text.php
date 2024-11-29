<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document\Page;

use Pop\Color\Color;
use Pop\Color\Color\ColorInterface;
use OutOfRangeException;

/**
 * Pdf page text class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.1
 */
class Text
{

    /**
     * Text string value
     * @var ?string
     */
    protected ?string $string = null;

    /**
     * Text strings as array for streaming
     * @var array
     */
    protected array $strings = [];

    /**
     * Text strings with offset values
     * @var array
     */
    protected array $stringsWithOffsets = [];

    /**
     * Text font size
     * @var int|float
     */
    protected int|float $size = 12;

    /**
     * Text fill color
     * @var ?ColorInterface
     */
    protected ?ColorInterface $fillColor = null;

    /**
     * Text stroke color
     * @var ?ColorInterface
     */
    protected ?ColorInterface $strokeColor = null;

    /**
     * Text stroke
     * @var array
     */
    protected array $stroke = [
        'width'      => 0,
        'dashLength' => null,
        'dashGap'    => null
    ];

    /**
     * Basic wrap based on character length
     * @var int
     */
    protected int $charWrap = 0;

    /**
     * Leading for the lines for a character wrap
     * @var int
     */
    protected int $leading = 0;

    /**
     * Text alignment object
     * @var ?Text\Alignment
     */
    protected ?Text\Alignment $alignment = null;

    /**
     * Text wrap object
     * @var ?Text\Wrap
     */
    protected ?Text\Wrap $wrap = null;

    /**
     * Text stream object
     * @var ?Text\Stream
     */
    protected ?Text\Stream $stream = null;

    /**
     * Text parameters
     * @var array
     */
    protected array $textParams = [
        'c'    => 0,
        'w'    => 0,
        'h'    => 100,
        'v'    => 100,
        'rot'  => 0,
        'rend' => 0
    ];

    /**
     * Constructor
     *
     * Instantiate a PDF text object.
     *
     * @param ?string $string
     * @param ?string $size
     */
    public function __construct(?string $string = null, ?string $size = null)
    {
        if ($string !== null) {
            $this->setString($string);
        }
        if ($size !== null) {
            $this->setSize($size);
        }
    }

    /**
     * Set the text string
     *
     * @param  string $string
     * @return Text
     */
    public function setString(string $string): Text
    {
        if (function_exists('mb_strlen')) {
            if (mb_strlen($string, 'UTF-8') < strlen($string)) {
                $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
            }
        }
        $this->string = $string;
        return $this;
    }

    /**
     * Set the text strings
     *
     * @param  array $strings
     * @return Text
     */
    public function setStrings(array $strings): Text
    {
        if (function_exists('mb_strlen')) {
            $strings = array_map(function($value) {
                if ($value instanceof Text) {
                    $v = $value->getString();
                    if (mb_strlen($v, 'UTF-8') < strlen($v)) {
                        return mb_convert_encoding($v, 'UTF-8', mb_detect_encoding($v));
                    }
                    $value->setString($v);
                } else if (mb_strlen($value, 'UTF-8') < strlen($value)) {
                    $value = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value));
                }
                return $value;
            }, $strings);

        }
        $this->strings = $strings;
        return $this;
    }

    /**
     * Add a string with offset
     *
     * @param  string $string
     * @param  int    $offset
     * @return Text
     */
    public function addStringWithOffset(string $string, int $offset = 0): Text
    {
        if (function_exists('mb_strlen')) {
            if (mb_strlen($string, 'UTF-8') < strlen($string)) {
                $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
            }
        }
        $this->stringsWithOffsets[] = [
            'string' => $string,
            'offset' => $offset
        ];
        return $this;
    }

    /**
     * Get strings with offset
     *
     * @return array
     */
    public function getStringsWithOffset(): array
    {
        return $this->stringsWithOffsets;
    }

    /**
     * Set the text size
     *
     * @param  int|float $size
     * @return Text
     */
    public function setSize(int|float $size): Text
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Set the text fill color
     *
     * @param  ColorInterface $color
     * @return Text
     */
    public function setFillColor(ColorInterface $color): Text
    {
        $this->fillColor = $color;
        return $this;
    }

    /**
     * Set the text stroke color
     *
     * @param  ColorInterface $color
     * @return Text
     */
    public function setStrokeColor(ColorInterface $color): Text
    {
        $this->strokeColor = $color;
        return $this;
    }

    /**
     * Set the text stroke properties
     *
     * @param  int  $width
     * @param  ?int $dashLength
     * @param  ?int $dashGap
     * @return Text
     */
    public function setStroke(int $width, ?int $dashLength = null, ?int $dashGap = null): Text
    {
        $this->stroke = [
            'width'      => $width,
            'dashLength' => $dashLength,
            'dashGap'    => $dashGap
        ];
        return $this;
    }

    /**
     * Method to set the rotation of the text
     *
     * @param  int $rotation
     * @throws OutOfRangeException
     * @return Text
     */
    public function setRotation(int $rotation): Text
    {
        if (abs($rotation) > 90) {
            throw new OutOfRangeException('Error: The rotation parameter must be between -90 and 90 degrees.');
        }
        $this->textParams['rot'] = $rotation;
        return $this;
    }

    /**
     * Method to set the character wrap
     *
     * @param  int  $charWrap
     * @param  ?int $leading
     * @return Text
     */
    public function setCharWrap(int $charWrap, ?int $leading = null): Text
    {
        $this->charWrap = $charWrap;
        if ($leading !== null) {
            $this->setLeading($leading);
        }
        return $this;
    }

    /**
     * Method to set the character wrap leading
     *
     * @param  int $leading
     * @return Text
     */
    public function setLeading(int $leading): Text
    {
        $this->leading = $leading;
        return $this;
    }

    /**
     * Method to set the text alignment
     *
     * @param  Text\Alignment $alignment
     * @return Text
     */
    public function setAlignment(Text\Alignment $alignment): Text
    {
        $this->alignment = $alignment;
        return $this;
    }

    /**
     * Method to set the text wrap
     *
     * @param  Text\Wrap $wrap
     * @return Text
     */
    public function setWrap(Text\Wrap $wrap): Text
    {
        $this->wrap = $wrap;
        return $this;
    }

    /**
     * Method to set the text stream
     *
     * @param  Text\Stream $stream
     * @return Text
     */
    public function setTextStream(Text\Stream $stream): Text
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * Escape string
     *
     * @param  mixed $search
     * @param  mixed $replace
     * @return Text
     */
    public function escape(mixed $search = null, mixed $replace = null): Text
    {
        $searchAry  = ['(', ')'];
        $replaceAry = ['\(', '\)'];

        if (($search !== null) && ($replace !== null)) {
            if (!is_array($search)) {
                $search = [$search];
            }
            if (!is_array($replace)) {
                $replace = [$replace];
            }
            $searchAry  = array_merge($searchAry, $search);
            $replaceAry = array_merge($replaceAry, $replace);
        }

        $this->string = str_replace($searchAry, $replaceAry, $this->string);
        return $this;
    }

    /**
     * Get the text string
     *
     * @return ?string
     */
    public function getString(): ?string
    {
        return $this->string;
    }

    /**
     * Get the text string array
     *
     * @return array
     */
    public function getStrings(): array
    {
        return $this->strings;
    }

    /**
     * Get the text size
     *
     * @return int|float
     */
    public function getSize(): int|float
    {
        return $this->size;
    }

    /**
     * Get the text fill color
     *
     * @return ?ColorInterface
     */
    public function getFillColor(): ?ColorInterface
    {
        return $this->fillColor;
    }

    /**
     * Get the text stroke color
     *
     * @return ?ColorInterface
     */
    public function getStrokeColor(): ?ColorInterface
    {
        return $this->strokeColor;
    }

    /**
     * Get the text stroke properties
     *
     * @return array
     */
    public function getStroke(): array
    {
        return $this->stroke;
    }

    /**
     * Get the rotation of the text
     *
     * @return int
     */
    public function getRotation(): int
    {
        return $this->textParams['rot'];
    }

    /**
     * Get character wrap
     *
     * @return int
     */
    public function getCharWrap(): int
    {
        return $this->charWrap;
    }

    /**
     * Get number of wrapped lines from character wrap
     *
     * @return int
     */
    public function getNumberOfWrappedLines(): int
    {
        return count(explode("\n", wordwrap($this->string, $this->charWrap, "\n")));
    }

    /**
     * Get character wrap leading
     *
     * @return int
     */
    public function getLeading(): int
    {
        return $this->leading;
    }

    /**
     * Get text alignment
     *
     * @return ?Text\Alignment
     */
    public function getAlignment(): ?Text\Alignment
    {
        return $this->alignment;
    }

    /**
     * Get text wrap
     *
     * @return ?Text\Wrap
     */
    public function getWrap(): ?Text\Wrap
    {
        return $this->wrap;
    }

    /**
     * Get text stream
     *
     * @return ?Text\Stream
     */
    public function getTextStream(): ?Text\Stream
    {
        return $this->stream;
    }

    /**
     * Has text string
     *
     * @return bool
     */
    public function hasString(): bool
    {
        return ($this->string !== null);
    }

    /**
     * Has text string array
     *
     * @return bool
     */
    public function hasStrings(): bool
    {
        return !empty($this->strings);
    }

    /**
     * Has character wrap
     *
     * @return bool
     */
    public function hasCharWrap(): bool
    {
        return ($this->charWrap > 0);
    }

    /**
     * Has character wrap leading
     *
     * @return bool
     */
    public function hasLeading(): bool
    {
        return ($this->leading > 0);
    }

    /**
     * Has text alignment
     *
     * @return bool
     */
    public function hasAlignment(): bool
    {
        return ($this->alignment !== null);
    }

    /**
     * Has text wrap
     *
     * @return bool
     */
    public function hasWrap(): bool
    {
        return ($this->wrap !== null);
    }

    /**
     * Has text stream
     *
     * @return bool
     */
    public function hasTextStream(): bool
    {
        return ($this->stream !== null);
    }

    /**
     * Set the text parameters for rendering text content
     *
     * @param  int $c    (character spacing)
     * @param  int $w    (word spacing)
     * @param  int $h    (horz stretch)
     * @param  int $v    (vert stretch)
     * @param  int $rot  (rotation, -90 - 90)
     * @param  int $rend (render flag, 0 - 7)
     * @throws OutOfRangeException
     * @return Text
     */
    public function setTextParams(int $c = 0, int $w = 0, int $h = 100, int $v = 100, int $rot = 0, int $rend = 0): Text
    {
        // Check the rotation parameter.
        if (abs($rot) > 90) {
            throw new OutOfRangeException('Error: The rotation parameter must be between -90 and 90 degrees.');
        }

        // Check the render parameter.
        if ((!is_int($rend)) || (($rend > 7) || ($rend < 0))) {
            throw new OutOfRangeException('Error: The render parameter must be an integer between 0 and 7.');
        }

        // Set the text parameters.
        $this->textParams['c']    = $c;
        $this->textParams['w']    = $w;
        $this->textParams['h']    = $h;
        $this->textParams['v']    = $v;
        $this->textParams['rot']  = $rot;
        $this->textParams['rend'] = $rend;

        return $this;
    }

    /**
     * Start the text stream
     *
     * @param  string $fontReference
     * @param  int    $x
     * @param  int    $y
     * @return string
     */
    public function startStream(string $fontReference, int $x, int $y): string
    {
        $stream        = '';
        $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));

        $stream .= "\nBT\n    {$fontReference} {$this->size} Tf\n    " . $this->calculateTextMatrix() .
            " {$x} {$y} Tm\n    " . $this->textParams['c'] . " Tc " . $this->textParams['w'] .
            " Tw " . $this->textParams['rend'] . " Tr\n";

        return $stream;
    }

    /**
     * End the text stream
     *
     * @return string
     */
    public function endStream(): string
    {
        return "ET\n";
    }

    /**
     * Get the text stream
     *
     * @param  string $fontReference
     * @param  int    $x
     * @param  int    $y
     * @return string
     */
    public function getStream(string $fontReference, int $x, int $y): string
    {
        return $this->startStream($fontReference, $x, $y) . $this->getPartialStream() . $this->endStream();
    }

    /**
     * Get the partial text stream
     *
     * @param  ?string $fontReference
     * @return string
     */
    public function getPartialStream(?string $fontReference = null): string
    {
        $stream = '';

        if ($fontReference !== null) {
            $fontReference = substr($fontReference, 0, strpos($fontReference, ' '));
            $stream       .= "    {$fontReference} {$this->size} Tf\n";
        }

        $stream .= $this->getColorStream();

        if (count($this->stringsWithOffsets) > 0) {
            $stream .= "    [({$this->string})";
            foreach ($this->stringsWithOffsets as $string) {
                $stream .= " " . (0 - $string['offset']) . " (" . $string['string'] . ")";
            }
            $stream .= "]TJ\n";
        } else {
            if (($this->hasCharWrap()) && (strlen($this->string) > $this->charWrap)) {
                if ((int)$this->leading == 0) {
                    $this->leading = $this->size;
                }

                $strings = explode("\n", wordwrap($this->string, $this->charWrap, "\n"));

                foreach ($strings as $i => $string) {
                    $stream .= "    ({$string})Tj\n";
                    if ($i < count($strings)) {
                        $stream .= "    0 -" . $this->leading . " Td\n";
                    }
                }
            } else {
                $stream .= "    ({$this->string})Tj\n";
            }
        }

        return $stream;
    }

    /**
     * Get the partial color stream
     *
     * @return string
     */
    public function getColorStream(): string
    {
        $stream = '';

        if ($this->fillColor !== null) {
            if ($this->fillColor instanceof Color\Rgb) {
                $stream .= '    ' . $this->fillColor->render(Color\Rgb::PERCENT) . " rg\n";
            } else if ($this->fillColor instanceof Color\Cmyk) {
                $stream .= '    ' . $this->fillColor->render(Color\Cmyk::PERCENT) . " k\n";
            } else if ($this->fillColor instanceof Color\Grayscale) {
                $stream .= '    ' . $this->fillColor->render(Color\Grayscale::PERCENT) . " g\n";
            }
        }
        if ($this->strokeColor !== null) {
            if ($this->strokeColor instanceof Color\Rgb) {
                $stream .= '    ' . $this->strokeColor->render(Color\Rgb::PERCENT) . " RG\n";
            } else if ($this->strokeColor instanceof Color\Cmyk) {
                $stream .= '    ' . $this->strokeColor->render(Color\Cmyk::PERCENT) . " K\n";
            } else if ($this->strokeColor instanceof Color\Grayscale) {
                $stream .= '    ' . $this->strokeColor->render(Color\Grayscale::PERCENT) . " G\n";
            }
        }

        return $stream;
    }

    /**
     * Calculate text matrix
     *
     * @return string
     */
    protected function calculateTextMatrix(): string
    {
        // Define some variables.
        $a   = '';
        $b   = '';
        $c   = '';
        $d   = '';
        $neg = null;

        // Determine is the rotate parameter is negative or not.
        $neg = ($this->textParams['rot'] < 0) ? true : false;

        // Calculate the text matrix parameters.
        $rot = abs($this->textParams['rot']);

        if (($rot >= 0) && ($rot <= 45)) {
            $factor = round(($rot / 45), 2);
            if ($neg) {
                $a = 1;
                $b = '-' . $factor;
                $c = $factor;
                $d = 1;
            } else {
                $a = 1;
                $b = $factor;
                $c = '-' . $factor;
                $d = 1;
            }
        } else if (($rot > 45) && ($rot <= 90)) {
            $factor = round(((90 - $rot) / 45), 2);
            if ($neg) {
                $a = $factor;
                $b = -1;
                $c = 1;
                $d = $factor;
            } else {
                $a = $factor;
                $b = 1;
                $c = -1;
                $d = $factor;
            }
        }

        // Adjust the text matrix parameters according to the horizontal and vertical scale parameters.
        if ($this->textParams['h'] != 100) {
            $a = round(($a * ($this->textParams['h'] / 100)), 2);
            $b = round(($b * ($this->textParams['h'] / 100)), 2);
        }

        if ($this->textParams['v'] != 100) {
            $c = round(($c * ($this->textParams['v'] / 100)), 2);
            $d = round(($d * ($this->textParams['v'] / 100)), 2);
        }

        // Return the text matrix.
        return "{$a} {$b} {$c} {$d}";
    }

}
