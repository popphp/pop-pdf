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
namespace Pop\Pdf\Extract\Xref;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Filter\Budget;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract xref stream class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Stream
{

    /**
     * Parse a cross-reference stream at a byte position
     *
     * @param  string  $data
     * @param  int     $pos
     * @param  ?Budget $budget
     * @throws Exception
     * @return array
     */
    public static function parse(string $data, int $pos, ?Budget $budget = null): array
    {
        $tokenizer = new Tokenizer($data, $pos);

        $tokenizer->next(); // object number
        $tokenizer->next(); // generation number
        $objToken = $tokenizer->next();

        if (($objToken['type'] !== 'keyword') || ($objToken['value'] !== 'obj')) {
            throw new Exception('Error: Expected obj keyword for xref stream.');
        }

        $parser = new ObjectParser($tokenizer);
        $value  = $parser->parseValue();

        if (!($value instanceof Value\Stream)) {
            throw new Exception('Error: Expected a stream object for xref stream.');
        }

        $dict = $value->dict;
        $raw  = Registry::decode($value->raw, $dict['Filter'] ?? null, $dict['DecodeParms'] ?? null, $budget);

        $w = $dict['W'] ?? null;
        if (!is_array($w) || (count($w) < 3)) {
            throw new Exception('Error: Malformed xref stream /W array.');
        }

        $index = $dict['Index'] ?? [0, $dict['Size'] ?? 0];

        $offsets = [];
        $rowLen  = $w[0] + $w[1] + $w[2];
        $bytePos = 0;
        $rawLen  = strlen($raw);

        for ($i = 0; $i < count($index); $i += 2) {
            $startObj = $index[$i];
            $count    = $index[$i + 1];

            for ($j = 0; $j < $count; $j++) {
                if (($bytePos + $rowLen) > $rawLen) {
                    break 2;
                }

                $type   = ($w[0] === 0) ? 1 : self::readBigEndian($raw, $bytePos, $w[0]);
                $field2 = self::readBigEndian($raw, $bytePos + $w[0], $w[1]);
                $field3 = self::readBigEndian($raw, $bytePos + $w[0] + $w[1], $w[2]);
                $bytePos += $rowLen;

                $objNum = $startObj + $j;

                if (isset($offsets[$objNum])) {
                    continue;
                }

                if ($type === 1) {
                    $offsets[$objNum] = ['offset' => $field2];
                } elseif ($type === 2) {
                    $offsets[$objNum] = ['inStream' => $field2, 'index' => $field3];
                }
            }
        }

        return ['offsets' => $offsets, 'trailer' => $dict, 'endPos' => $tokenizer->getPosition()];
    }

    /**
     * Read a big-endian integer of a given byte size from a string
     *
     * @param  string $data
     * @param  int    $offset
     * @param  int    $size
     * @return int
     */
    protected static function readBigEndian(string $data, int $offset, int $size): int
    {
        if ($size === 0) {
            return 0;
        }

        $value = 0;
        for ($i = 0; $i < $size; $i++) {
            $value = ($value << 8) | ord($data[$offset + $i]);
        }

        return $value;
    }

}
