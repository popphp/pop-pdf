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
namespace Pop\Pdf\Build\Font\Standard;

/**
 * Pdf abstract standard font class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
abstract class AbstractStandard
{

    /**
     * Font units per em
     * @var int
     */
    protected int $unitsPerEm = 1000;

    /**
     * Font glyph widths
     * @var array
     */
    protected array $glyphWidths = [];

    /**
     * Font character map
     * @var array
     */
    protected array $cmap = [];

    /**
     * Built-in encoding, for the symbolic fonts only (Symbol, ZapfDingbats)
     *
     * Maps each Unicode codepoint in the cmap to the single byte the font's own built-in
     * encoding assigns it. Empty for every other standard font, which use WinAnsiEncoding.
     *
     * @var array
     */
    protected array $encoding = [];

    /**
     * Built-in encoding inverted (byte => Unicode codepoint), built on first use
     * @var ?array
     */
    protected ?array $decoding = null;

    /**
     * Get units per em
     *
     * @return int
     */
    public function getUnitsPerEm(): int
    {
        return $this->unitsPerEm;
    }

    /**
     * Get character glyph width
     *
     * @param  int $code
     * @return mixed
     */
    public function getGlyphWidth(int $code): mixed
    {
        $code = $this->resolveCodepoint($code);

        if (($code !== null) && isset($this->glyphWidths[$this->cmap[$code]])) {
            return $this->glyphWidths[$this->cmap[$code]];
        } else {
            return 0;
        }
    }

    /**
     * Determine if this font's character map has a glyph for the given codepoint
     *
     * @param  int $code
     * @return bool
     */
    public function hasGlyph(int $code): bool
    {
        return ($this->resolveCodepoint($code) !== null);
    }

    /**
     * Determine if this is a symbolic font, one with its own built-in encoding (Symbol, ZapfDingbats)
     *
     * @return bool
     */
    public function isSymbolic(): bool
    {
        return !empty($this->encoding);
    }

    /**
     * Encode a UTF-8 string into the single-byte encoding this font's text is written in
     *
     * WinAnsiEncoding (Windows-1252) for most standard fonts; the font's built-in encoding for
     * the symbolic ones. Returns null if any character cannot be represented. The result is raw
     * bytes - escaping them for a PDF literal string is the caller's job.
     *
     * @param  string $string
     * @return ?string
     */
    public function encodeString(string $string): ?string
    {
        if (!$this->isSymbolic()) {
            $encoded = @iconv('UTF-8', 'Windows-1252', $string);
            return ($encoded !== false) ? $encoded : null;
        }

        $encoded = '';
        $utf16   = iconv('UTF-8', 'UTF-16BE//IGNORE', $string);

        // UTF-16BE code units, the same BMP-only convention the width and glyph-coverage checks use
        for ($i = 0; $i < strlen($utf16); $i += 2) {
            $codePoint = $this->resolveCodepoint((ord($utf16[$i]) << 8) | ord($utf16[$i + 1]));
            if ($codePoint === null) {
                return null;
            }
            $encoded .= chr($this->encoding[$codePoint]);
        }

        return $encoded;
    }

    /**
     * Resolve an input character to the cmap codepoint it selects, or null if the font has no glyph for it
     *
     * Unicode is the primary input. For a symbolic font, a printable ASCII character with no
     * Unicode entry is also accepted as the byte it is in the font's built-in encoding - before
     * v7 standard font text was written as raw bytes, so '3' was how a ZapfDingbats check mark
     * was drawn (and 'a' an alpha in Symbol). This is limited to ASCII because that is the only
     * range where the two readings cannot disagree: every ASCII cmap key encodes to itself, while
     * above it Symbol maps Latin-1 characters elsewhere (U+00D7 '×' is byte 0xB4, and byte 0xD7
     * is the dot operator). Raw high bytes were never valid UTF-8 input to begin with.
     *
     * @param  int $code
     * @return ?int
     */
    protected function resolveCodepoint(int $code): ?int
    {
        if (isset($this->cmap[$code])) {
            return $code;
        }

        if ($this->isSymbolic() && ($code >= 0x20) && ($code <= 0x7E)) {
            if ($this->decoding === null) {
                $this->decoding = array_flip($this->encoding);
            }
            return $this->decoding[$code] ?? null;
        }

        return null;
    }

}
