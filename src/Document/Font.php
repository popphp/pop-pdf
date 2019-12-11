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
namespace Pop\Pdf\Document;

use Pop\Pdf\Build\Font\AbstractFont;
use Pop\Pdf\Build\Font\Parser;

/**
 * Pdf font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    4.0.0
 */
class Font
{

    /**
     * Standard font constants
     */
    const ARIAL                      = 'Arial';
    const ARIAL_ITALIC               = 'Arial,Italic';
    const ARIAL_BOLD                 = 'Arial,Bold';
    const ARIAL_BOLD_ITALIC          = 'Arial,BoldItalic';
    const COURIER                    = 'Courier';
    const COURIER_OBLIQUE            = 'Courier-Oblique';
    const COURIER_BOLD               = 'Courier-Bold';
    const COURIER_BOLD_OBLIQUE       = 'Courier-BoldOblique';
    const COURIER_NEW                = 'CourierNew';
    const COURIER_NEW_ITALIC         = 'CourierNew,Italic';
    const COURIER_NEW_BOLD           = 'CourierNew,Bold';
    const COURIER_NEW_BOLD_ITALIC    = 'CourierNew,BoldItalic';
    const HELVETICA                  = 'Helvetica';
    const HELVETICA_OBLIQUE          = 'Helvetica-Oblique';
    const HELVETICA_BOLD             = 'Helvetica-Bold';
    const HELVETICA_BOLD_OBLIQUE     = 'Helvetica-BoldOblique';
    const SYMBOL                     = 'Symbol';
    const TIMES_ROMAN                = 'Times-Roman';
    const TIMES_BOLD                 = 'Times-Bold';
    const TIMES_ITALIC               = 'Times-Italic';
    const TIMES_BOLD_ITALIC          = 'Times-BoldItalic';
    const TIMES_NEW_ROMAN            = 'TimesNewRoman';
    const TIMES_NEW_ROMAN_ITALIC     = 'TimesNewRoman,Italic';
    const TIMES_NEW_ROMAN_BOLD       = 'TimesNewRoman,Bold';
    const TIMES_NEW_ROMAN_BOLDITALIC = 'TimesNewRoman,BoldItalic';
    const ZAPF_DINGBATS              = 'ZapfDingbats';

    /**
     * Standard PDF fonts
     * @var array
     */
    protected $standardFonts = [
        'Arial', 'Arial,Italic', 'Arial,Bold', 'Arial,BoldItalic', 'Courier', 'CourierNew', 'Courier-Oblique',
        'CourierNew,Italic', 'Courier-Bold', 'CourierNew,Bold', 'Courier-BoldOblique', 'CourierNew,BoldItalic',
        'Helvetica', 'Helvetica-Oblique', 'Helvetica-Bold', 'Helvetica-BoldOblique', 'Symbol', 'Times-Roman',
        'Times-Bold', 'Times-Italic', 'Times-BoldItalic', 'TimesNewRoman', 'TimesNewRoman,Italic',
        'TimesNewRoman,Bold', 'TimesNewRoman,BoldItalic', 'ZapfDingbats'
    ];

    /**
     * Font
     * @var string
     */
    protected $font = null;

    /**
     * Font name
     * @var string
     */
    protected $name = null;

    /**
     * Flag for a standard font
     * @var boolean
     */
    protected $isStandard = false;

    /**
     * Flag for an embedded font file
     * @var boolean
     */
    protected $isEmbedded = false;

    /**
     * Font parser
     * @var Parser
     */
    protected $parser = null;

    /**
     * Constructor
     *
     * Instantiate a PDF font.
     *
     * @param  string $font
     */
    public function __construct($font = null)
    {
        if (null !== $font) {
            $this->setFont($font);
        }
    }

    /**
     * Get standard PDF fonts in an array
     *
     * @return array
     */
    public static function standardFonts()
    {
        return (new self())->getStandardFonts();
    }

    /**
     * Set font
     *
     * @param  string $font
     * @throws \InvalidArgumentException
     * @return Font
     */
    public function setFont($font)
    {
        $this->font = $font;
        if (in_array($font, $this->standardFonts)) {
            $this->isStandard = true;
            $this->name       = $font;
        } else if (file_exists($font)) {
            $this->isEmbedded = true;
            $this->parser     = new Parser($this->font);
            $this->name       = $this->parser->getFontName();
        } else {
            throw new \InvalidArgumentException(
                "Error: The font '" . $font . "' is not valid. It must be a standard PDF font or a font file."
            );
        }

        return $this;
    }

    /**
     * Get font
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font;
    }

    /**
     * Get font name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Determine if the font is a standard font
     *
     * @return boolean
     */
    public function isStandard()
    {
        return $this->isStandard;
    }

    /**
     * Determine if the font is an embedded font
     *
     * @return boolean
     */
    public function isEmbedded()
    {
        return $this->isEmbedded;
    }

    /**
     * Get available standard fonts
     *
     * @return array
     */
    public function getStandardFonts()
    {
        return $this->standardFonts;
    }

    /**
     * Get the font parser
     *
     * @return AbstractFont
     */
    public function getParsedFont()
    {
        return (null !== $this->parser) ? $this->parser->getFont() : null;
    }

    /**
     * Attempt to get string width
     *
     * @param  string $string
     * @param  mixed  $size
     * @throws Exception
     * @return mixed
     */
    public function getStringWidth($string, $size)
    {
        if (null !== $this->parser) {
            return $this->parser->getFont()->getStringWidth($string, $size);
        } else {
            $fontClass = '\Pop\Pdf\Build\Font\Standard\\' . str_replace([',', '-'], ['', ''], $this->name);
            if (!class_exists($fontClass)) {
                throw new Exception('Error: That standard font class was not found.');
            }
            $font      = new $fontClass();
            $widths    = [];

            $drawingString = iconv('UTF-8', 'UTF-16BE//IGNORE', $string);
            $characters    = [];

            for ($i = 0; $i < strlen($drawingString); $i++) {
                $characters[] = (ord($drawingString[$i++]) << 8 ) | ord($drawingString[$i]);
            }

            foreach ($characters as $character) {
                $widths[] = $font->getGlyphWidth($character);
            }

            return (array_sum($widths) / $font->getUnitsPerEm()) * $size;
        }
    }

    /**
     * Get the font parser
     *
     * @return Parser
     */
    public function parser()
    {
        return $this->parser;
    }

}