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
 * /Encrypt dictionary.
 *
 * The build (write) direction turns a Document\Security into the /O, /U,
 * /OE, /UE, /P and /Perms values a conforming reader validates a password
 * against, plus the File Encryption Key those values wrap.
 *
 * The open (read) direction runs the same primitives backwards: given an
 * /Encrypt dictionary read off disk and a candidate password, it verifies
 * the password and recovers that File Encryption Key.
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
     * The 32-byte password padding string of ISO 32000-1 Annex C, Algorithm 2
     * step (a). Revision 2-4 passwords are padded out to exactly 32 bytes
     * with the leading bytes of this string (or truncated to 32 bytes if
     * longer), so that an empty password is still a full 32-byte input.
     *
     * This scheme belongs to revisions 2-4 only. Revision 6 does not pad at
     * all - it takes the raw UTF-8 password bytes truncated to 127 - so
     * nothing here is shared with buildRevision6()'s preparePassword().
     * @var string
     */
    const PADDING =
        "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08" .
        "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /**
     * Number of key-strengthening rounds performed by revision 3+ of
     * Algorithms 2 and 3.
     * @var int
     */
    const KEY_ROUNDS = 50;

    /**
     * Number of XOR'd-key RC4 re-encryption rounds performed by revision 3+
     * of Algorithms 3 and 5, with the counter running 1 to 19 inclusive.
     * @var int
     */
    const RC4_ROUNDS = 19;

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
     * Verify a candidate password against a revision 6 (AES-256) /Encrypt
     * dictionary and recover the File Encryption Key. ISO 32000-2 Annex C,
     * Algorithm 2.A, by way of Algorithms 11 (user) and 12 (owner) - the
     * exact inverse of buildRevision6().
     *
     * Each of /U and /O is a 48-byte string laid out by Algorithms 8 and 9
     * as hash (bytes 0-31), validation salt (32-39), key salt (40-47). The
     * validation salt proves the password; the key salt then derives the
     * key that unwraps the file key out of /UE or /OE. Note the two salts
     * are NOT interchangeable - using the validation salt to unwrap would
     * still yield 32 plausible bytes and no error at all, just an unusable
     * key, so the offsets are load-bearing.
     *
     * Both owner hashes additionally take the full 48-byte /U string as
     * input (that is what binds /O to /U); the user hashes take an empty
     * string there.
     *
     * The user password is tried first and the owner password second;
     * Algorithm 2.A states the opposite order. For any conforming file the
     * order cannot change the result, because /UE and /OE are required to
     * wrap one and the same file key (buildRevision6() passes the identical
     * key to both) - so it would matter only to a caller that needed to know
     * WHICH permission level was unlocked, which this method's return type
     * cannot express anyway. That is a property of the file being conforming,
     * not an identity: a malformed or hostile file whose /UE and /OE wrap
     * different keys has no single right answer, and neither ordering
     * produces one.
     *
     * @param  array<string, string|int> $encryptDict must contain O, U, OE, UE (as built by buildRevision6())
     * @param  string                    $candidatePassword
     * @throws Exception
     * @return string 32 raw bytes
     */
    public static function openRevision6(array $encryptDict, string $candidatePassword): string
    {
        // Truncated to 48 bytes: the spec fixes /U and /O at exactly that
        // length, but some producers pad them out to 127 (a leftover of the
        // pre-standard Adobe extension level 3 revision), and every reader
        // worth interoperating with ignores the excess.
        $u  = substr((string)($encryptDict['U'] ?? ''), 0, 48);
        $o  = substr((string)($encryptDict['O'] ?? ''), 0, 48);
        $ue = (string)($encryptDict['UE'] ?? '');
        $oe = (string)($encryptDict['OE'] ?? '');

        if ((strlen($u) != 48) || (strlen($o) != 48) || (strlen($ue) != 32) || (strlen($oe) != 32)) {
            throw new Exception(
                'Error: The encryption dictionary is malformed - /U and /O must be 48 bytes and /UE and /OE 32 bytes.'
            );
        }

        $password = self::preparePassword($candidatePassword);

        // Algorithm 11 - the user password.
        if (hash_equals(substr($u, 0, 32), self::hash2B($password, substr($u, 32, 8), ''))) {
            return self::unwrapFileKey(self::hash2B($password, substr($u, 40, 8), ''), $ue);
        }

        // Algorithm 12 - the owner password.
        if (hash_equals(substr($o, 0, 32), self::hash2B($password, substr($o, 32, 8), $u))) {
            return self::unwrapFileKey(self::hash2B($password, substr($o, 40, 8), $u), $oe);
        }

        throw new Exception('Error: The password provided is incorrect for this encrypted PDF.');
    }

    /**
     * Build the /Encrypt dictionary fields and File Encryption Key for
     * revision 4 (AES-128, PDF 1.6/1.7). ISO 32000-1 Annex C, Algorithms
     * 2, 3 and 5. Algorithms 3/5 are specified in terms of RC4 regardless
     * of the content cipher - RC4 here never touches page/stream content.
     *
     * Unlike revision 6, the file key is not random: it is derived from the
     * user password, /O, /P and the document /ID, so every one of those has
     * to be settled before the key exists. That forces a strict order -
     * /O first (it feeds the key), then the key, then /U (it is the key,
     * obfuscated). Producing them in any other order cannot work.
     *
     * The owner password never encrypts anything directly. /O is the padded
     * USER password encrypted under a key derived from the owner password,
     * so a reader given the owner password peels /O back to the user
     * password and then follows the ordinary user path from there.
     *
     * @param  Security $security
     * @param  string   $fileId raw bytes of the PDF's first /ID element
     * @return array{fileKey: string, dict: array<string, string|int>}
     */
    public static function buildRevision4(Security $security, string $fileId): array
    {
        $userPassword  = (string)$security->getUserPassword();
        $ownerPassword = $security->getEffectiveOwnerPassword();
        $p             = $security->getPermissions()->toPValue();

        $o       = self::computeORevision4($ownerPassword, $userPassword);
        $fileKey = self::deriveRevision4FileKey(self::padPassword($userPassword), $o, $p, $fileId);
        $u       = self::computeURevision4($fileKey, $fileId);

        return ['fileKey' => $fileKey, 'dict' => ['O' => $o, 'U' => $u, 'P' => $p]];
    }

    /**
     * Verify a candidate password against a revision 4 (AES-128) /Encrypt
     * dictionary and recover the File Encryption Key. ISO 32000-1 Annex C,
     * Algorithm 6 (the candidate tried as a USER password), falling back to
     * Algorithm 7 (tried as an OWNER password) - the exact inverse of
     * buildRevision4().
     *
     * The two paths differ only in how they arrive at the padded user
     * password that Algorithm 2 consumes. As a user password the candidate
     * IS that password, padded. As an owner password it is only the key that
     * unlocks /O, which is where Algorithm 3 stored the padded user password;
     * once recovered, the owner path is indistinguishable from the user path.
     * That is the whole mechanism by which an owner password opens a
     * document without being the user password.
     *
     * Only the FIRST 16 bytes of /U are compared. Algorithm 5 fixes /U at 32
     * bytes but specifies only its first 16 - the remainder is arbitrary
     * padding. buildRevision4() writes zeros there; qpdf writes random bytes;
     * both are conforming. Comparing all 32 would pass every round-trip
     * against this library's own output and then reject every file written by
     * anyone else, so the truncation is load-bearing rather than cosmetic.
     *
     * Unlike openRevision6(), /O and /U are required to be exactly 32 bytes
     * with no leniency. The over-long /U and /O this class tolerates for
     * revision 6 are an artifact of the pre-standard Adobe extension level 3
     * AES-256 revision, which postdates revision 4 entirely; no revision 4
     * producer is known to pad them, and /O is fed WHOLE into Algorithm 2's
     * digest, so silently accepting a wrong length would yield a wrong key
     * and an "incorrect password" error pointing at the wrong problem.
     *
     * @param  array<string, string|int> $encryptDict must contain O, U, P (as built by buildRevision4())
     * @param  string                    $fileId raw bytes of the PDF's first /ID element
     * @param  string                    $candidatePassword
     * @throws Exception
     * @return string 16 raw bytes
     */
    public static function openRevision4(array $encryptDict, string $fileId, string $candidatePassword): string
    {
        $o = (string)($encryptDict['O'] ?? '');
        $u = (string)($encryptDict['U'] ?? '');

        if ((strlen($o) != 32) || (strlen($u) != 32) || !isset($encryptDict['P'])) {
            throw new Exception(
                'Error: The encryption dictionary is malformed - /O and /U must be 32 bytes and /P must be present.'
            );
        }

        $p        = (int)$encryptDict['P'];
        $expected = substr($u, 0, 16);

        // Algorithm 6 - the candidate treated as the user password.
        $fileKey = self::deriveRevision4FileKeyFromUserPassword($candidatePassword, $o, $p, $fileId);
        if (hash_equals($expected, substr(self::computeURevision4($fileKey, $fileId), 0, 16))) {
            return $fileKey;
        }

        // Algorithm 7 - the candidate treated as the owner password. Peel the
        // padded user password out of /O, then re-run the user path on it.
        $fileKey = self::deriveRevision4FileKey(
            self::recoverPaddedUserPassword($candidatePassword, $o), $o, $p, $fileId
        );
        if (hash_equals($expected, substr(self::computeURevision4($fileKey, $fileId), 0, 16))) {
            return $fileKey;
        }

        throw new Exception('Error: The password provided is incorrect for this encrypted PDF.');
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 7 - recover the padded USER password out
     * of the stored /O value given a candidate owner password, by undoing
     * Algorithm 3's chained RC4 passes.
     *
     * Steps (a)-(b) rebuild Algorithm 3's RC4 key from the owner password by
     * exactly the same route computeORevision4() takes - note the 50-round
     * loop re-hashes the FULL digest here, unlike Algorithm 2's, which
     * truncates to the key length first. Step (c) then runs the 19 XOR'd-key
     * rounds with the counter descending 19 to 1, followed by the unmodified
     * key, which is how the spec words the reversal.
     *
     * That descending order is written out literally to match the spec, but
     * it is not actually load-bearing, and it is worth recording why so nobody
     * "fixes" it later. Rc4::crypt() rebuilds its state array from scratch on
     * every call and its keystream is a pure function of (key, byte position)
     * - nothing carries over between calls - so over these fixed 32 bytes
     * Algorithm 3 collapses to
     *
     *     O = padded_user_password XOR ks(K) XOR ks(K^1) XOR ... XOR ks(K^19)
     *
     * and re-applying the same 20 keys to /O cancels every term, in whatever
     * order. What IS load-bearing is the SET of round keys - all 20 of them,
     * no more and no fewer - and that each round consumes the previous
     * round's output so those keystreams accumulate at all.
     *
     * A wrong owner password does not fail here; it simply returns 32
     * meaningless bytes. Deciding correctness is openRevision4()'s job, via
     * the /U comparison.
     *
     * @param  string $ownerCandidate
     * @param  string $oValue 32 raw bytes
     * @param  int    $keyLength RC4 key length in bytes
     * @return string exactly 32 bytes, the padded user password
     */
    protected static function recoverPaddedUserPassword(
        string $ownerCandidate, string $oValue, int $keyLength = 16
    ): string
    {
        $digest = md5(self::padPassword($ownerCandidate), true);
        for ($i = 0; $i < self::KEY_ROUNDS; $i++) {
            $digest = md5($digest, true);
        }
        $rc4Key = substr($digest, 0, $keyLength);

        $decrypted = $oValue;
        for ($round = self::RC4_ROUNDS; $round >= 1; $round--) {
            $decrypted = Rc4::crypt(self::xorKey($rc4Key, $round), $decrypted);
        }

        return Rc4::crypt($rc4Key, $decrypted);
    }

    /**
     * The public entry point for the read path (and this plan's own
     * round-trip test): recover the file key from a candidate user
     * password given the already-computed /O, /P, and file /ID.
     *
     * ISO 32000-1 Annex C, Algorithm 2. Note this always returns a key -
     * a wrong password yields a wrong key rather than an error. Deciding
     * whether the password was correct is Algorithm 6's job: re-run
     * Algorithm 5 with the recovered key and compare against /U.
     *
     * @param  string $userPassword
     * @param  string $oValue 32 raw bytes
     * @param  int    $p
     * @param  string $fileId raw bytes of the PDF's first /ID element
     * @return string 16 raw bytes
     */
    public static function deriveRevision4FileKeyFromUserPassword(
        string $userPassword, string $oValue, int $p, string $fileId
    ): string
    {
        return self::deriveRevision4FileKey(self::padPassword($userPassword), $oValue, $p, $fileId);
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 2 - the File Encryption Key itself.
     *
     * The MD5 is fed, in this exact order, the padded user password, the
     * whole 32-byte /O string, /P as four bytes low-order first (it is a
     * signed 32-bit value, so it is masked to unsigned before packing), and
     * the first element of the document /ID. Every one of those is public
     * except the password, which is what makes the key password-derived.
     *
     * The 50-round loop then re-hashes only the first $keyLength bytes of
     * each digest. That truncation is deliberate and is the one thing
     * separating this loop from the superficially identical one in
     * Algorithm 3, which re-hashes the full 16-byte digest. For a 128-bit
     * key the two happen to coincide (16 bytes is the whole digest); for a
     * 40-bit key they do not, so the distinction is kept explicit here.
     *
     * EncryptMetadata is always true for documents this component writes
     * (see spec Non-goals), so Algorithm 2's optional step (f) - appending
     * 0xFFFFFFFF for unencrypted metadata - is never applicable.
     *
     * @param  string $paddedUserPassword exactly 32 bytes
     * @param  string $oValue 32 raw bytes
     * @param  int    $p
     * @param  string $fileId raw bytes of the PDF's first /ID element
     * @param  int    $keyLength file key length in bytes
     * @return string $keyLength raw bytes
     */
    protected static function deriveRevision4FileKey(
        string $paddedUserPassword, string $oValue, int $p, string $fileId, int $keyLength = 16
    ): string
    {
        $hash = hash_init('md5');
        hash_update($hash, $paddedUserPassword);
        hash_update($hash, $oValue);
        hash_update($hash, pack('V', $p & 0xFFFFFFFF));
        hash_update($hash, $fileId);
        $digest = hash_final($hash, true);

        for ($i = 0; $i < self::KEY_ROUNDS; $i++) {
            $digest = md5(substr($digest, 0, $keyLength), true);
        }

        return substr($digest, 0, $keyLength);
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 2 step (a): pad the password out to
     * exactly 32 bytes with the leading bytes of the fixed padding string,
     * or truncate it to 32 bytes if it is longer.
     *
     * @param  string $password
     * @return string exactly 32 bytes
     */
    protected static function padPassword(string $password): string
    {
        $password = substr($password, 0, 32);

        return $password . substr(self::PADDING, 0, 32 - strlen($password));
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 3 - the /O entry.
     *
     * Steps (a)-(d) turn the owner password into an RC4 key: pad it, MD5 it,
     * then re-hash the digest 50 more times (revision 3+ only). Unlike
     * Algorithm 2's loop this one feeds the FULL previous digest back in,
     * not a truncated copy.
     *
     * Steps (e)-(g) then encrypt the padded USER password with that key,
     * and re-encrypt the result 19 more times with the key XOR'd against
     * the round counter, 1 through 19, each round applied to the previous
     * round's ciphertext. A reader holding the owner password undoes this
     * by running the chain backwards - key XOR 19 first, then 18, down to
     * 1, then the unmodified key - and recovers the padded user password
     * (Algorithm 7), from which it proceeds down the ordinary user path.
     *
     * Worth recording, since it is easy to assume otherwise: with RC4 the
     * ORDER of these rounds has no effect on the result. RC4's keystream
     * depends only on the key and the requested length, and every round
     * here processes the same 32 bytes, so the chain collapses to
     * padded_password XOR ks(K) XOR ks(K^1) XOR ... XOR ks(K^19), and XOR
     * is commutative. What is load-bearing is the SET of round keys - all
     * 20 of them, no more and no fewer - and the chaining, which is what
     * makes those keystreams accumulate at all. An implementation that fed
     * every round the original first-pass ciphertext instead of the running
     * one would emit 32 equally plausible bytes that no reader could open.
     *
     * @param  string $ownerPassword the effective owner password
     * @param  string $userPassword
     * @param  int    $keyLength RC4 key length in bytes
     * @return string exactly 32 bytes
     */
    protected static function computeORevision4(
        string $ownerPassword, string $userPassword, int $keyLength = 16
    ): string
    {
        $digest = md5(self::padPassword($ownerPassword), true);
        for ($i = 0; $i < self::KEY_ROUNDS; $i++) {
            $digest = md5($digest, true);
        }
        $rc4Key = substr($digest, 0, $keyLength);

        $encrypted = Rc4::crypt($rc4Key, self::padPassword($userPassword));
        for ($round = 1; $round <= self::RC4_ROUNDS; $round++) {
            $encrypted = Rc4::crypt(self::xorKey($rc4Key, $round), $encrypted);
        }

        return $encrypted;
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 5 - the /U entry for revision 3+.
     *
     * /U is a checksum on the file key: MD5 of the padding string followed
     * by the document /ID, encrypted with the file key and put through the
     * same 19 chained XOR'd-key rounds as Algorithm 3. Note the hash input
     * is the bare padding string, NOT the padded user password - the
     * password's contribution arrives only through the file key.
     *
     * A reader validates a user password by deriving a candidate file key
     * (Algorithm 2), re-running this, and comparing the first 16 bytes
     * against /U. The trailing 16 bytes are arbitrary per the spec, purely
     * to bring the entry to the fixed 32-byte width; zeros are used here.
     *
     * @param  string $fileKey 16 raw bytes
     * @param  string $fileId raw bytes of the PDF's first /ID element
     * @return string exactly 32 bytes
     */
    protected static function computeURevision4(string $fileKey, string $fileId): string
    {
        $encrypted = Rc4::crypt($fileKey, md5(self::PADDING . $fileId, true));
        for ($round = 1; $round <= self::RC4_ROUNDS; $round++) {
            $encrypted = Rc4::crypt(self::xorKey($fileKey, $round), $encrypted);
        }

        return $encrypted . str_repeat("\x00", 16);
    }

    /**
     * XOR every byte of an RC4 key against a single-byte round counter, per
     * step (g) of Algorithm 3 and step (e) of Algorithm 5.
     *
     * @param  string $key
     * @param  int    $round 1-19
     * @return string same length as $key
     */
    protected static function xorKey(string $key, int $round): string
    {
        $result = '';
        for ($i = 0, $len = strlen($key); $i < $len; $i++) {
            $result .= chr(ord($key[$i]) ^ $round);
        }

        return $result;
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
     * The inverse of wrapFileKey(): AES-256-CBC, no padding, zero IV, per
     * ISO 32000-2 Algorithm 2.A steps (f) and (g). Unwraps the File
     * Encryption Key out of /UE or /OE.
     *
     * This step cannot itself tell a right key from a wrong one - AES-CBC
     * with no padding decrypts any 32 bytes to some other 32 bytes - which
     * is precisely why the validation-salt hash comparison has to happen
     * first. /Perms (Algorithm 13) is what independently confirms the
     * recovered key afterwards, should a caller want that.
     *
     * @param  string $key  32 raw bytes, the hash of password + key salt
     * @param  string $data 32 raw bytes, the wrapped file key from /UE or /OE
     * @throws Exception
     * @return string 32 raw bytes
     */
    protected static function unwrapFileKey(string $key, string $data): string
    {
        $fileKey = openssl_decrypt(
            $data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\x00", 16)
        );
        if ($fileKey === false) {
            throw new Exception('File encryption key unwrapping failed');
        }

        return $fileKey;
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
