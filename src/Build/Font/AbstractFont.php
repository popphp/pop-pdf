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
namespace Pop\Pdf\Build\Font;

use Pop\Utils\ArrayObject as Data;

/**
 * Font abstract class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
abstract class AbstractFont implements \ArrayAccess
{

    /**
     * Font properties
     * @var array
     */
    protected array $properties = [
        'info'             => null,
        'bBox'             => null,
        'ascent'           => 0,
        'descent'          => 0,
        'numberOfGlyphs'   => 0,
        'glyphWidths'      => [],
        'missingWidth'     => 0,
        'numberOfHMetrics' => 0,
        'italicAngle'      => 0,
        'capHeight'        => 0,
        'stemH'            => 0,
        'stemV'            => 0,
        'unitsPerEm'       => 1000,
        'flags'            => null,
        'embeddable'       => true,
    ];

    /**
     * Read-only properties
     * @var array
     */
    protected array $readOnly = [];

    /**
     * Array of allowed file types.
     * @var array
     */
    protected array $allowedTypes = [
        'afm' => 'application/x-font-afm',
        'otf' => 'application/x-font-otf',
        'pfb' => 'application/x-font-pfb',
        'pfm' => 'application/x-font-pfm',
        'ttf' => 'application/x-font-ttf'
    ];

    /**
     * Full path of font file, i.e. '/path/to/fontfile.ext'
     * @var ?string
     */
    protected ?string $fullpath = null;

    /**
     * Full, absolute directory of the font file, i.e. '/some/dir/'
     * @var ?string
     */
    protected ?string $dir = null;

    /**
     * Full basename of font file, i.e. 'fontfile.ext'
     * @var ?string
     */
    protected ?string $basename = null;

    /**
     * Full filename of font file, i.e. 'fontfile'
     * @var ?string
     */
    protected ?string $filename = null;

    /**
     * Font file extension, i.e. 'ext'
     * @var ?string
     */
    protected ?string $extension = null;

    /**
     * Font file size in bytes
     * @var int
     */
    protected int $size = 0;

    /**
     * Font file mime type
     * @var string
     */
    protected string $mime = 'text/plain';

    /**
     * Font stream
     * @var ?string
     */
    protected ?string $stream = null;

    /**
     * Constructor
     *
     * Instantiate a font file object based on a pre-existing font file on disk.
     *
     * @param  ?string $fontFile
     * @param  ?string $fontStream
     * @throws Exception|\Pop\Utils\Exception
     */
    public function __construct(?string $fontFile = null, ?string $fontStream = null)
    {
        $this->properties['flags'] = new Data([
            'isFixedPitch'  => false,
            'isSerif'       => false,
            'isSymbolic'    => false,
            'isScript'      => false,
            'isNonSymbolic' => false,
            'isItalic'      => false,
            'isAllCap'      => false,
            'isSmallCap'    => false,
            'isForceBold'   => false
        ]);

        if ($fontFile !== null) {
            if (!file_exists($fontFile)) {
                throw new Exception('The font file does not exist.');
            }

            $this->fullpath  = $fontFile;
            $parts           = pathinfo($fontFile);
            $this->size      = filesize($fontFile);
            $this->dir       = realpath($parts['dirname']);
            $this->basename  = $parts['basename'];
            $this->filename  = $parts['filename'];
            $this->extension = (isset($parts['extension']) && ($parts['extension'] != '')) ? $parts['extension'] : null;

            if ($this->extension === null) {
                throw new Exception('Error: That font file does not have an extension.');
            }

            if (($this->extension !== null) && !isset($this->allowedTypes[strtolower($this->extension)])) {
                throw new Exception('Error: That font file type is not allowed.');
            }

            $this->mime = $this->allowedTypes[strtolower($this->extension)];
        } else if ($fontStream !== null) {
            $this->stream = $fontStream;
        } else {
            throw new Exception('Error: You must pass either a font file or font stream.');
        }
    }

    /**
     * Read data from the font file.
     *
     * @param  ?int $offset
     * @param  ?int $length
     * @return ?string
     */
    public function read(?int $offset = null, ?int $length = null): ?string
    {
        if ($offset !== null) {
            if ($this->stream !== null) {
                $data = (($length !== null) && ((int)$length >= 0)) ?
                    substr($this->stream, $offset, $length) :
                    substr($this->stream, $offset);
            } else {
                $data = (($length !== null) && ((int)$length >= 0)) ?
                    file_get_contents($this->fullpath, false, null, $offset, $length) :
                    file_get_contents($this->fullpath, false, null, $offset);
            }
        } else {
            $data = ($this->stream !== null) ? $this->stream : file_get_contents($this->fullpath);
        }

        return $data;
    }

    /**
     * Static method to read and return a fixed-point number
     *
     * @param  int    $mantissaBits
     * @param  int    $fractionBits
     * @param  string $bytes
     * @return float|int
     */
    public function readFixed(int $mantissaBits, int $fractionBits, string $bytes): float|int
    {
        return $this->readInt((($mantissaBits + $fractionBits) >> 3), $bytes) / (1 << $fractionBits);
    }

    /**
     * Static method to read and return a signed integer
     *
     * @param  int    $size
     * @param  string $bytes
     * @return int
     */
    public function readInt(int $size, string $bytes): int
    {
        $number = ord($bytes[0]);

        if (($number & 0x80) == 0x80) {
            $number = (~ $number) & 0xff;
            for ($i = 1; $i < $size; $i++) {
                $number = ($number << 8) | ((~ ord($bytes[$i])) & 0xff);
            }
            $number = ~$number;
        } else {
            for ($i = 1; $i < $size; $i++) {
                $number = ($number << 8) | ord($bytes[$i]);
            }
        }

        return $number;
    }

    /**
     * Method to shift an unpacked signed short from little endian to big endian
     *
     * @param  int|array $values
     * @return int|array
     */
    public function shiftToSigned(int|array $values): int|array
    {
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if ($value >= pow(2, 15)) {
                    $values[$key] -= pow(2, 16);
                }
            }
        } else {
            if ($values >= pow(2, 15)) {
                $values -= pow(2, 16);
            }
        }

        return $values;
    }

    /**
     * Method to convert a value to the representative value in EM.
     *
     * @param  int $value
     * @return int
     */
    public function toEmSpace(int $value): int
    {
        return ($this->properties['unitsPerEm'] == 1000) ? $value : ceil(($value / $this->properties['unitsPerEm']) * 1000);
    }

    /**
     * Get the widths for the glyphs
     *
     * @param  array $glyphs
     * @return array
     */
    public function getWidthsForGlyphs(array $glyphs): array
    {
        $widths = [];

        foreach ($glyphs as $glyph) {
            if (isset($this->properties['cmap']['glyphNumbers'][$glyph]) &&
                isset($this->properties['rawGlyphWidths'][$this->properties['cmap']['glyphNumbers'][$glyph]])) {
                $widths[] = $this->properties['rawGlyphWidths'][$this->properties['cmap']['glyphNumbers'][$glyph]];
            } else {
                $widths[] = $this->properties['missingWidth'];
            }
        }

        return $widths;
    }

    /**
     * Attempt to get string width
     *
     * @param  string $string
     * @param  mixed  $size
     * @return mixed
     */
    public function getStringWidth(string $string, mixed $size): mixed
    {
        $width = null;

        $drawingString = iconv('UTF-8', 'UTF-16BE//IGNORE', $string);
        $characters    = [];

        for ($i = 0; $i < strlen($drawingString); $i++) {
            $characters[] = (ord($drawingString[$i++]) << 8 ) | ord($drawingString[$i]);
        }

        if (count($this->properties['rawGlyphWidths']) > 0) {
            $widths = $this->getWidthsForGlyphs($characters);
            $width  = (array_sum($widths) / $this->properties['unitsPerEm']) * $size;
        }

        return $width;
    }

    /**
     * Method to calculate the font flags
     *
     * @return int
     */
    public function calcFlags(): int
    {
        $flags = 0;

        if ($this->properties['flags']['isFixedPitch']) {
            $flags += 1 << 0;
        }

        $flags += 1 << 5;

        if ($this->properties['flags']['isItalic']) {
            $flags += 1 << 6;
        }

        return $flags;
    }

    /**
     * Offset set method
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->properties[$offset] = $value;
    }

    /**
     * Offset get method
     *
     * @param  string $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->properties[$offset] ?? null;
    }

    /**
     * Offset exists method
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->properties[$offset]);
    }

    /**
     * Offset unset method
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->properties[$offset])) {
            unset($this->properties[$offset]);
        }
    }

    /**
     * Set method
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Get method
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->offsetGet($name);
    }
    /**
     * Isset method
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }
    /**
     * Unset fields[$name]
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->offsetUnset($name);
    }

}
