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
 * Per-object/per-string AES-CBC encryption for PDF's Standard Security
 * Handler. A random 16-byte IV is prepended to every encrypted buffer -
 * that IV is what a decrypting reader (Plan 2) reads back off the front of
 * the ciphertext.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ObjectCipher
{
    /**
     * Encrypt a buffer for revision 6 (AES-256). The file key is used
     * directly - ISO 32000-2's Algorithm 1.A - unlike revision 4, there is
     * no separate per-object key derivation step.
     *
     * @param  string $fileKey 32 raw bytes
     * @param  string $data
     * @return string
     */
    public static function encryptAes256(string $fileKey, string $data): string
    {
        if (strlen($fileKey) !== 32) {
            throw new Exception('File key for AES-256 must be exactly 32 bytes');
        }
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $fileKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new Exception('AES-256 encryption failed');
        }
        return $iv . $ciphertext;
    }

    /**
     * Encrypt a buffer for revision 4 (AES-128). ISO 32000-1 Algorithm 1:
     * the per-object key is derived from the file key mixed with the
     * object's number/generation (low-order bytes, little-endian) plus a
     * fixed "sAlT" suffix that signals AES rather than RC4 content
     * encryption, truncated to min(fileKeyLength + 5, 16) bytes.
     *
     * @param  string $fileKey 16 raw bytes
     * @param  int    $objectNumber
     * @param  int    $generation
     * @param  string $data
     * @return string
     */
    public static function encryptAes128(string $fileKey, int $objectNumber, int $generation, string $data): string
    {
        if (strlen($fileKey) !== 16) {
            throw new Exception('File key for AES-128 must be exactly 16 bytes');
        }
        $objectKey = self::deriveObjectKey($fileKey, $objectNumber, $generation);
        $iv        = random_bytes(16);
        $ciphertext = openssl_encrypt($data, 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new Exception('AES-128 encryption failed');
        }
        return $iv . $ciphertext;
    }

    /**
     * @param  string $fileKey
     * @param  int    $objectNumber
     * @param  int    $generation
     * @return string
     */
    protected static function deriveObjectKey(string $fileKey, int $objectNumber, int $generation): string
    {
        $input  = $fileKey;
        $input .= chr($objectNumber & 0xFF) . chr(($objectNumber >> 8) & 0xFF) . chr(($objectNumber >> 16) & 0xFF);
        $input .= chr($generation & 0xFF) . chr(($generation >> 8) & 0xFF);
        $input .= "\x73\x41\x6C\x54"; // "sAlT" - AES content marker, ISO 32000-1 Algorithm 1 step (c)

        $digest    = md5($input, true);
        $keyLength = min(strlen($fileKey) + 5, 16);

        return substr($digest, 0, $keyLength);
    }
}
