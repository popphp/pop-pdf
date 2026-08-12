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

/**
 * Pdf extract ascii hex filter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class AsciiHex implements FilterInterface
{

    /**
     * Decode an ASCIIHexDecode stream
     *
     * @param  string $data
     * @param  array  $params
     * @return string
     */
    public function decode(string $data, array $params = []): string
    {
        $data = rtrim($data);

        if (str_ends_with($data, '>')) {
            $data = substr($data, 0, -1);
        }

        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $data);

        if ((strlen($hex) % 2) !== 0) {
            $hex .= '0';
        }

        return ($hex === '') ? '' : (string) hex2bin($hex);
    }

}
