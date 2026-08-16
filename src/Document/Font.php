<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document;

use Pop\Pdf\Build\Font\AbstractFont;
use Pop\Pdf\Build\Font\Exception as FontException;
use Pop\Pdf\Build\Font\Parser;
use Pop\Pdf\Build\Font\TrueType;
use InvalidArgumentException;

/**
 * Pdf font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
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
     * Cached standard-font instance (shared by getStringWidth() and hasGlyph())
     *
     * Lazily built by standardFontInstance() and invalidated automatically
     * whenever the selected standard font changes (it's only reused when it's
     * already an instance of the currently-selected font's class).
     *
     * @var ?\Pop\Pdf\Build\Font\Standard\AbstractStandard
     */
    protected ?\Pop\Pdf\Build\Font\Standard\AbstractStandard $standardFontInstance = null;

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
     * Determine if the font is an embedded CID (TrueType/OpenType) font
     *
     * Type1 (.pfb) embedded fonts and standard fonts are not CID fonts -
     * they stay on the single-byte encoding path.
     *
     * @return bool
     */
    public function isCid(): bool
    {
        return $this->isEmbedded && ($this->parser->getFont() instanceof TrueType);
    }

    /**
     * Split a UTF-8 string into its UTF-16BE code units
     *
     * Matches the BMP-only convention already used by getStringWidth() and
     * Build\Font\AbstractFont::getStringWidth() - sufficient for Cyrillic,
     * Greek, and the vast majority of non-Latin scripts short of rare
     * astral-plane characters (e.g. some CJK extensions, emoji).
     *
     * @param  string $string
     * @return array
     */
    public static function stringToCodeUnits(string $string): array
    {
        $codeUnits = [];
        $utf16     = iconv('UTF-8', 'UTF-16BE//IGNORE', $string);

        for ($i = 0; $i < strlen($utf16); $i += 2) {
            $codeUnits[] = (ord($utf16[$i]) << 8) | ord($utf16[$i + 1]);
        }

        return $codeUnits;
    }

    /**
     * Determine if this font has a glyph for the given UTF-16BE code unit
     *
     * Type1 embedded fonts are not validated (always returns true) - their
     * glyph coverage isn't tracked by codepoint the way standard and CID
     * fonts are.
     *
     * @param  int $codeUnit
     * @return bool
     */
    public function hasGlyph(int $codeUnit): bool
    {
        if ($this->isCid()) {
            return $this->getGlyphId($codeUnit) !== null;
        } else if ($this->isStandard) {
            return $this->standardFontInstance()->hasGlyph($codeUnit);
        }

        return true;
    }

    /**
     * Get the glyph ID for the given UTF-16BE code unit in this CID font
     *
     * @param  int $codeUnit
     * @return ?int
     */
    public function getGlyphId(int $codeUnit): ?int
    {
        if (!$this->isCid()) {
            return null;
        }

        $font = $this->parser->getFont();
        return $font['cmap']['glyphNumbers'][$codeUnit] ?? null;
    }

    /**
     * Assert every character in the string is covered by this font, throwing otherwise
     *
     * @param  string $string
     * @throws FontException
     * @return void
     */
    public function requireGlyphCoverage(string $string): void
    {
        foreach (self::stringToCodeUnits($string) as $codeUnit) {
            if (!$this->hasGlyph($codeUnit)) {
                throw new FontException(sprintf(
                    "Error: The font '%s' does not contain a glyph for character '%s' (U+%04X).",
                    $this->getName(), mb_chr($codeUnit), $codeUnit
                ));
            }
        }
    }

    /**
     * Convert a string to a big-endian glyph-ID hex string for this CID font's content stream
     *
     * @param  string $string
     * @throws FontException
     * @return string
     */
    public function stringToGidHex(string $string): string
    {
        $this->requireGlyphCoverage($string);

        $hex = '';
        foreach (self::stringToCodeUnits($string) as $codeUnit) {
            $hex .= sprintf('%04X', $this->getGlyphId($codeUnit));
        }

        return $hex;
    }

    /**
     * Instantiate this font's standard-font class (shared by getStringWidth() and hasGlyph())
     *
     * @throws Exception
     * @return \Pop\Pdf\Build\Font\Standard\AbstractStandard
     */
    protected function standardFontInstance(): \Pop\Pdf\Build\Font\Standard\AbstractStandard
    {
        $fontClass = '\Pop\Pdf\Build\Font\Standard\\' . str_replace([',', '-'], ['', ''], $this->name);
        if (!class_exists($fontClass)) {
            throw new Exception('Error: That standard font class was not found.');
        }
        if (!($this->standardFontInstance instanceof $fontClass)) {
            $this->standardFontInstance = new $fontClass();
        }
        return $this->standardFontInstance;
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
     * @param  string    $string
     * @param  int|float $size
     * @throws Exception
     * @return mixed
     */
    public function getStringWidth(string $string, int|float $size): mixed
    {
        if ($this->parser !== null) {
            return $this->parser->getFont()->getStringWidth($string, $size);
        } else {
            $font   = $this->standardFontInstance();
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
