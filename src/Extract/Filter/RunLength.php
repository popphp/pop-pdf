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
namespace Pop\Pdf\Extract\Filter;

use Pop\Pdf\Extract\Exception;

/**
 * Pdf extract run length filter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class RunLength implements FilterInterface
{

    /**
     * Maximum decoded stream size in bytes
     */
    protected const MAX_DECODED_LENGTH = 67108864;

    /**
     * Decode a RunLengthDecode stream
     *
     * @param  string $data
     * @param  array  $params
     * @throws Exception
     * @return string
     */
    public function decode(string $data, array $params = []): string
    {
        $out    = '';
        $pos    = 0;
        $length = strlen($data);

        while ($pos < $length) {
            $len = ord($data[$pos]);
            $pos++;

            if ($len === 128) {
                break;
            } elseif ($len < 128) {
                $out .= substr($data, $pos, $len + 1);
                $pos += $len + 1;
            } elseif ($pos < $length) {
                $out .= str_repeat($data[$pos], 257 - $len);
                $pos++;
            }

            if (strlen($out) > self::MAX_DECODED_LENGTH) {
                throw new Exception('Error: Decoded RunLengthDecode stream exceeds the maximum allowed size.');
            }
        }

        return $out;
    }

}
