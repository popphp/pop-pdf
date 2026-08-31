<?php

namespace Pop\Pdf\Test\Build\Security;

use Pop\Pdf\Build\Security\Rc4;
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
        // Fully permissive: every bit set except ISO 32000-1 Table 22's
        // reserved bits 1 and 2, which must always be 0.
        $this->assertEquals(-4, $result['dict']['P']);
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

    /**
     * ISO 32000-2 Annex C, Algorithm 11 + 2.A steps (e)/(f): a reader handed
     * the USER password validates it against /U and then unwraps /UE back to
     * the file encryption key. Round-tripping buildRevision6()'s own output
     * proves the open direction is the exact inverse of the build direction.
     */
    public function testOpenRevision6RecoversTheFileKeyWithTheUserPassword()
    {
        $security = new Security('open-me', 'admin123');
        $built    = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $recovered = StandardSecurityHandler::openRevision6($built['dict'], 'open-me');

        $this->assertEquals($built['fileKey'], $recovered);
    }

    /**
     * ISO 32000-2 Annex C, Algorithm 12 + 2.A step (g): the OWNER password
     * follows the parallel path through /O and /OE - and, critically, both
     * owner hashes additionally take the full 48-byte /U string as input.
     * Omitting that /U argument would still yield 32 well-formed bytes and
     * would still be self-consistent with a build direction that omitted it
     * too, so this only bites in combination with the qpdf fixture test
     * below (and with buildRevision6, which does pass /U).
     */
    public function testOpenRevision6RecoversTheFileKeyWithTheOwnerPassword()
    {
        $security = new Security('open-me', 'admin123');
        $built    = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $recovered = StandardSecurityHandler::openRevision6($built['dict'], 'admin123');

        $this->assertEquals($built['fileKey'], $recovered);
    }

    public function testOpenRevision6ThrowsForAnIncorrectPassword()
    {
        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);

        $security = new Security('open-me', 'admin123');
        $built    = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        StandardSecurityHandler::openRevision6($built['dict'], 'wrong-password');
    }

    /**
     * An absent user password means the document opens with the EMPTY
     * password - the case every reader hits first when it encounters an
     * owner-password-only document, and the one an implementation that
     * treated '' as "no password supplied" would get wrong.
     */
    public function testOpenRevision6OpensAnOwnerOnlyDocumentWithTheEmptyPassword()
    {
        $security = new Security(null, 'admin123');
        $built    = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision6($built['dict'], ''));
        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision6($built['dict'], 'admin123'));
    }

    /**
     * A truncated or otherwise malformed /Encrypt dictionary is a read-path
     * input coming straight off disk, so it must produce a clear exception
     * rather than a silent "incorrect password" (which would send a caller
     * hunting for the wrong problem entirely).
     */
    public function testOpenRevision6ThrowsForAMalformedEncryptDictionary()
    {
        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);
        $this->expectExceptionMessage('malformed');

        StandardSecurityHandler::openRevision6(
            ['U' => random_bytes(20), 'O' => random_bytes(48), 'UE' => random_bytes(32), 'OE' => random_bytes(32)],
            'open-me'
        );
    }

    /**
     * The interoperability test that actually matters: open an AES-256 file
     * that qpdf - an entirely independent implementation - encrypted, which
     * this library never wrote a byte of. Self-consistency with
     * buildRevision6() cannot catch a shared misreading of Algorithm 2.B
     * (wrong salt offsets, a missing /U argument on the owner path, a
     * mis-ordered hash input); this can, because qpdf's /U, /O, /UE and /OE
     * were produced by someone else's reading of the same spec.
     *
     * /Perms is the independent oracle for the recovered key itself: per
     * Algorithm 13 it decrypts under the FEK - and under nothing else - to a
     * block carrying the literal marker "adb" at bytes 9-11. Without that
     * check, "both passwords gave the same 32 bytes" would still pass if
     * both paths were wrong in the same way.
     */
    public function testOpenRevision6RecoversTheFileKeyFromAQpdfEncryptedFile()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $source = tempnam(sys_get_temp_dir(), 'pop_pdf_r6_src_') . '.pdf';
        $target = tempnam(sys_get_temp_dir(), 'pop_pdf_r6_enc_') . '.pdf';
        copy(__DIR__ . '/../../tmp/test-extract.pdf', $source);

        exec(
            'qpdf --encrypt open-me admin123 256 -- ' . escapeshellarg($source) . ' ' .
            escapeshellarg($target) . ' 2>&1',
            $output, $status
        );
        $this->assertEquals(0, $status, implode("\n", $output));

        $dict = $this->extractR6EncryptDict((string)file_get_contents($target));

        unlink($source);
        unlink($target);

        $fromUser  = StandardSecurityHandler::openRevision6($dict, 'open-me');
        $fromOwner = StandardSecurityHandler::openRevision6($dict, 'admin123');

        $this->assertEquals(32, strlen($fromUser));
        $this->assertEquals($fromUser, $fromOwner);

        // Algorithm 13 - the recovered key is really THE file encryption key.
        $block = openssl_decrypt(
            (string)$dict['Perms'], 'aes-256-ecb', $fromUser, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );
        $this->assertEquals('adb', substr((string)$block, 9, 3));
        $this->assertEquals(pack('V', ((int)$dict['P']) & 0xFFFFFFFF), substr((string)$block, 0, 4));

        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);
        StandardSecurityHandler::openRevision6($dict, 'not-the-password');
    }

    /**
     * Pull /O, /U, /OE, /UE, /P and /Perms out of a raw PDF's Standard
     * security handler dictionary. Deliberately a small hand-rolled scan
     * rather than anything from src/ - the point of the fixture test above
     * is to depend on as little of this library as possible.
     *
     * @param  string $pdfData
     * @return array<string, string|int>
     */
    protected function extractR6EncryptDict(string $pdfData): array
    {
        $this->assertEquals(1, preg_match('#/Filter\s*/Standard.{0,1200}#s', $pdfData, $m), 'no /Standard dict');
        $encrypt = $m[0];

        $dict = [];
        foreach (['O', 'U', 'OE', 'UE', 'Perms'] as $key) {
            $this->assertEquals(
                1, preg_match('#/' . $key . '\s*<([0-9A-Fa-f]+)>#', $encrypt, $found), "no /{$key} in /Encrypt"
            );
            $dict[$key] = (string)hex2bin($found[1]);
        }
        $this->assertEquals(1, preg_match('#/P\s+(-?\d+)#', $encrypt, $p), 'no /P in /Encrypt');
        $dict['P'] = (int)$p[1];

        $this->assertEquals(48, strlen((string)$dict['U']));
        $this->assertEquals(48, strlen((string)$dict['O']));

        return $dict;
    }

    /**
     * The 32-byte password padding string of ISO 32000-1 Algorithm 2 step (a),
     * replicated here so the test suite does not have to reach into the
     * class under test for the constant it is supposed to be checking.
     */
    const PAD =
        "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08" .
        "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /**
     * Independent replay of Algorithm 2 step (a): pad to 32 bytes, or
     * truncate to 32 bytes if longer.
     */
    protected function pad(string $password): string
    {
        $password = substr($password, 0, 32);
        return $password . substr(self::PAD, 0, 32 - strlen($password));
    }

    /**
     * Independent replay of Algorithm 3 steps (a)-(d): the RC4 key used to
     * encrypt the padded user password into /O. Note the 50-round loop
     * re-hashes the FULL digest here, unlike Algorithm 2's, which truncates
     * to the key length first.
     */
    protected function oRc4Key(string $ownerPassword): string
    {
        $digest = md5($this->pad($ownerPassword), true);
        for ($i = 0; $i < 50; $i++) {
            $digest = md5($digest, true);
        }
        return substr($digest, 0, 16);
    }

    /**
     * Mirror of Algorithm 3 step (g) / Algorithm 5 step (e)'s key XOR, kept
     * local to the test so the implementation's own helper is never the
     * thing validating itself.
     */
    protected function xorKey(string $key, int $round): string
    {
        $result = '';
        for ($i = 0, $len = strlen($key); $i < $len; $i++) {
            $result .= chr(ord($key[$i]) ^ $round);
        }
        return $result;
    }

    public function testBuildRevision4ProducesA16ByteFileKeyAnd32ByteUAndO()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision4($security, random_bytes(16));

        $this->assertEquals(16, strlen($result['fileKey']));
        $this->assertEquals(32, strlen($result['dict']['U']));
        $this->assertEquals(32, strlen($result['dict']['O']));
        // Fully permissive: every bit set except ISO 32000-1 Table 22's
        // reserved bits 1 and 2, which must always be 0.
        $this->assertEquals(-4, $result['dict']['P']);
    }

    public function testBuildRevision4UserPasswordRecoversTheSameFileKey()
    {
        // A round-trip through the same class's own logic: re-deriving the
        // file key from the user password via the published Algorithm 2
        // steps (independent of buildRevision4's random O/U generation
        // order) must match what buildRevision4 actually used.
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $result   = StandardSecurityHandler::buildRevision4($security, $fileId);

        $recomputed = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(
            'open-me', $result['dict']['O'], $result['dict']['P'], $fileId
        );

        $this->assertEquals($result['fileKey'], $recomputed);
    }

    /**
     * ISO 32000-1 Algorithm 7 - what a conforming reader does when it is
     * handed the OWNER password: derive the Algorithm 3 RC4 key from it,
     * then run the 19 XOR'd-key rounds BACKWARDS (counter 19 down to 1)
     * followed by the unmodified key, and what falls out is the padded USER
     * password. That user password then feeds Algorithm 2 to get the file
     * key, which is the whole reason an owner password can open a document.
     *
     * This is the single most valuable test in this file for the revision 4
     * direction: it pins the exact multiset of round keys Algorithm 3 step
     * (g) uses and the fact that the rounds chain. An implementation that
     * applied each round to the ORIGINAL RC4 output instead of the previous
     * round's, or ran 18 or 20 rounds, would still emit 32 well-formed
     * random-looking bytes and pass every length assertion - but this
     * reversal would not come back to the padded user password, and no real
     * PDF reader would ever accept the owner password.
     */
    public function testOwnerPasswordDecryptsORevision4BackToThePaddedUserPassword()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision4($security, random_bytes(16));

        $rc4Key    = $this->oRc4Key('admin123');
        $decrypted = $result['dict']['O'];
        for ($round = 19; $round >= 1; $round--) {
            $decrypted = Rc4::crypt($this->xorKey($rc4Key, $round), $decrypted);
        }
        $decrypted = Rc4::crypt($rc4Key, $decrypted);

        $this->assertEquals($this->pad('open-me'), $decrypted);
    }

    /**
     * Pins the round COUNT in Algorithm 3 step (g) at exactly 19 extra
     * rounds on top of the initial unmodified-key pass. Unwinding with 18
     * or with 20 must fail; only 19 recovers the padded user password.
     *
     * Worth stating plainly, because it corrects a widespread intuition
     * about this loop: with RC4 the ORDER of the rounds does not actually
     * matter. RC4's keystream depends only on the key and the output
     * length, and every round here operates on the same 32 bytes, so the
     * whole chain collapses algebraically to
     *
     *     O = padded_user_password XOR keystream(K) XOR keystream(K^1)
     *           XOR ... XOR keystream(K^19)
     *
     * and XOR is commutative. Running the counter 19 down to 1, or in a
     * shuffled order, or putting the unmodified-key pass last, all produce
     * the identical /O. What IS load-bearing is the SET of round keys - all
     * 20 of K, K^1 ... K^19, no more and no fewer - and that each round is
     * applied to the previous round's output so those keystreams actually
     * accumulate. Those are what this test and the one above nail down.
     */
    public function testORevision4UsesExactlyNineteenExtraRounds()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision4($security, random_bytes(16));
        $rc4Key   = $this->oRc4Key('admin123');

        foreach ([18, 19, 20] as $rounds) {
            $decrypted = $result['dict']['O'];
            for ($round = $rounds; $round >= 1; $round--) {
                $decrypted = Rc4::crypt($this->xorKey($rc4Key, $round), $decrypted);
            }
            $decrypted = Rc4::crypt($rc4Key, $decrypted);

            if ($rounds === 19) {
                $this->assertEquals($this->pad('open-me'), $decrypted);
            } else {
                $this->assertNotEquals($this->pad('open-me'), $decrypted);
            }
        }
    }

    /**
     * The rounds must chain onto each other. An implementation that ran 19
     * rounds but fed each one the ORIGINAL first-pass ciphertext would end
     * up with only two keystreams XOR'd in instead of twenty.
     */
    public function testORevision4RoundsChainRatherThanRestart()
    {
        $security = new Security('open-me', 'admin123');
        $result   = StandardSecurityHandler::buildRevision4($security, random_bytes(16));
        $rc4Key   = $this->oRc4Key('admin123');

        $singlePass = Rc4::crypt($rc4Key, $this->pad('open-me'));
        $unchained  = $singlePass;
        for ($round = 1; $round <= 19; $round++) {
            $unchained = Rc4::crypt($this->xorKey($rc4Key, $round), $singlePass);
        }

        $this->assertNotEquals($singlePass, $result['dict']['O']);
        $this->assertNotEquals($unchained, $result['dict']['O']);
    }

    /**
     * ISO 32000-1 Algorithm 3 with no owner password set: /O is computed
     * from the effective owner password, which Document\Security generates
     * at random rather than leaving blank. The user password must therefore
     * NOT decrypt /O - that would mean the document had, in effect, no
     * owner password at all and its permissions could be stripped by anyone.
     */
    public function testORevision4UsesTheEffectiveOwnerPasswordWhenNoneWasSet()
    {
        $security = new Security('open-me');
        $result   = StandardSecurityHandler::buildRevision4($security, random_bytes(16));

        $rc4Key    = $this->oRc4Key('open-me');
        $decrypted = $result['dict']['O'];
        for ($round = 19; $round >= 1; $round--) {
            $decrypted = Rc4::crypt($this->xorKey($rc4Key, $round), $decrypted);
        }
        $decrypted = Rc4::crypt($rc4Key, $decrypted);

        $this->assertNotEquals($this->pad('open-me'), $decrypted);

        // ...but the password Security actually generated does decrypt it.
        $rc4Key    = $this->oRc4Key($security->getEffectiveOwnerPassword());
        $decrypted = $result['dict']['O'];
        for ($round = 19; $round >= 1; $round--) {
            $decrypted = Rc4::crypt($this->xorKey($rc4Key, $round), $decrypted);
        }
        $decrypted = Rc4::crypt($rc4Key, $decrypted);

        $this->assertEquals($this->pad('open-me'), $decrypted);
    }

    /**
     * ISO 32000-1 Algorithm 2, replayed independently of the class under
     * test. Pins the ingredient ORDER (padded user password, /O, /P as four
     * little-endian bytes, then the first /ID element) and - critically -
     * the 50-round loop's truncation of the digest to the key length before
     * re-hashing, which is the step Algorithm 3's otherwise near-identical
     * loop deliberately does NOT do.
     *
     * The permissions are deliberately restricted rather than left at their
     * default. A fully-permissive /P is -1, which packs to FF FF FF FF - a
     * byte palindrome, so a big-endian/little-endian mix-up would be
     * entirely invisible with the default value. Denying two permissions
     * yields an asymmetric /P that only matches when packed low-order byte
     * first, which is what the spec requires.
     */
    public function testRevision4FileKeyMatchesAnIndependentReplayOfAlgorithm2()
    {
        $permissions = (new Permissions())->allowCopying(false)->allowPrinting(false);
        $security    = new Security('open-me', 'admin123', $permissions);
        $fileId      = random_bytes(16);
        $result      = StandardSecurityHandler::buildRevision4($security, $fileId);

        $packed = pack('V', $result['dict']['P'] & 0xFFFFFFFF);
        $this->assertNotEquals(strrev($packed), $packed, '/P must be byte-asymmetric for this test to bite');

        $digest = md5(
            $this->pad('open-me') . $result['dict']['O'] .
            pack('V', $result['dict']['P'] & 0xFFFFFFFF) . $fileId,
            true
        );
        for ($i = 0; $i < 50; $i++) {
            $digest = md5(substr($digest, 0, 16), true);
        }

        $this->assertEquals(substr($digest, 0, 16), $result['fileKey']);
    }

    /**
     * Changing /P or the file /ID must change the file key - both are mixed
     * into Algorithm 2, and dropping either would still yield a 16-byte key
     * that round-trips against itself.
     */
    public function testRevision4FileKeyDependsOnBothPAndTheFileId()
    {
        $fileId  = random_bytes(16);
        $o       = random_bytes(32);

        $base    = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword('open-me', $o, -1, $fileId);
        $otherP  = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword('open-me', $o, -4, $fileId);
        $otherId = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword('open-me', $o, -1, random_bytes(16));
        $otherO  = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword('open-me', random_bytes(32), -1, $fileId);

        $this->assertNotEquals($base, $otherP);
        $this->assertNotEquals($base, $otherId);
        $this->assertNotEquals($base, $otherO);
    }

    /**
     * ISO 32000-1 Algorithm 5, replayed independently: MD5 of the padding
     * string followed by the first /ID element, RC4'd with the file key,
     * then the same 19 XOR'd-key chained rounds as Algorithm 3, then 16
     * bytes of arbitrary padding appended to reach 32.
     *
     * Note the padding string is hashed on its own here - Algorithm 5 does
     * NOT hash the padded user password, a natural-looking mistake that
     * would produce a /U no reader could validate.
     */
    public function testURevision4MatchesAnIndependentReplayOfAlgorithm5()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $result   = StandardSecurityHandler::buildRevision4($security, $fileId);

        $expected = Rc4::crypt($result['fileKey'], md5(self::PAD . $fileId, true));
        for ($round = 1; $round <= 19; $round++) {
            $expected = Rc4::crypt($this->xorKey($result['fileKey'], $round), $expected);
        }

        $this->assertEquals($expected, substr($result['dict']['U'], 0, 16));
        $this->assertEquals(str_repeat("\x00", 16), substr($result['dict']['U'], 16, 16));
    }

    /**
     * The 19 rounds must actually run and must chain. A single RC4 pass, or
     * 19 rounds all applied to the same starting ciphertext, would both be
     * wrong, and both would produce output of exactly the right shape.
     */
    public function testURevision4IsNotJustASingleRc4Pass()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $result   = StandardSecurityHandler::buildRevision4($security, $fileId);

        $digest    = md5(self::PAD . $fileId, true);
        $singlePass = Rc4::crypt($result['fileKey'], $digest);

        $unchained = $singlePass;
        for ($round = 1; $round <= 19; $round++) {
            $unchained = Rc4::crypt($this->xorKey($result['fileKey'], $round), $singlePass);
        }

        $this->assertNotEquals($singlePass, substr($result['dict']['U'], 0, 16));
        $this->assertNotEquals($unchained, substr($result['dict']['U'], 0, 16));
    }

    /**
     * ISO 32000-1 Algorithm 2 step (a): revision 4 passwords are padded or
     * TRUNCATED to exactly 32 bytes - a much shorter limit than revision 6's
     * 127 bytes, and a separate scheme entirely. A 50-byte password and its
     * first 32 bytes must therefore produce an identical file key.
     */
    public function testRevision4PasswordsAreTruncatedTo32Bytes()
    {
        $fileId = random_bytes(16);
        $o      = random_bytes(32);

        $this->assertEquals(
            StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(str_repeat('a', 50), $o, -1, $fileId),
            StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(str_repeat('a', 32), $o, -1, $fileId)
        );
        $this->assertNotEquals(
            StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(str_repeat('a', 50), $o, -1, $fileId),
            StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(str_repeat('a', 31), $o, -1, $fileId)
        );
    }

    /**
     * An absent user password means "opens with an empty password": the
     * padded password is then exactly the 32-byte padding string.
     */
    public function testRevision4EmptyUserPasswordPadsToThePaddingStringAlone()
    {
        $security = new Security(null, 'admin123');
        $fileId   = random_bytes(16);
        $result   = StandardSecurityHandler::buildRevision4($security, $fileId);

        $digest = md5(
            self::PAD . $result['dict']['O'] . pack('V', $result['dict']['P'] & 0xFFFFFFFF) . $fileId,
            true
        );
        for ($i = 0; $i < 50; $i++) {
            $digest = md5(substr($digest, 0, 16), true);
        }

        $this->assertEquals(substr($digest, 0, 16), $result['fileKey']);
        $this->assertEquals(
            $result['fileKey'],
            StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword('', $result['dict']['O'], $result['dict']['P'], $fileId)
        );
    }

    /**
     * /P is written into the key derivation as an unsigned 32-bit
     * little-endian value; the common restricted-permissions case must not
     * blow up on the sign of the PHP int.
     */
    public function testRevision4CarriesRestrictedPermissionsThroughToP()
    {
        $permissions = (new Permissions())->allowCopying(false)->allowPrinting(false);
        $security    = new Security('open-me', 'admin123', $permissions);
        $result      = StandardSecurityHandler::buildRevision4($security, random_bytes(16));

        $this->assertEquals($permissions->toPValue(), $result['dict']['P']);
        $this->assertNotEquals(-1, $result['dict']['P']);
        $this->assertEquals(16, strlen($result['fileKey']));
    }

}
