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
namespace Pop\Pdf\Extract\Font\Encoding;

/**
 * Pdf extract SymbolEncoding table class
 *
 * Covers the well-known Symbol-font convention of mapping each Latin
 * keyboard-position letter to the correspondingly-named Greek letter (e.g.
 * 'p' -> pi, 'w' -> omega). The 'j' and 'v' keys and all non-Greek math/
 * technical symbols in the Symbol font are intentionally unmapped.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class SymbolEncoding
{

    /**
     * Lowercase Latin keyboard letter to Greek Unicode codepoint table
     */
    protected const GREEK_BY_LETTER = [
        'a' => 0x3B1, 'b' => 0x3B2, 'g' => 0x3B3, 'd' => 0x3B4, 'e' => 0x3B5,
        'z' => 0x3B6, 'h' => 0x3B7, 'q' => 0x3B8, 'i' => 0x3B9, 'k' => 0x3BA,
        'l' => 0x3BB, 'm' => 0x3BC, 'n' => 0x3BD, 'x' => 0x3BE, 'o' => 0x3BF,
        'p' => 0x3C0, 'r' => 0x3C1, 's' => 0x3C3, 't' => 0x3C4, 'u' => 0x3C5,
        'f' => 0x3C6, 'c' => 0x3C7, 'y' => 0x3C8, 'w' => 0x3C9,
    ];

    /**
     * Get the Unicode codepoint for a Symbol-font byte
     *
     * @param  int $byte
     * @return ?int
     */
    public static function get(int $byte): ?int
    {
        if ($byte === 0x20) {
            return 0x0020;
        }

        if (($byte >= 0x61) && ($byte <= 0x7A)) {
            return self::GREEK_BY_LETTER[chr($byte)] ?? null;
        }

        if (($byte >= 0x41) && ($byte <= 0x5A)) {
            $lower = self::GREEK_BY_LETTER[strtolower(chr($byte))] ?? null;

            return ($lower !== null) ? ($lower - 0x20) : null;
        }

        return null;
    }

}
