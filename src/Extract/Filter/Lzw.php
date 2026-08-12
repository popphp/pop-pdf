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
namespace Pop\Pdf\Extract\Filter;

use Pop\Pdf\Extract\Exception;

/**
 * Pdf extract lzw filter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Lzw implements FilterInterface
{

    /**
     * LZW clear-table code
     */
    protected const CLEAR = 256;

    /**
     * LZW end-of-data code
     */
    protected const EOD = 257;

    /**
     * Maximum decoded stream size in bytes
     */
    protected const MAX_DECODED_LENGTH = 67108864;

    /**
     * Decode an LZWDecode stream
     *
     * @param  string $data
     * @param  array  $params
     * @throws Exception
     * @return string
     */
    public function decode(string $data, array $params = []): string
    {
        $earlyChange = $params['EarlyChange'] ?? 1;
        $bytes       = strlen($data);
        $bitPos      = 0;
        $codeWidth   = 9;
        $table       = $this->initialTable();
        $prev        = null;
        $out         = '';

        while (true) {
            $code = $this->readCode($data, $bytes, $bitPos, $codeWidth);

            if (($code === null) || ($code === self::EOD)) {
                break;
            }

            if ($code === self::CLEAR) {
                $table     = $this->initialTable();
                $codeWidth = 9;
                $prev      = null;
                continue;
            }

            if (isset($table[$code])) {
                $entry = $table[$code];
            } elseif (($code === count($table)) && ($prev !== null)) {
                $entry = $prev . $prev[0];
            } else {
                throw new Exception('Error: Invalid LZW code encountered.');
            }

            $out .= $entry;

            if (strlen($out) > self::MAX_DECODED_LENGTH) {
                throw new Exception('Error: Decoded LZWDecode stream exceeds the maximum allowed size.');
            }

            if ($prev !== null) {
                // Codes above 4095 are unreachable at the max 12-bit code
                // width, so a stream that never sends an explicit Clear code
                // must not be allowed to grow the table without bound -
                // treat hitting the cap as an implicit clear/reset instead.
                if (count($table) >= 4096) {
                    $table     = $this->initialTable();
                    $codeWidth = 9;
                } else {
                    $table[] = $prev . $entry[0];
                }
            }

            $prev = $entry;

            $nextSize = count($table) + $earlyChange;
            if ($nextSize > 2047) {
                $codeWidth = 12;
            } elseif ($nextSize > 1023) {
                $codeWidth = 11;
            } elseif ($nextSize > 511) {
                $codeWidth = 10;
            } else {
                $codeWidth = 9;
            }
        }

        return $out;
    }

    /**
     * Build the initial 256-entry single-byte LZW table
     *
     * @return array
     */
    protected function initialTable(): array
    {
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[$i] = chr($i);
        }
        $table[256] = '';
        $table[257] = '';

        return $table;
    }

    /**
     * Read the next fixed-width code from the bitstream
     *
     * @param  string $data
     * @param  int    $bytes
     * @param  int    $bitPos
     * @param  int    $codeWidth
     * @return ?int
     */
    protected function readCode(string $data, int $bytes, int &$bitPos, int $codeWidth): ?int
    {
        $value    = 0;
        $bitsRead = 0;

        while ($bitsRead < $codeWidth) {
            $bytePos = intdiv($bitPos, 8);
            if ($bytePos >= $bytes) {
                return null;
            }

            $bitOffset  = $bitPos % 8;
            $bitsLeft   = 8 - $bitOffset;
            $bitsToTake = min($bitsLeft, $codeWidth - $bitsRead);
            $byte       = ord($data[$bytePos]);
            $shift      = $bitsLeft - $bitsToTake;
            $mask       = (1 << $bitsToTake) - 1;
            $chunk      = ($byte >> $shift) & $mask;

            $value     = ($value << $bitsToTake) | $chunk;
            $bitsRead += $bitsToTake;
            $bitPos   += $bitsToTake;
        }

        return $value;
    }

}
