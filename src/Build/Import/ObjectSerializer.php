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
namespace Pop\Pdf\Build\Import;

use Pop\Pdf\Build\Exception;
use Pop\Pdf\Extract\Value;

/**
 * Pdf build import object serializer class
 *
 * Turns a decoded Extract\Value tree (dict/array/Name/Reference/Keyword/scalar)
 * back into raw PDF object syntax - the write-side counterpart Extract\* never
 * needed for read-only text extraction.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ObjectSerializer
{

    /**
     * Serialize a single decoded value into PDF syntax
     *
     * @param  mixed $value
     * @throws Exception
     * @return string
     */
    public static function serializeValue(mixed $value): string
    {
        if ($value instanceof Value\Reference) {
            return $value->objNum . ' 0 R';
        } elseif ($value instanceof Value\Name) {
            return '/' . self::escapeName($value->name);
        } elseif ($value instanceof Value\Keyword) {
            return $value->keyword;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif ($value === null) {
            return 'null';
        } elseif (is_int($value)) {
            return (string) $value;
        } elseif (is_float($value)) {
            return self::formatFloat($value);
        } elseif (is_string($value)) {
            return '(' . self::escapeLiteralString($value) . ')';
        } elseif (is_array($value)) {
            return array_is_list($value) ? self::serializeArray($value) : self::serializeDict($value);
        }

        throw new Exception('Error: Cannot serialize a PDF value of type ' . get_debug_type($value) . '.');
    }

    /**
     * Serialize a dictionary (associative array)
     *
     * @param  array $dict
     * @return string
     */
    public static function serializeDict(array $dict): string
    {
        $parts = [];

        foreach ($dict as $key => $value) {
            $parts[] = '/' . self::escapeName((string) $key);
            $parts[] = self::serializeValue($value);
        }

        return '<< ' . implode(' ', $parts) . ' >>';
    }

    /**
     * Serialize an array (list)
     *
     * @param  array $items
     * @return string
     */
    public static function serializeArray(array $items): string
    {
        $parts = [];

        foreach ($items as $item) {
            $parts[] = self::serializeValue($item);
        }

        return '[ ' . implode(' ', $parts) . ' ]';
    }

    /**
     * Format a float without scientific notation or a trailing decimal point
     *
     * @param  float $value
     * @return string
     */
    protected static function formatFloat(float $value): string
    {
        if ($value == (int) $value) {
            return (string) (int) $value;
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return ($formatted === '' || $formatted === '-' || $formatted === '-0') ? '0' : $formatted;
    }

    /**
     * Escape a name's bytes per PDF name syntax (mirrors Tokenizer::readName()'s decoding, in reverse)
     *
     * @param  string $name
     * @return string
     */
    protected static function escapeName(string $name): string
    {
        $escaped = '';
        $length  = strlen($name);

        for ($i = 0; $i < $length; $i++) {
            $c   = $name[$i];
            $ord = ord($c);

            if (($ord <= 0x20) || ($ord >= 0x7F) || (strpos('()<>[]{}/%#', $c) !== false)) {
                $escaped .= '#' . strtoupper(sprintf('%02x', $ord));
            } else {
                $escaped .= $c;
            }
        }

        return $escaped;
    }

    /**
     * Escape a literal string's backslashes and parentheses
     *
     * @param  string $value
     * @return string
     */
    protected static function escapeLiteralString(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

}
