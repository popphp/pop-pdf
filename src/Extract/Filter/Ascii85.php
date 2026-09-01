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
namespace Pop\Pdf\Extract\Filter;

use Pop\Pdf\Extract\Exception;

/**
 * Pdf extract ascii 85 filter class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Ascii85 implements FilterInterface
{

    /**
     * Maximum decoded stream size in bytes
     */
    protected const MAX_DECODED_LENGTH = 67108864;

    /**
     * Decode an ASCII85Decode stream
     *
     * @param  string $data
     * @param  array  $params
     * @throws Exception
     * @return string
     */
    public function decode(string $data, array $params = []): string
    {
        $data = trim($data);

        if (str_starts_with($data, '<~')) {
            $data = substr($data, 2);
        }
        if (str_ends_with($data, '~>')) {
            $data = substr($data, 0, -2);
        }

        $data = preg_replace('/\s+/', '', $data);

        $out   = '';
        $group = '';
        $len   = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $c = $data[$i];

            if (($c === 'z') && ($group === '')) {
                // The 'z' shortcut expands a single input byte to 4 output
                // bytes - unlike every other group here, it costs nothing
                // from $len to produce output, so it needs the same
                // decoded-size cap the other amplifying filters already have.
                $out .= "\0\0\0\0";

                if (strlen($out) > self::MAX_DECODED_LENGTH) {
                    throw new Exception('Error: Decoded ASCII85Decode stream exceeds the maximum allowed size.');
                }

                continue;
            }

            $group .= $c;

            if (strlen($group) === 5) {
                $out  .= $this->decodeGroup($group);
                $group = '';

                if (strlen($out) > self::MAX_DECODED_LENGTH) {
                    throw new Exception('Error: Decoded ASCII85Decode stream exceeds the maximum allowed size.');
                }
            }
        }

        if ($group !== '') {
            $n     = strlen($group);
            $padded = str_pad($group, 5, 'u');
            $out   .= substr($this->decodeGroup($padded), 0, $n - 1);
        }

        return $out;
    }

    /**
     * Decode a 5-character ASCII85 group into 4 raw bytes
     *
     * @param  string $group
     * @throws Exception
     * @return string
     */
    protected function decodeGroup(string $group): string
    {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $digit = ord($group[$i]) - 33;
            if (($digit < 0) || ($digit > 84)) {
                throw new Exception('Error: Invalid ASCII85 data.');
            }
            $value = ($value * 85) + $digit;
        }

        if ($value > 4294967295) {
            throw new Exception('Error: Invalid ASCII85 data.');
        }

        return pack('N', $value);
    }

}
