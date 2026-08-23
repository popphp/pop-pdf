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
namespace Pop\Pdf\Extract;

use Pop\Pdf\Extract\Filter\Budget;
use Pop\Pdf\Extract\Filter\Registry;

/**
 * Pdf extract object stream class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ObjectStream
{

    /**
     * Expand an object stream into its map of object number to parsed object value
     *
     * @param  Value\Stream $stream
     * @param  ?Budget      $budget
     * @return array
     */
    public static function expand(Value\Stream $stream, ?Budget $budget = null): array
    {
        $dict = $stream->dict;
        $raw  = Registry::decode($stream->raw, $dict['Filter'] ?? null, $dict['DecodeParms'] ?? null, $budget);

        $n     = $dict['N'] ?? 0;
        $first = $dict['First'] ?? 0;

        // /N and /First come straight out of a producer-controlled dict and
        // may resolve to a non-numeric Value (a Reference, a Name, ...)
        // rather than an int/float - casting one of those directly to int
        // emits a PHP warning that a strict error handler (common in real
        // apps embedding this library) turns into an uncaught \Throwable,
        // escaping every catch(Exception) guard this codebase relies on.
        // Falling back to 0 for a non-numeric value degrades to "no pairs
        // found", matching how a malformed/unresolvable stream is already
        // handled elsewhere.
        $n     = (is_int($n) || is_float($n)) ? (int) $n : 0;
        $first = (is_int($first) || is_float($first)) ? max(0, (int) $first) : 0;

        // Tokenize only the header region (bytes before /First) rather than
        // the whole raw stream - otherwise, once a huge /N outruns the real
        // header pairs, the tokenizer keeps reading into the object data
        // that follows and misinterprets it as further bogus pairs instead
        // of ever reaching EOF where the header actually ends.
        $headerData      = substr($raw, 0, $first);
        $headerTokenizer = new Tokenizer($headerData, 0);
        $pairs = [];

        for ($i = 0; $i < $n; $i++) {
            $objNumToken = $headerTokenizer->next();
            if ($objNumToken['type'] === 'eof') {
                break;
            }
            $offsetToken = $headerTokenizer->next();
            if ($offsetToken['type'] === 'eof') {
                break;
            }
            $pairs[] = [(int) $objNumToken['value'], (int) $offsetToken['value']];
        }

        $objects = [];

        foreach ($pairs as [$objNum, $offset]) {
            $tokenizer = new Tokenizer($raw, $first + $offset);
            $parser    = new ObjectParser($tokenizer);
            $objects[$objNum] = $parser->parseValue();
        }

        return $objects;
    }

}
