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

use Pop\Pdf\Document\Security;

/**
 * PDF Standard Security Handler - password and key derivation for the
 * /Encrypt dictionary. This class holds the build (write) direction only:
 * it turns a Document\Security into the /O, /U, /OE, /UE, /P and /Perms
 * values a conforming reader validates a password against, plus the File
 * Encryption Key those values wrap.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class StandardSecurityHandler
{

    /**
     * Maximum password length in bytes. ISO 32000-2, 7.6.4.3.3: a password
     * longer than 127 bytes is truncated, and readers do the same, so the
     * truncation has to happen here or long passwords would not round-trip.
     * @var int
     */
    const MAX_PASSWORD_LENGTH = 127;

    /**
     * Build the /Encrypt dictionary fields and File Encryption Key for
     * revision 6 (AES-256, PDF 2.0). ISO 32000-2 Annex C, Algorithms 2.A,
     * 2.B, 8, 9, 10.
     *
     * The File Encryption Key is random and independent of both passwords;
     * each password only ever wraps it (in /UE and /OE respectively), which
     * is why the owner password can open the document without either
     * password being derivable from the other.
     *
     * $fileId is deliberately unused: unlike revision 4, revision 6 does not
     * mix the document /ID into the key derivation at all. It stays in the
     * signature so the revision 4 and 6 builders can be called uniformly.
     *
     * @param  Security $security
     * @param  string   $fileId raw bytes of the PDF's first /ID element (unused for revision 6)
     * @throws Exception
     * @return array{fileKey: string, dict: array<string, string|int>}
     */
    public static function buildRevision6(Security $security, string $fileId): array
    {
        $fileKey       = random_bytes(32);
        $userPassword  = self::preparePassword((string)$security->getUserPassword());
        $ownerPassword = self::preparePassword($security->getEffectiveOwnerPassword());

        // Algorithm 8 - /U and /UE. The validation salt proves the password;
        // the key salt derives the wrapping key for the file key.
        $userValidationSalt = random_bytes(8);
        $userKeySalt        = random_bytes(8);
        $u  = self::hash2B($userPassword, $userValidationSalt, '') . $userValidationSalt . $userKeySalt;
        $ue = self::wrapFileKey(self::hash2B($userPassword, $userKeySalt, ''), $fileKey);

        // Algorithm 9 - /O and /OE. Both owner hashes additionally take the
        // full 48-byte /U string as input, which binds /O to /U and is why
        // /U must be computed first.
        $ownerValidationSalt = random_bytes(8);
        $ownerKeySalt        = random_bytes(8);
        $o  = self::hash2B($ownerPassword, $ownerValidationSalt, $u) . $ownerValidationSalt . $ownerKeySalt;
        $oe = self::wrapFileKey(self::hash2B($ownerPassword, $ownerKeySalt, $u), $fileKey);

        $p     = $security->getPermissions()->toPValue();
        $perms = self::computePerms($fileKey, $p);

        return [
            'fileKey' => $fileKey,
            'dict'    => ['O' => $o, 'U' => $u, 'OE' => $oe, 'UE' => $ue, 'P' => $p, 'Perms' => $perms],
        ];
    }

    /**
     * ISO 32000-2 Annex C, Algorithm 2.B - the "hardened hash". Produces a
     * 32-byte hash from a password, an 8-byte salt, and (for the owner
     * password only) the 48-byte /U string computed just before it.
     *
     * The loop runs a minimum of 64 rounds and then keeps going until the
     * last byte of the round's AES output is less than or equal to
     * (round number - 32), with rounds counted from 1. Because that last
     * byte is effectively uniform over 0-255, the extra rounds beyond 64
     * are data-dependent and unpredictable, which is the entire point: the
     * work factor cannot be short-circuited by an attacker. The condition
     * is guaranteed to terminate, since by round 288 the threshold reaches
     * 255 and every possible byte value satisfies it.
     *
     * Public because the read/verify direction (Algorithms 11 and 12) needs
     * the identical primitive to check a supplied password against /U or /O.
     *
     * @param  string $password already prepared/truncated password bytes
     * @param  string $salt     8 raw bytes
     * @param  string $uData    the 48-byte /U string, or '' for user-password hashes
     * @throws Exception
     * @return string 32 raw bytes
     */
    public static function hash2B(string $password, string $salt, string $uData): string
    {
        $k     = hash('sha256', $password . $salt . $uData, true);
        $round = 0;

        while (true) {
            $round++;

            // (a) 64 repetitions of password + K + udata. K grows to 48 or
            // 64 bytes after the first round; the whole of it is repeated.
            $k1 = str_repeat($password . $k . $uData, 64);

            // (b) AES-128-CBC, no padding, key = K[0..15], IV = K[16..31].
            $e = openssl_encrypt(
                $k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16)
            );
            if ($e === false) {
                throw new Exception('Hardened hash AES-128 round encryption failed');
            }

            // (c) The first 16 bytes of E as a big-endian integer, modulo 3.
            // 256 % 3 == 1, so every byte's positional weight is 1 mod 3 and
            // the sum of the bytes is congruent to the full integer mod 3.
            $sum = 0;
            for ($i = 0; $i < 16; $i++) {
                $sum += ord($e[$i]);
            }

            // (d) Re-hash E with the selected digest; K becomes 32/48/64 bytes.
            $k = hash(match ($sum % 3) {
                1       => 'sha384',
                2       => 'sha512',
                default => 'sha256',
            }, $e, true);

            if (($round >= 64) && (ord($e[strlen($e) - 1]) <= ($round - 32))) {
                break;
            }
        }

        return substr($k, 0, 32);
    }

    /**
     * AES-256-CBC, no padding, zero IV - used only to wrap the File
     * Encryption Key inside /UE and /OE (ISO 32000-2 Algorithms 8 and 9,
     * step (b)), never for actual object content.
     *
     * The all-zero IV is mandated by the spec and is safe here specifically
     * because the wrapping key is itself derived from a fresh random 8-byte
     * key salt on every build: no two documents ever wrap under the same
     * key, so the IV-reuse weakness that a fixed IV would normally create
     * does not arise.
     *
     * @param  string $key  32 raw bytes, the hash of password + key salt
     * @param  string $data 32 raw bytes, the file encryption key
     * @throws Exception
     * @return string 32 raw bytes
     */
    protected static function wrapFileKey(string $key, string $data): string
    {
        $wrapped = openssl_encrypt(
            $data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\x00", 16)
        );
        if ($wrapped === false) {
            throw new Exception('File encryption key wrapping failed');
        }

        return $wrapped;
    }

    /**
     * ISO 32000-2 Annex C, Algorithm 10 - an encrypted copy of the
     * permission bits, letting a conforming reader detect a tampered /P
     * value even if it doesn't cross-check against /O or /U.
     *
     * The 16-byte cleartext block is: /P extended to 64 bits by setting the
     * high 32 bits to 1, stored low-order byte first (bytes 0-7); the
     * EncryptMetadata flag as 'T' or 'F' (byte 8); the literal marker "adb"
     * (bytes 9-11); and 4 random bytes (bytes 12-15).
     *
     * @param  string $fileKey 32 raw bytes
     * @param  int    $p
     * @throws Exception
     * @return string 16 raw bytes
     */
    protected static function computePerms(string $fileKey, int $p): string
    {
        $block  = pack('V', $p & 0xFFFFFFFF);
        $block .= "\xFF\xFF\xFF\xFF";
        $block .= 'T'; // EncryptMetadata always true - see spec Non-goals
        $block .= 'adb';
        $block .= random_bytes(4);

        $perms = openssl_encrypt($block, 'aes-256-ecb', $fileKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if ($perms === false) {
            throw new Exception('Permissions block encryption failed');
        }

        return $perms;
    }

    /**
     * ISO 32000-2, 7.6.4.3.3: the password is a UTF-8 byte string truncated
     * to 127 bytes.
     *
     * The spec also calls for SASLprep (RFC 4013) normalization ahead of the
     * truncation. That is not applied here - it only affects passwords
     * containing non-ASCII characters, and getting it wrong would be worse
     * than not doing it, since a reader that also skips SASLprep (as several
     * do) would then disagree with us on every such password.
     *
     * @param  string $password
     * @return string
     */
    protected static function preparePassword(string $password): string
    {
        return substr($password, 0, self::MAX_PASSWORD_LENGTH);
    }

}
