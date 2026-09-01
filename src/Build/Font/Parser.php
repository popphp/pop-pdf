<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Parser
{

    /**
     * Font object
     * @var Type1|TrueType|null
     */
    protected Type1|TrueType|null $font = null;

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
     * CID (descendant) font object index
     * @var ?int
     */
    protected ?int $cidFontObjectIndex = null;

    /**
     * ToUnicode CMap stream object index
     * @var ?int
     */
    protected ?int $toUnicodeIndex = null;

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
     * Set the CID (descendant) font object index
     *
     * @param  int $index
     * @return Parser
     */
    public function setCidFontObjectIndex(int $index): Parser
    {
        $this->cidFontObjectIndex = $index;
        return $this;
    }

    /**
     * Set the ToUnicode CMap stream object index
     *
     * @param  int $index
     * @return Parser
     */
    public function setToUnicodeIndex(int $index): Parser
    {
        $this->toUnicodeIndex = $index;
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
     * Get the CID (descendant) font object index
     *
     * @return ?int
     */
    public function getCidFontObjectIndex(): ?int
    {
        return $this->cidFontObjectIndex;
    }

    /**
     * Get the ToUnicode CMap stream object index
     *
     * @return ?int
     */
    public function getToUnicodeIndex(): ?int
    {
        return $this->toUnicodeIndex;
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
        if ($this->font instanceof Type1) {
            return $this->font->info->postscriptName;
        } elseif ($this->font instanceof TrueType) {
            return $this->font->tables['name']->postscriptName;
        }

        throw new Exception('Error: The font type is not supported.');
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

            $this->objects[$this->fontObjectIndex] = StreamObject::parse(
                "{$this->fontObjectIndex} 0 obj\n<<\n    /Type /Font\n    /Subtype /{$fontType}\n    /FontDescriptor " .
                $this->fontDescIndex . " 0 R\n    /Name /TT{$this->fontIndex}\n    /BaseFont /" . $fontName .
                "\n    /FirstChar 32\n    /LastChar 255\n    /Widths [" . implode(' ', $glyphWidths['widths']) .
                "]\n    /Encoding /" . $glyphWidths['encoding'] . "\n>>\nendobj\n\n"
            );
        } elseif ($this->font instanceof TrueType) {
            if (($this->cidFontObjectIndex === null) || ($this->toUnicodeIndex === null)) {
                throw new Exception('Error: The CID font indices are not set');
            }

            $fontName     = $this->font->tables['name']->postscriptName;
            $fontFile     = 'FontFile2';
            $unCompStream = $this->font->read();
            $length1      = strlen($unCompStream);
            $length2      = null;

            $this->objects[$this->fontObjectIndex] = StreamObject::parse(
                "{$this->fontObjectIndex} 0 obj\n<<\n    /Type /Font\n    /Subtype /Type0\n    /Name /TT{$this->fontIndex}\n    /BaseFont /" .
                $fontName . "\n    /Encoding /Identity-H\n    /DescendantFonts [" . $this->cidFontObjectIndex .
                " 0 R]\n    /ToUnicode " . $this->toUnicodeIndex . " 0 R\n>>\nendobj\n\n"
            );

            $this->objects[$this->cidFontObjectIndex] = StreamObject::parse(
                "{$this->cidFontObjectIndex} 0 obj\n<<\n    /Type /Font\n    /Subtype /CIDFontType2\n    /BaseFont /" .
                $fontName . "\n    /CIDSystemInfo <</Registry (Adobe) /Ordering (Identity) /Supplement 0>>\n    /FontDescriptor " .
                $this->fontDescIndex . " 0 R\n    /CIDToGIDMap /Identity\n    /DW " . $this->font->missingWidth .
                "\n    /W [" . $this->buildCidWidths($this->font) . "]\n>>\nendobj\n\n"
            );

            $this->objects[$this->toUnicodeIndex] = StreamObject::parse($this->buildToUnicodeCMap($this->font));
        } else {
            throw new Exception('Error: The font type is not supported.');
        }

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
     * Build the /W (per-CID width) array for a CID font from GID-indexed glyph widths
     *
     * hmtx-derived glyph widths are already contiguous from GID 0, so this
     * emits a single 'c [w1 w2 ... wn]' run rather than detecting ranges.
     *
     * @param  TrueType $font
     * @return string
     */
    protected function buildCidWidths(TrueType $font): string
    {
        return '0 [' . implode(' ', $font->glyphWidths) . ']';
    }

    /**
     * Build a /ToUnicode CMap stream mapping glyph IDs back to Unicode code units
     *
     * Inverts the font's own codepoint => GID cmap. The inversion is lossy:
     * several codepoints can share one GID (e.g. times.ttf maps both U+0020
     * and U+00A1 to GID 3), but a /ToUnicode CMap can only name one of them.
     * The LOWEST codepoint wins, which keeps the ASCII/Latin-1 member of any
     * such collision - so a space extracts as a space rather than as whatever
     * exotic codepoint happened to be iterated last.
     *
     * @param  TrueType $font
     * @return string
     */
    protected function buildToUnicodeCMap(TrueType $font): string
    {
        $gidToCodeUnit = [];
        foreach (($font->cmap['glyphNumbers'] ?? []) as $codeUnit => $gid) {
            if (!isset($gidToCodeUnit[$gid]) || ($codeUnit < $gidToCodeUnit[$gid])) {
                $gidToCodeUnit[$gid] = $codeUnit;
            }
        }
        ksort($gidToCodeUnit);

        $body = '';
        foreach (array_chunk($gidToCodeUnit, 100, true) as $chunk) {
            $body .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $gid => $codeUnit) {
                $body .= sprintf("<%04X> <%04X>\n", $gid, $codeUnit);
            }
            $body .= "endbfchar\n";
        }

        $cmapProgram = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n" .
            "/CIDSystemInfo <</Registry (Adobe) /Ordering (UCS) /Supplement 0>> def\n" .
            "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n" .
            "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n" . $body .
            "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";

        return "{$this->toUnicodeIndex} 0 obj\n<</Length " . strlen($cmapProgram) . ">>\nstream\n" .
            $cmapProgram . "\nendstream\nendobj\n\n";
    }

}
