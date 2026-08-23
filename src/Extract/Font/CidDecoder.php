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
namespace Pop\Pdf\Extract\Font;

use Pop\Pdf\Build\Font\TrueType;

/**
 * Pdf extract CID font decoder class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class CidDecoder
{

    /**
     * Per-FontInfo cache of built glyph-ID-to-Unicode reverse cmaps
     * @var \WeakMap
     */
    protected static \WeakMap $reverseCmapCache;

    /**
     * Decode a Type0/CID composite font's raw byte string to Unicode text
     *
     * @param  string   $rawBytes
     * @param  FontInfo $info
     * @return string
     */
    public static function decode(string $rawBytes, FontInfo $info): string
    {
        $codes = self::splitCodes($rawBytes, $info->encoding);

        $reverseCmap      = null;
        $reverseCmapBuilt = false;

        $out = '';

        foreach ($codes as $code) {
            if (($info->toUnicodeCMap !== null) && isset($info->toUnicodeCMap['bfMappings'][$code])) {
                $out .= $info->toUnicodeCMap['bfMappings'][$code];
                continue;
            }

            if (!$reverseCmapBuilt) {
                $reverseCmap      = self::getReverseCmap($info);
                $reverseCmapBuilt = true;
            }

            $cid = self::codeToCid($code, $info->encoding);
            $gid = self::cidToGid($cid, $info->cidToGidMap);

            $unicode = $reverseCmap[$gid] ?? null;
            if ($unicode !== null) {
                $out .= self::codepointToUtf8($unicode);
            }
        }

        return $out;
    }

    /**
     * Split raw bytes into fixed-width character codes per the encoding's codespace range
     *
     * @param  string $rawBytes
     * @param  mixed  $encoding
     * @return array
     */
    protected static function splitCodes(string $rawBytes, mixed $encoding): array
    {
        $length = 2;

        if (is_array($encoding) && !empty($encoding['codespaceRanges'])) {
            $length = $encoding['codespaceRanges'][0]['length'] ?? 2;
        }

        if ($length < 1) {
            $length = 2;
        }

        $codes = [];
        for ($i = 0; ($i + $length) <= strlen($rawBytes); $i += $length) {
            $chunk = substr($rawBytes, $i, $length);
            $value = 0;
            for ($j = 0; $j < $length; $j++) {
                $value = ($value << 8) | ord($chunk[$j]);
            }
            $codes[] = $value;
        }

        return $codes;
    }

    /**
     * Map a character code to a CID via the encoding's cidMappings, or identity
     *
     * @param  int   $code
     * @param  mixed $encoding
     * @return int
     */
    protected static function codeToCid(int $code, mixed $encoding): int
    {
        if (is_array($encoding) && isset($encoding['cidMappings'][$code])) {
            return $encoding['cidMappings'][$code];
        }

        // Identity-H/V (or any predefined encoding this decoder doesn't
        // specifically recognize): CID == code, directly.
        return $code;
    }

    /**
     * Map a CID to a glyph ID via the CIDToGIDMap stream bytes, or identity
     *
     * @param  int   $cid
     * @param  mixed $cidToGidMap
     * @return int
     */
    protected static function cidToGid(int $cid, mixed $cidToGidMap): int
    {
        if (!is_string($cidToGidMap) || ($cidToGidMap === 'Identity')) {
            return $cid;
        }

        $offset = $cid * 2;
        if (($offset + 2) > strlen($cidToGidMap)) {
            return $cid;
        }

        return (ord($cidToGidMap[$offset]) << 8) | ord($cidToGidMap[$offset + 1]);
    }

    /**
     * Get a cached reverse cmap for a FontInfo, building and caching it if needed
     *
     * @param  FontInfo $info
     * @return array
     */
    protected static function getReverseCmap(FontInfo $info): array
    {
        self::$reverseCmapCache ??= new \WeakMap();

        if (!isset(self::$reverseCmapCache[$info])) {
            self::$reverseCmapCache[$info] = self::buildReverseCmap($info->embeddedFontBytes);
        }

        return self::$reverseCmapCache[$info];
    }

    /**
     * Build a glyph-ID-to-Unicode reverse cmap from an embedded TrueType font's own cmap table
     *
     * @param  ?string $embeddedFontBytes
     * @return array
     */
    protected static function buildReverseCmap(?string $embeddedFontBytes): array
    {
        if ($embeddedFontBytes === null) {
            return [];
        }

        try {
            $font = new TrueType(null, $embeddedFontBytes);
        } catch (\Throwable $e) {
            return [];
        }

        $cmapTable = $font->tables['cmap'] ?? null;
        if ($cmapTable === null) {
            return [];
        }

        $reverse = [];
        foreach ($cmapTable->subTables as $subTable) {
            if (($subTable->encoding === 'Microsoft Unicode') || ($subTable->encoding === 'Unicode')) {
                foreach (($subTable->parsed['glyphNumbers'] ?? []) as $unicode => $glyphId) {
                    if (!isset($reverse[$glyphId])) {
                        $reverse[$glyphId] = $unicode;
                    }
                }
                // Only stop at the first Unicode-ish subtable if it actually
                // produced usable glyph numbers - some subtable formats
                // (anything other than 0/4/6) never populate `parsed`,
                // which would otherwise discard a perfectly good later
                // subtable and silently lose all text for this font.
                if (!empty($reverse)) {
                    break;
                }
            }
        }

        return $reverse;
    }

    /**
     * Convert a Unicode codepoint to a UTF-8 string
     *
     * @param  int $codepoint
     * @return string
     */
    protected static function codepointToUtf8(int $codepoint): string
    {
        return @mb_convert_encoding(pack('N', $codepoint), 'UTF-8', 'UTF-32BE');
    }

}
