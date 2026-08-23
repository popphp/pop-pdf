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

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract CMap parser class
 *
 * Parses CMap-program byte streams (used by both /ToUnicode streams and
 * embedded /Encoding CMap streams for Type0 fonts) - reuses Extract\Tokenizer
 * for the underlying lexical grammar, the same way Content\Interpreter
 * reuses it for content-stream operators.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class CMapParser
{

    /**
     * Maximum span (hi - lo) allowed for a single bfrange/cidrange entry
     */
    protected const MAX_RANGE_SPAN = 65536;

    /**
     * Maximum total mappings a single parse() call may materialize
     */
    protected const MAX_TOTAL_MAPPINGS = 1000000;

    /**
     * Parse a CMap-program byte stream into codespace ranges and bf/cid mappings
     *
     * @param  string $data
     * @return array
     */
    public static function parse(string $data): array
    {
        $tokenizer    = new Tokenizer($data);
        $objectParser = new ObjectParser($tokenizer);

        $codespaceRanges = [];
        $bfMappings      = [];
        $cidMappings     = [];

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                break;
            }

            $tokenizer->setPosition($savedPos);

            try {
                $value = $objectParser->parseValue();
            } catch (Exception $e) {
                break;
            }

            if (!($value instanceof Value\Keyword)) {
                continue;
            }

            switch ($value->keyword) {
                case 'begincodespacerange':
                    self::readCodespaceRanges($objectParser, $codespaceRanges);
                    break;
                case 'beginbfchar':
                    self::readBfChar($objectParser, $bfMappings);
                    break;
                case 'beginbfrange':
                    self::readBfRange($objectParser, $bfMappings);
                    break;
                case 'begincidchar':
                    self::readCidChar($objectParser, $cidMappings);
                    break;
                case 'begincidrange':
                    self::readCidRange($objectParser, $cidMappings);
                    break;
                default:
                    break;
            }
        }

        return [
            'codespaceRanges' => $codespaceRanges,
            'bfMappings'      => $bfMappings,
            'cidMappings'     => $cidMappings,
        ];
    }

    /**
     * Read a begincodespacerange...endcodespacerange section
     *
     * @param  ObjectParser $parser
     * @param  array        $ranges
     * @return void
     */
    protected static function readCodespaceRanges(ObjectParser $parser, array &$ranges): void
    {
        try {
            while (true) {
                $value = $parser->parseValue();
                if (($value instanceof Value\Keyword) && ($value->keyword === 'endcodespacerange')) {
                    break;
                }
                if (!is_string($value)) {
                    continue;
                }
                $lo = $value;
                $hi = $parser->parseValue();
                if (!is_string($hi)) {
                    continue;
                }
                $ranges[] = ['lo' => $lo, 'hi' => $hi, 'length' => strlen($lo)];
            }
        } catch (Exception $e) {
            // Truncated/malformed section (no matching end* keyword before
            // EOF) - keep whatever was successfully parsed rather than
            // aborting the whole CMap.
            return;
        }
    }

    /**
     * Read a beginbfchar...endbfchar section
     *
     * @param  ObjectParser $parser
     * @param  array        $mappings
     * @return void
     */
    protected static function readBfChar(ObjectParser $parser, array &$mappings): void
    {
        try {
            while (true) {
                $value = $parser->parseValue();
                if (($value instanceof Value\Keyword) && ($value->keyword === 'endbfchar')) {
                    break;
                }
                if (!is_string($value)) {
                    continue;
                }
                $code = self::bytesToInt($value);
                $dst  = $parser->parseValue();
                if (is_string($dst)) {
                    $mappings[$code] = self::dstToUnicodeString($dst);
                }
            }
        } catch (Exception $e) {
            // Truncated/malformed section (no matching end* keyword before
            // EOF) - keep whatever was successfully parsed rather than
            // aborting the whole CMap.
            return;
        }
    }

    /**
     * Read a beginbfrange...endbfrange section
     *
     * @param  ObjectParser $parser
     * @param  array        $mappings
     * @return void
     */
    protected static function readBfRange(ObjectParser $parser, array &$mappings): void
    {
        try {
            while (true) {
                if (count($mappings) >= self::MAX_TOTAL_MAPPINGS) {
                    return;
                }

                $value = $parser->parseValue();
                if (($value instanceof Value\Keyword) && ($value->keyword === 'endbfrange')) {
                    break;
                }
                if (!is_string($value)) {
                    continue;
                }
                $loBytes = $value;
                $hi      = $parser->parseValue();
                $dst     = $parser->parseValue();

                $lo    = self::bytesToInt($loBytes);
                $hiInt = is_string($hi) ? self::bytesToInt($hi) : $lo;

                if (($hiInt < $lo) || (($hiInt - $lo) >= self::MAX_RANGE_SPAN)) {
                    // Malformed or adversarial range (inverted, or larger
                    // than any legitimate single-codespace range) - skip
                    // rather than materializing a huge/unbounded array.
                    continue;
                }

                if (is_array($dst)) {
                    foreach ($dst as $i => $element) {
                        if (count($mappings) >= self::MAX_TOTAL_MAPPINGS) {
                            break;
                        }
                        if (is_string($element)) {
                            $mappings[$lo + $i] = self::dstToUnicodeString($element);
                        }
                    }
                } elseif (is_string($dst)) {
                    $dstStart = self::bytesToInt($dst);
                    $dstLen   = strlen($dst);
                    for ($code = $lo; $code <= $hiInt; $code++) {
                        if (count($mappings) >= self::MAX_TOTAL_MAPPINGS) {
                            break;
                        }
                        $offset = $code - $lo;
                        $mappings[$code] = self::intToUnicodeString($dstStart + $offset, $dstLen);
                    }
                }
            }
        } catch (Exception $e) {
            // Truncated/malformed section (no matching end* keyword before
            // EOF) - keep whatever was successfully parsed rather than
            // aborting the whole CMap.
            return;
        }
    }

    /**
     * Read a begincidchar...endcidchar section
     *
     * @param  ObjectParser $parser
     * @param  array        $mappings
     * @return void
     */
    protected static function readCidChar(ObjectParser $parser, array &$mappings): void
    {
        try {
            while (true) {
                $value = $parser->parseValue();
                if (($value instanceof Value\Keyword) && ($value->keyword === 'endcidchar')) {
                    break;
                }
                if (!is_string($value)) {
                    continue;
                }
                $code = self::bytesToInt($value);
                $cid  = $parser->parseValue();
                if (is_numeric($cid)) {
                    $mappings[$code] = (int) $cid;
                }
            }
        } catch (Exception $e) {
            // Truncated/malformed section (no matching end* keyword before
            // EOF) - keep whatever was successfully parsed rather than
            // aborting the whole CMap.
            return;
        }
    }

    /**
     * Read a begincidrange...endcidrange section
     *
     * @param  ObjectParser $parser
     * @param  array        $mappings
     * @return void
     */
    protected static function readCidRange(ObjectParser $parser, array &$mappings): void
    {
        try {
            while (true) {
                if (count($mappings) >= self::MAX_TOTAL_MAPPINGS) {
                    return;
                }

                $value = $parser->parseValue();
                if (($value instanceof Value\Keyword) && ($value->keyword === 'endcidrange')) {
                    break;
                }
                if (!is_string($value)) {
                    continue;
                }
                $loBytes = $value;
                $hi      = $parser->parseValue();
                $cid     = $parser->parseValue();

                $lo       = self::bytesToInt($loBytes);
                $hiInt    = is_string($hi) ? self::bytesToInt($hi) : $lo;
                $cidStart = is_numeric($cid) ? (int) $cid : 0;

                if (($hiInt < $lo) || (($hiInt - $lo) >= self::MAX_RANGE_SPAN)) {
                    continue;
                }

                for ($code = $lo; $code <= $hiInt; $code++) {
                    if (count($mappings) >= self::MAX_TOTAL_MAPPINGS) {
                        break;
                    }
                    $mappings[$code] = $cidStart + ($code - $lo);
                }
            }
        } catch (Exception $e) {
            // Truncated/malformed section (no matching end* keyword before
            // EOF) - keep whatever was successfully parsed rather than
            // aborting the whole CMap.
            return;
        }
    }

    /**
     * Convert a byte string to a big-endian integer
     *
     * @param  string $bytes
     * @return int
     */
    protected static function bytesToInt(string $bytes): int
    {
        $value = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }
        return $value;
    }

    /**
     * Convert an integer to a fixed-length big-endian byte string, then decode it as UTF-16BE
     *
     * @param  int $value
     * @param  int $byteLen
     * @return string
     */
    protected static function intToUnicodeString(int $value, int $byteLen): string
    {
        $bytes = '';
        for ($i = $byteLen - 1; $i >= 0; $i--) {
            $bytes .= chr(($value >> ($i * 8)) & 0xFF);
        }
        return self::dstToUnicodeString($bytes);
    }

    /**
     * Decode a bfchar/bfrange destination byte string as UTF-16BE, rejecting malformed/placeholder values
     *
     * @param  string $bytes
     * @return string
     */
    protected static function dstToUnicodeString(string $bytes): string
    {
        if ((strlen($bytes) % 2) !== 0) {
            // Odd-length UTF-16BE input is malformed - mb_convert_encoding
            // would otherwise substitute a literal '?' that's indistinguishable
            // from real decoded content.
            return '';
        }

        if (rtrim($bytes, "\x00") === '') {
            // A destination that decodes to nothing but U+0000 is a common
            // real-world "unused code" placeholder, not actual text -
            // emitting it would inject raw NUL bytes into extracted text.
            return '';
        }

        return @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
    }

}
