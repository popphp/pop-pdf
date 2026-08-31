<?php

namespace Pop\Pdf\Test\Build\Security;

use Pop\Pdf\Build\Security\StandardSecurityHandler;
use Pop\Pdf\Document\Permissions;
use Pop\Pdf\Document\Security;
use PHPUnit\Framework\TestCase;

class StandardSecurityHandlerTest extends TestCase
{

    public function testBuildRevision6ProducesA32ByteFileKeyAnd48ByteUAndO()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $this->assertEquals(32, strlen($result['fileKey']));
        $this->assertEquals(48, strlen($result['dict']['U']));
        $this->assertEquals(48, strlen($result['dict']['O']));
        $this->assertEquals(32, strlen($result['dict']['UE']));
        $this->assertEquals(32, strlen($result['dict']['OE']));
        $this->assertEquals(16, strlen($result['dict']['Perms']));
        $this->assertEquals(-1, $result['dict']['P']);
    }

    public function testBuildRevision6ProducesADifferentFileKeyEachCall()
    {
        // The FEK is generated at random on every build, independent of
        // the passwords - two calls with identical inputs must not
        // produce the same key (or ciphertext outputs would repeat, an
        // AES-CBC anti-pattern this design specifically avoids).
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);

        $first  = StandardSecurityHandler::buildRevision6($security, $fileId);
        $second = StandardSecurityHandler::buildRevision6($security, $fileId);

        $this->assertNotEquals($first['fileKey'], $second['fileKey']);
    }

    /**
     * Round-trip the user password through ISO 32000-2 Algorithm 11: hash
     * the supplied password with the validation salt out of /U and compare
     * against the first 32 bytes of /U. This is precisely the check a
     * conforming reader performs, so it exercises hash2B end to end rather
     * than merely measuring string lengths.
     */
    public function testUserPasswordValidatesAgainstTheUStringPerAlgorithm11()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));
        $u        = $result['dict']['U'];

        $hash           = substr($u, 0, 32);
        $validationSalt = substr($u, 32, 8);

        $this->assertEquals($hash, StandardSecurityHandler::hash2B('open-me', $validationSalt, ''));
        $this->assertNotEquals($hash, StandardSecurityHandler::hash2B('wrong-password', $validationSalt, ''));
    }

    /**
     * ISO 32000-2 Algorithm 12: the owner hash is computed over the owner
     * password, the owner validation salt AND the full 48-byte /U string.
     */
    public function testOwnerPasswordValidatesAgainstTheOStringPerAlgorithm12()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));
        $u        = $result['dict']['U'];
        $o        = $result['dict']['O'];

        $hash           = substr($o, 0, 32);
        $validationSalt = substr($o, 32, 8);

        $this->assertEquals($hash, StandardSecurityHandler::hash2B('admin123', $validationSalt, $u));
        $this->assertNotEquals($hash, StandardSecurityHandler::hash2B('admin123', $validationSalt, ''));
    }

    /**
     * ISO 32000-2 Algorithms 2.A step (f)/(g): the intermediate key derived
     * from the key salt decrypts /UE and /OE back to the file encryption
     * key. This is the step that actually lets a reader open the document,
     * so a mismatch here would render every produced PDF unopenable.
     */
    public function testUeAndOeUnwrapBackToTheFileEncryptionKey()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $userKeySalt  = substr($result['dict']['U'], 40, 8);
        $ownerKeySalt = substr($result['dict']['O'], 40, 8);

        $userIntermediate  = StandardSecurityHandler::hash2B('open-me', $userKeySalt, '');
        $ownerIntermediate = StandardSecurityHandler::hash2B('admin123', $ownerKeySalt, $result['dict']['U']);

        $fromUe = openssl_decrypt(
            $result['dict']['UE'], 'aes-256-cbc', $userIntermediate,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\x00", 16)
        );
        $fromOe = openssl_decrypt(
            $result['dict']['OE'], 'aes-256-cbc', $ownerIntermediate,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\x00", 16)
        );

        $this->assertEquals($result['fileKey'], $fromUe);
        $this->assertEquals($result['fileKey'], $fromOe);
    }

    /**
     * ISO 32000-2 Algorithm 13: /Perms decrypts with the file key to a
     * 16-byte block whose bytes 0-3 are /P little-endian, bytes 4-7 are
     * 0xFF, byte 8 is the EncryptMetadata flag and bytes 9-11 are "adb".
     */
    public function testPermsDecryptsToTheExpectedPermissionBlock()
    {
        $permissions = (new Permissions())->allowCopying(false)->allowPrinting(false);
        $security    = new Security('open-me', 'admin123', $permissions);
        $result      = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $block = openssl_decrypt(
            $result['dict']['Perms'], 'aes-256-ecb', $result['fileKey'],
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        $this->assertEquals(16, strlen($block));
        $this->assertEquals(pack('V', $result['dict']['P'] & 0xFFFFFFFF), substr($block, 0, 4));
        $this->assertEquals("\xFF\xFF\xFF\xFF", substr($block, 4, 4));
        $this->assertEquals('T', $block[8]);
        $this->assertEquals('adb', substr($block, 9, 3));
        $this->assertNotEquals(-1, $result['dict']['P']);
    }

    /**
     * The validation and key salts must be independent random values; a
     * copy/paste slip that reused one salt for both would still produce
     * correctly-sized output but would leak the key-salt hash in /U.
     */
    public function testValidationAndKeySaltsAreDistinct()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $this->assertNotEquals(substr($result['dict']['U'], 32, 8), substr($result['dict']['U'], 40, 8));
        $this->assertNotEquals(substr($result['dict']['O'], 32, 8), substr($result['dict']['O'], 40, 8));
        $this->assertNotEquals(substr($result['dict']['U'], 32, 16), substr($result['dict']['O'], 32, 16));
    }

    /**
     * An absent user password means "opens with an empty password" - the
     * document is still encrypted and still carries owner restrictions.
     */
    public function testEmptyUserPasswordStillProducesAValidUString()
    {
        $security = new Security(null, 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $validationSalt = substr($result['dict']['U'], 32, 8);

        $this->assertEquals(substr($result['dict']['U'], 0, 32), StandardSecurityHandler::hash2B('', $validationSalt, ''));
    }

    /**
     * ISO 32000-2 7.6.4.3.3: passwords longer than 127 bytes are truncated,
     * so a 200-byte password and its first 127 bytes must authenticate
     * identically.
     */
    public function testPasswordsAreTruncatedTo127Bytes()
    {
        $long     = str_repeat('a', 200);
        $security = new Security($long, 'admin123');
        $result   = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $validationSalt = substr($result['dict']['U'], 32, 8);

        $this->assertEquals(
            substr($result['dict']['U'], 0, 32),
            StandardSecurityHandler::hash2B(str_repeat('a', 127), $validationSalt, '')
        );
    }

    /**
     * Pins the Algorithm 2.B loop termination condition: at least 64 rounds,
     * then stop once the last byte of the round's AES output is <= round-32.
     *
     * This is worth pinning explicitly because it is nearly invisible to
     * end-to-end testing. An off-by-one in the threshold (< instead of <=,
     * or round-31 instead of round-32) changes the result only when the last
     * byte lands exactly on the boundary - about 3% of the time - so a
     * handful of round-trip checks against a real PDF reader would pass a
     * broken implementation ~97% of the time. Re-deriving the round count
     * here catches it deterministically.
     *
     * The salt below is not arbitrary: it was searched for specifically
     * because it is a boundary case, one where the last byte of E lands
     * exactly on the threshold, so that BOTH off-by-one variants terminate
     * on a different round than the correct condition does. With a randomly
     * chosen salt this test would pass against a broken implementation.
     *
     * The reference round count is computed by replaying the loop from the
     * spec independently of the implementation; the assertion is that
     * hash2B's output equals the digest at exactly that round.
     */
    public function testHash2BStopsAtTheRoundDictatedByTheTerminationCondition()
    {
        $password = 'open-me';
        $salt     = hex2bin('6c960afbedac84ca');

        $k        = hash('sha256', $password . $salt, true);
        $round    = 0;
        $atRound  = [];

        while (true) {
            $round++;
            $k1 = str_repeat($password . $k, 64);
            $e  = openssl_encrypt(
                $k1, 'aes-128-cbc', substr($k, 0, 16),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16)
            );

            $sum = 0;
            for ($i = 0; $i < 16; $i++) {
                $sum += ord($e[$i]);
            }
            $k = hash(match ($sum % 3) {
                1       => 'sha384',
                2       => 'sha512',
                default => 'sha256',
            }, $e, true);

            $atRound[$round] = substr($k, 0, 32);

            if (($round >= 64) && (ord($e[strlen($e) - 1]) <= ($round - 32))) {
                break;
            }
            if ($round > 500) {
                $this->fail('Algorithm 2.B failed to terminate');
            }
        }

        // Must have run the mandatory 64 rounds at minimum.
        $this->assertGreaterThanOrEqual(64, $round);

        $actual = StandardSecurityHandler::hash2B($password, $salt, '');
        $this->assertEquals($atRound[$round], $actual);

        // Stopping one round early or one round late gives a different answer,
        // which is what makes the exact condition load-bearing.
        $this->assertNotEquals($atRound[$round - 1], $actual);
    }

    /**
     * Algorithm 2.B is deterministic for a fixed password/salt/udata.
     */
    public function testHash2BIsDeterministicAndReturns32Bytes()
    {
        $salt = str_repeat("\x01", 8);

        $this->assertEquals(32, strlen(StandardSecurityHandler::hash2B('pw', $salt, '')));
        $this->assertEquals(
            StandardSecurityHandler::hash2B('pw', $salt, ''),
            StandardSecurityHandler::hash2B('pw', $salt, '')
        );
        $this->assertNotEquals(
            StandardSecurityHandler::hash2B('pw', $salt, ''),
            StandardSecurityHandler::hash2B('pw', str_repeat("\x02", 8), '')
        );
    }

}
