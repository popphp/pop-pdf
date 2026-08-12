<?php
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
namespace Pop\Pdf\Extract\Font;

use Pop\Pdf\Build\Font\TrueType;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract simple font decoder class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class SimpleDecoder
{

    /**
     * Per-FontInfo cache of built byte-to-Unicode tables
     * @var \WeakMap
     */
    protected static \WeakMap $tableCache;

    /**
     * Decode a simple (non-Type0) font's raw byte string to Unicode text
     *
     * @param  string   $rawBytes
     * @param  FontInfo $info
     * @return string
     */
    public static function decode(string $rawBytes, FontInfo $info): string
    {
        self::$tableCache ??= new \WeakMap();

        if (!isset(self::$tableCache[$info])) {
            self::$tableCache[$info] = self::buildTable($info);
        }

        $table = self::$tableCache[$info];
        $out   = '';

        for ($i = 0; $i < strlen($rawBytes); $i++) {
            $byte = ord($rawBytes[$i]);

            if (($info->toUnicodeCMap !== null) && isset($info->toUnicodeCMap['bfMappings'][$byte])) {
                $out .= $info->toUnicodeCMap['bfMappings'][$byte];
                continue;
            }

            $unicode = $table[$byte] ?? null;

            if ($unicode !== null) {
                $out .= self::codepointToUtf8($unicode);
            }
        }

        return $out;
    }

    /**
     * Build a byte-to-Unicode table for a font, applying /Differences overrides
     *
     * @param  FontInfo $info
     * @return array
     */
    protected static function buildTable(FontInfo $info): array
    {
        $baseName    = null;
        $differences = null;

        if ($info->encoding instanceof Value\Name) {
            $baseName = $info->encoding->name;
        } elseif (is_array($info->encoding)) {
            $base     = $info->encoding['BaseEncoding'] ?? null;
            $baseName = ($base instanceof Value\Name) ? $base->name : null;

            $diffs = $info->encoding['Differences'] ?? null;
            if (is_array($diffs)) {
                $differences = $diffs;
            }
        }

        if ($baseName === null) {
            $baseName = self::detectFallbackEncoding($info->embeddedFontBytes);
        }

        $table = match ($baseName) {
            'MacRomanEncoding' => Encoding\MacRomanEncoding::TABLE,
            'MacExpertEncoding', 'StandardEncoding' => Encoding\StandardEncoding::TABLE,
            'Symbol' => self::buildSymbolTable(),
            'ZapfDingbats' => Encoding\ZapfDingbatsEncoding::TABLE,
            default => Encoding\WinAnsiEncoding::TABLE,
        };

        if ($differences !== null) {
            $code = 0;
            foreach ($differences as $entry) {
                if (is_numeric($entry)) {
                    $code = (int) $entry;
                } elseif ($entry instanceof Value\Name) {
                    $unicode = GlyphNames::resolve($entry->name);
                    if ($unicode !== null) {
                        $table[$code] = $unicode;
                    }
                    $code++;
                }
            }
        }

        return $table;
    }

    /**
     * Build the Symbol font's byte-to-Unicode table
     *
     * @return array
     */
    protected static function buildSymbolTable(): array
    {
        $table = [];
        for ($byte = 0x20; $byte <= 0x7A; $byte++) {
            $unicode = Encoding\SymbolEncoding::get($byte);
            if ($unicode !== null) {
                $table[$byte] = $unicode;
            }
        }
        return $table;
    }

    /**
     * Guess a fallback encoding from an embedded font's own cmap table when /Encoding is absent
     *
     * @param  ?string $embeddedFontBytes
     * @return string
     */
    protected static function detectFallbackEncoding(?string $embeddedFontBytes): string
    {
        if ($embeddedFontBytes === null) {
            return 'WinAnsiEncoding';
        }

        try {
            $font = new TrueType(null, $embeddedFontBytes);
        } catch (\Throwable $e) {
            return 'WinAnsiEncoding';
        }

        $cmapTable = $font->tables['cmap'] ?? null;
        if ($cmapTable === null) {
            return 'WinAnsiEncoding';
        }

        foreach ($cmapTable->subTables as $subTable) {
            if (($subTable->encoding === 'Mac Roman') && ($subTable->format === 0)) {
                return 'MacRomanEncoding';
            }
        }

        return 'WinAnsiEncoding';
    }

    /**
     * Convert a Unicode codepoint to a UTF-8 string
     *
     * @param  int $codepoint
     * @return string
     */
    protected static function codepointToUtf8(int $codepoint): string
    {
        $converted = @mb_convert_encoding(pack('N', $codepoint), 'UTF-8', 'UTF-32BE');
        return ($converted !== false) ? $converted : '';
    }

}
