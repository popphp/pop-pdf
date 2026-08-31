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

namespace Pop\Pdf\Build\Security;

/**
 * RC4 stream cipher
 *
 * PHP has no built-in RC4 primitive (and OpenSSL's RC4 support is
 * inconsistent across builds/deprecated), but ISO 32000's Algorithms 3/5/7
 * for PDF revisions 2-4 require it to compute the /O and /U password
 * dictionary entries - even when the actual page/stream content is
 * encrypted with AES rather than RC4. This class is used only for that
 * dictionary-entry math; it never touches page/stream content.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Rc4
{
    /**
     * RC4 is a symmetric stream cipher - this same method encrypts and
     * decrypts, since both operations are "XOR with the same keystream."
     *
     * @param  string $key
     * @param  string $data
     * @return string
     */
    public static function crypt(string $key, string $data): string
    {
        $keyLength = strlen($key);
        $s = range(0, 255);

        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLength])) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }

        $result = '';
        $i = 0;
        $j = 0;
        for ($n = 0, $len = strlen($data); $n < $len; $n++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $result .= chr(ord($data[$n]) ^ $s[($s[$i] + $s[$j]) % 256]);
        }

        return $result;
    }
}
