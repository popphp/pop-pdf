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

use Pop\Pdf\Build\PdfObject\StreamObject;

/**
 * Pdf font parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    5.2.2
 */
class Parser
{

    /**
     * Font object
     * @var ?AbstractFont
     */
    protected ?AbstractFont $font = null;

    /**
     * Font reference index
     * @var ?int
     */
    protected ?int $fontIndex = null;

    /**
     * Font object index
     * @var ?int
     */
    protected ?int $fontObjectIndex = null;

    /**
     * Font descriptor index
     * @var ?int
     */
    protected ?int $fontDescIndex = null;

    /**
     * Font file index
     * @var ?int
     */
    protected ?int $fontFileIndex = null;

    /**
     * Font objects
     * @var array
     */
    protected array $objects = [];

    /**
     * Font compression flag
     * @var bool
     */
    protected bool $compression = false;

    /**
     * Constructor
     *
     * Instantiate a font parser object
     *
     * @param  string $fontFile
     * @param  bool   $compression
     * @throws Exception|\Pop\Utils\Exception
     */
    public function __construct(string $fontFile, bool $compression = false)
    {
        $ext = strtolower(substr($fontFile, (strrpos($fontFile, '.') + 1)));
        switch ($ext) {
            case 'ttf':
                $this->font = new TrueType($fontFile);
                break;
            case 'otf':
                $this->font = new TrueType\OpenType($fontFile);
                break;
            case 'pfb':
                $this->font = new Type1($fontFile);
                if ($this->font->afmPath === null) {
                    throw new Exception('The AFM font file was not found.');
                }
                break;
            default:
                throw new Exception('That font type is not supported.');
        }

        $this->setCompression($compression);
    }

    /**
     * Load font from stream
     *
     * @param  string $stream
     * @return void
     */
    public static function loadFromStream(string $stream): void
    {
        // TO-DO
    }

    /**
     * Set the font index
     *
     * @param  int $index
     * @return Parser
     */
    public function setFontIndex(int $index): Parser
    {
        $this->fontIndex = $index;
        return $this;
    }

    /**
     * Set the font object index
     *
     * @param  int $index
     * @return Parser
     */
    public function setFontObjectIndex(int $index): Parser
    {
        $this->fontObjectIndex = $index;
        return $this;
    }

    /**
     * Set the font descriptor index
     *
     * @param  int $index
     * @return Parser
     */
    public function setFontDescIndex(int $index): Parser
    {
        $this->fontDescIndex = $index;
        return $this;
    }

    /**
     * Set the font file index
     *
     * @param  int $index
     * @return Parser
     */
    public function setFontFileIndex(int $index): Parser
    {
        $this->fontFileIndex = $index;
        return $this;
    }

    /**
     * Set the compression
     *
     * @param  bool $compression
     * @return Parser
     */
    public function setCompression(bool $compression): Parser
    {
        $this->compression = $compression;
        return $this;
    }

    /**
     * Get the font object
     *
     * @return ?AbstractFont
     */
    public function getFont(): ?AbstractFont
    {
        return $this->font;
    }

    /**
     * Get the font index
     *
     * @return ?int
     */
    public function getFontIndex(): ?int
    {
        return $this->fontIndex;
    }

    /**
     * Get the font object index
     *
     * @return ?int
     */
    public function getFontObjectIndex(): ?int
    {
        return $this->fontObjectIndex;
    }

    /**
     * Get the font descriptor index
     *
     * @return ?int
     */
    public function getFontDescIndex(): ?int
    {
        return $this->fontDescIndex;
    }

    /**
     * Get the font file index
     *
     * @return ?int
     */
    public function getFontFileIndex(): ?int
    {
        return $this->fontFileIndex;
    }

    /**
     * Get the font objects
     *
     * @return array
     */
    public function getObjects(): array
    {
        if (count($this->objects) == 0) {
            $this->parse();
        }
        return $this->objects;
    }

    /**
     * Method to get the font reference.
     *
     * @return string
     */
    public function getFontReference(): string
    {
        return "/TT{$this->fontIndex} {$this->fontObjectIndex} 0 R";
    }

    /**
     * Method to get the font name.
     *
     * @return string
     */
    public function getFontName(): string
    {
        $fontName = ($this->font instanceof Type1) ? $this->font->info->postscriptName :
            $this->font->tables['name']->postscriptName;
        return $fontName;
    }

    /**
     * Method to get if the font is embeddable.
     *
     * @return bool
     */
    public function isEmbeddable(): bool
    {
        return $this->font->embeddable;
    }

    /**
     * Get whether or not the font objects are compressed
     *
     * @return bool
     */
    public function isCompressed(): bool
    {
        return $this->compression;
    }

    /**
     * Parse the font data and create the font objects
     *
     * @throws Exception
     * @return void
     */
    public function parse(): void
    {
        if (($this->fontIndex === null) || ($this->fontObjectIndex === null) ||
            ($this->fontDescIndex === null) || ($this->fontFileIndex === null)) {
            throw new Exception('Error: The font indices are not set');
        }

        if ($this->font instanceof Type1) {
            $fontType     = 'Type1';
            $fontName     = $this->font->info->postscriptName;
            $fontFile     = 'FontFile';
            $glyphWidths  = ['encoding' => 'StandardEncoding', 'widths' => $this->font->glyphWidths];
            $unCompStream = $this->font->fontData;
            $length1      = $this->font->length1;
            $length2      = " /Length2 " . $this->font->length2 . " /Length3 0";
        } else {
            $fontType     = 'TrueType';
            $fontName     = $this->font->tables['name']->postscriptName;
            $fontFile     = 'FontFile2';
            $glyphWidths  = $this->getGlyphWidthsFromCmap($this->font->tables['cmap']);
            $unCompStream = $this->font->read();
            $length1      = strlen($unCompStream);
            $length2      = null;
        }

        $this->objects[$this->fontObjectIndex] = StreamObject::parse(
            "{$this->fontObjectIndex} 0 obj\n<<\n    /Type /Font\n    /Subtype /{$fontType}\n    /FontDescriptor " .
            $this->fontDescIndex . " 0 R\n    /Name /TT{$this->fontIndex}\n    /BaseFont /" . $fontName .
            "\n    /FirstChar 32\n    /LastChar 255\n    /Widths [" . implode(' ', $glyphWidths['widths']) .
            "]\n    /Encoding /" . $glyphWidths['encoding'] . "\n>>\nendobj\n\n"
        );

        $bBox = '[' . $this->font->bBox->xMin . ' ' . $this->font->bBox->yMin . ' ' .
            $this->font->bBox->xMax . ' ' . $this->font->bBox->yMax . ']';

        if (($this->compression) && function_exists('gzcompress')) {
            $compStream  = gzcompress($unCompStream, 9);
            $fontFileObj = "{$this->fontFileIndex} 0 obj\n<</Length " . strlen($compStream) .
                " /Filter /FlateDecode /Length1 " . $length1 . $length2 . ">>\nstream\n" . $compStream . "\nendstream\nendobj\n\n";
        } else {
            $fontFileObj = "{$this->fontFileIndex} 0 obj\n<</Length " . strlen($unCompStream) . " /Length1 " .
                $length1 . $length2 . ">>\nstream\n" . $unCompStream . "\nendstream\nendobj\n\n";
        }

        $this->objects[$this->fontDescIndex] = StreamObject::parse(
            "{$this->fontDescIndex} 0 obj\n<<\n    /Type /FontDescriptor\n    /FontName /" . $fontName .
            "\n    /{$fontFile} {$this->fontFileIndex} 0 R\n    /MissingWidth {$this->font->missingWidth}\n    /StemV " .
            $this->font->stemV . "\n    /Flags " . $this->font->calcFlags() . "\n    /FontBBox {$bBox}\n    /Descent " .
            $this->font->descent . "\n    /Ascent {$this->font->ascent}\n    /CapHeight " . $this->font->capHeight .
            "\n    /ItalicAngle {$this->font->italicAngle}\n>>\nendobj\n\n"
        );

        $this->objects[$this->fontFileIndex] = StreamObject::parse($fontFileObj);
    }

    /**
     * Method to to get the glyph widths from the CMap
     *
     * @param  TrueType\Table\Cmap $cmap
     * @return array
     */
    protected function getGlyphWidthsFromCmap(TrueType\Table\Cmap $cmap): array
    {
        $gw       = ['encoding' => null, 'widths' => []];
        $uniTable = null;
        $msTable  = null;
        $macTable = null;

        foreach ($cmap->subTables as $index => $table) {
            if ($table->encoding == 'Microsoft Unicode') {
                $msTable = $index;
            }
            if ($table->encoding == 'Unicode') {
                $uniTable = $index;
            }
            if (($table->encoding == 'Mac Roman') && ($table->format == 0)) {
                $macTable = $index;
            }
        }

        if ($msTable !== null) {
            $gw['encoding'] = 'WinAnsiEncoding';
            foreach ($cmap->subTables[$msTable]->parsed['glyphNumbers'] as $key => $value) {
                if (isset($this->font->glyphWidths[$value])) {
                    $gw['widths'][$key] = $this->font->glyphWidths[$value];
                }
            }
        } else if ($uniTable !== null) {
            $gw['encoding'] = 'WinAnsiEncoding';
            foreach ($cmap->subTables[$uniTable]->parsed['glyphNumbers'] as $key => $value) {
                if (isset($this->font->glyphWidths[$value])) {
                    $gw['widths'][$key] = $this->font->glyphWidths[$value];
                }
            }
        } else if ($macTable !== null) {
            $gw['encoding'] = 'MacRomanEncoding';
            foreach ($cmap->subTables[$macTable]->parsed as $key => $value) {
                if (($this->font->glyphWidths[$value->ascii] != 0) &&
                    ($this->font->glyphWidths[$value->ascii] != $this->font->missingWidth)) {
                    if (isset($this->font->glyphWidths[$value->ascii])) {
                        $gw['widths'][$key] = $this->font->glyphWidths[$value->ascii];
                    }
                }
            }
        }

        return $gw;
    }

}
