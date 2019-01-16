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
namespace Pop\Pdf\Build\Font;

/**
 * Font abstract class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    3.2.0
 */
abstract class AbstractFont implements \ArrayAccess
{

    /**
     * Font properties
     * @var array
     */
    protected $properties = [
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
    protected $readOnly = [];

    /**
     * Array of allowed file types.
     * @var array
     */
    protected $allowedTypes = [
        'afm' => 'application/x-font-afm',
        'otf' => 'application/x-font-otf',
        'pfb' => 'application/x-font-pfb',
        'pfm' => 'application/x-font-pfm',
        'ttf' => 'application/x-font-ttf'
    ];

    /**
     * Full path of font file, i.e. '/path/to/fontfile.ext'
     * @var string
     */
    protected $fullpath = null;

    /**
     * Full, absolute directory of the font file, i.e. '/some/dir/'
     * @var string
     */
    protected $dir = null;

    /**
     * Full basename of font file, i.e. 'fontfile.ext'
     * @var string
     */
    protected $basename = null;

    /**
     * Full filename of font file, i.e. 'fontfile'
     * @var string
     */
    protected $filename = null;

    /**
     * Font file extension, i.e. 'ext'
     * @var string
     */
    protected $extension = null;

    /**
     * Font file size in bytes
     * @var int
     */
    protected $size = 0;

    /**
     * Font file mime type
     * @var string
     */
    protected $mime = 'text/plain';

    /**
     * Constructor
     *
     * Instantiate a font file object based on a pre-existing font file on disk.
     *
     * @param  string $font
     * @throws Exception
     */
    public function __construct($font)
    {
        if (!file_exists($font)) {
            throw new Exception('The font file does not exist.');
        }

        $this->properties['flags'] = new \ArrayObject([
            'isFixedPitch'  => false,
            'isSerif'       => false,
            'isSymbolic'    => false,
            'isScript'      => false,
            'isNonSymbolic' => false,
            'isItalic'      => false,
            'isAllCap'      => false,
            'isSmallCap'    => false,
            'isForceBold'   => false
        ], \ArrayObject::ARRAY_AS_PROPS);

        $this->fullpath  = $font;
        $parts           = pathinfo($font);
        $this->size      = filesize($font);
        $this->dir       = realpath($parts['dirname']);
        $this->basename  = $parts['basename'];
        $this->filename  = $parts['filename'];
        $this->extension = (isset($parts['extension']) && ($parts['extension'] != '')) ? $parts['extension'] : null;

        if (null === $this->extension) {
            throw new Exception('Error: That font file does not have an extension.');
        }

        if ((null !== $this->extension) && !isset($this->allowedTypes[strtolower($this->extension)])) {
            throw new Exception('Error: That font file type is not allowed.');
        }

        $this->mime = $this->allowedTypes[strtolower($this->extension)];
    }

    /**
     * Read data from the font file.
     *
     * @param  int $offset
     * @param  int $length
     * @return string
     */
    public function read($offset = null, $length = null)
    {
        if (null !== $offset) {
            $data = ((null !== $length) && ((int)$length >= 0)) ?
                file_get_contents($this->fullpath, null, null, $offset, $length) :
                file_get_contents($this->fullpath, null, null, $offset);
        } else {
            $data = file_get_contents($this->fullpath);
        }

        return $data;
    }

    /**
     * Static method to read and return a fixed-point number
     *
     * @param  int    $mantissaBits
     * @param  int    $fractionBits
     * @param  string $bytes
     * @return int
     */
    public function readFixed($mantissaBits, $fractionBits, $bytes)
    {
        $bitsToRead = $mantissaBits + $fractionBits;
        $number = $this->readInt(($bitsToRead >> 3), $bytes) / (1 << $fractionBits);
        return $number;
    }

    /**
     * Static method to read and return a signed integer
     *
     * @param  int    $size
     * @param  string $bytes
     * @return int
     */
    public function readInt($size, $bytes)
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
    public function shiftToSigned($values)
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
     * @param int $value
     * @return int
     */
    public function toEmSpace($value)
    {
        return ($this->properties['unitsPerEm'] == 1000) ? $value : ceil(($value / $this->properties['unitsPerEm']) * 1000);
    }

    /**
     * Get the widths for the glyphs
     *
     * @param  array $glyphs
     * @return array
     */
    public function getWidthsForGlyphs(array $glyphs)
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
    public function getStringWidth($string, $size)
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
    public function calcFlags()
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
     * @param  string $name
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * Offset get method
     *
     * @param  string $name
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function offsetGet($name)
    {
        return (isset($this->properties[$name])) ? $this->properties[$name] : null;
    }

    /**
     * Offset exists method
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->properties[$offset]);
    }

    /**
     * Offset unset method
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
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
    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Get method
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }
    /**
     * Isset method
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->offsetExists($name);
    }
    /**
     * Unset fields[$name]
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        $this->offsetUnset($name);
    }

}
