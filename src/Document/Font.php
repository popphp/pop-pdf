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
namespace Pop\Pdf\Document;

use Pop\Pdf\Build\Font\AbstractFont;
use Pop\Pdf\Build\Font\Parser;
use InvalidArgumentException;

/**
 * Pdf font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
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
    protected array $standardFonts = [
        'Arial', 'Arial,Italic', 'Arial,Bold', 'Arial,BoldItalic', 'Courier', 'CourierNew', 'Courier-Oblique',
        'CourierNew,Italic', 'Courier-Bold', 'CourierNew,Bold', 'Courier-BoldOblique', 'CourierNew,BoldItalic',
        'Helvetica', 'Helvetica-Oblique', 'Helvetica-Bold', 'Helvetica-BoldOblique', 'Symbol', 'Times-Roman',
        'Times-Bold', 'Times-Italic', 'Times-BoldItalic', 'TimesNewRoman', 'TimesNewRoman,Italic',
        'TimesNewRoman,Bold', 'TimesNewRoman,BoldItalic', 'ZapfDingbats'
    ];

    /**
     * Font
     * @var ?string
     */
    protected ?string $font = null;

    /**
     * Font name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Flag for a standard font
     * @var bool
     */
    protected bool $isStandard = false;

    /**
     * Flag for an embedded font file
     * @var bool
     */
    protected bool$isEmbedded = false;

    /**
     * Font parser
     * @var ?Parser
     */
    protected ?Parser $parser = null;

    /**
     * Constructor
     *
     * Instantiate a PDF font.
     *
     * @param ?string $font
     */
    public function __construct(?string $font = null)
    {
        if ($font !== null) {
            $this->setFont($font);
        }
    }

    /**
     * Get standard PDF fonts in an array
     *
     * @return array
     */
    public static function standardFonts(): array
    {
        return (new self())->getStandardFonts();
    }

    /**
     * Set font
     *
     * @param  string $font
     * @throws InvalidArgumentException|\Pop\Pdf\Build\Font\Exception
     * @return Font
     */
    public function setFont(string $font): Font
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
            throw new InvalidArgumentException(
                "Error: The font '" . $font . "' is not valid. It must be a standard PDF font or a font file."
            );
        }

        return $this;
    }

    /**
     * Get font
     *
     * @return ?string
     */
    public function getFont(): ?string
    {
        return $this->font;
    }

    /**
     * Get font name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Determine if the font is a standard font
     *
     * @return bool
     */
    public function isStandard(): bool
    {
        return $this->isStandard;
    }

    /**
     * Determine if the font is an embedded font
     *
     * @return bool
     */
    public function isEmbedded(): bool
    {
        return $this->isEmbedded;
    }

    /**
     * Get available standard fonts
     *
     * @return array
     */
    public function getStandardFonts(): array
    {
        return $this->standardFonts;
    }

    /**
     * Get the font parser
     *
     * @return ?AbstractFont
     */
    public function getParsedFont(): ?AbstractFont
    {
        return ($this->parser !== null) ? $this->parser->getFont() : null;
    }

    /**
     * Attempt to get string width
     *
     * @param  string $string
     * @param  mixed  $size
     * @throws Exception
     * @return mixed
     */
    public function getStringWidth(string $string, string $size): mixed
    {
        if ($this->parser !== null) {
            return $this->parser->getFont()->getStringWidth($string, $size);
        } else {
            $fontClass = '\Pop\Pdf\Build\Font\Standard\\' . str_replace([',', '-'], ['', ''], $this->name);
            if (!class_exists($fontClass)) {
                throw new Exception('Error: That standard font class was not found.');
            }
            $font   = new $fontClass();
            $widths = [];

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
     * @return ?Parser
     */
    public function parser(): ?Parser
    {
        return $this->parser;
    }

}
