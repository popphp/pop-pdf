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
     * ISO 32000-2 7.6.4.3.3: a password is truncated to 127 bytes before it
     * is hashed. buildRevision6() does that truncation, so openRevision6()
     * must do the identical truncation or a password longer than 127 bytes
     * could never reopen a document this library had just written - the
     * build side would have hashed 127 bytes and the open side 204.
     *
     * The tail beyond byte 127 is deliberately distinct from the repeated
     * prefix: with a uniform str_repeat() password the truncated and
     * untruncated forms would hash differently anyway, but this makes the
     * "only the first 127 bytes count" property the explicit subject.
     */
    public function testOpenRevision6RecoversTheFileKeyWithAPasswordLongerThan127Bytes()
    {
        $longPassword = str_repeat('x', 200) . 'TAIL';
        $security     = new Security($longPassword, 'admin123');
        $built        = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision6($built['dict'], $longPassword));

        // ...and anything sharing those first 127 bytes opens it too, which
        // is what truncation MEANS - a reader that kept all 204 bytes would
        // reject this one.
        $this->assertEquals(
            $built['fileKey'],
            StandardSecurityHandler::openRevision6($built['dict'], str_repeat('x', 127) . 'DIFFERENT-TAIL')
        );
    }

    /**
     * ISO 32000-2 fixes /U and /O at exactly 48 bytes, but producers
     * descended from the pre-standard Adobe extension level 3 revision pad
     * them out to 127 with trailing bytes that carry no meaning, and every
     * reader worth interoperating with ignores the excess (pdf.js truncates
     * both to 48 on the way in).
     *
     * Both assertions below bite: without the truncation the length guard
     * rejects a 127-byte /U outright, and even past that the owner path
     * would hand hash2B() all 127 bytes as udata when /O was only ever
     * computed over the first 48.
     */
    public function testOpenRevision6IgnoresUAndOPaddingBeyond48Bytes()
    {
        $security = new Security('open-me', 'admin123');
        $built    = StandardSecurityHandler::buildRevision6($security, random_bytes(16));

        $dict = $built['dict'];
        $dict['U'] .= random_bytes(79);
        $dict['O'] .= random_bytes(79);
        $this->assertEquals(127, strlen($dict['U']));
        $this->assertEquals(127, strlen($dict['O']));

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision6($dict, 'open-me'));
        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision6($dict, 'admin123'));
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

        // Not tempnam(): tempnam() creates a file AT the path it returns, so
        // appending '.pdf' to it leaves that original file orphaned in the
        // temp dir on every single run. The try/finally then guarantees both
        // files are removed even when an assertion below throws.
        $source = sys_get_temp_dir() . '/pop_pdf_r6_src_' . uniqid() . '.pdf';
        $target = sys_get_temp_dir() . '/pop_pdf_r6_enc_' . uniqid() . '.pdf';

        try {
            copy(__DIR__ . '/../../tmp/test-extract.pdf', $source);

            exec(
                'qpdf --encrypt open-me admin123 256 -- ' . escapeshellarg($source) . ' ' .
                escapeshellarg($target) . ' 2>&1',
                $output, $status
            );
            $this->assertEquals(0, $status, implode("\n", $output));

            $dict = $this->extractR6EncryptDict((string)file_get_contents($target));
        } finally {
            foreach ([$source, $target] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

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

    /**
     * ISO 32000-1 Annex C, Algorithm 6: derive a candidate file key from the
     * supplied password via Algorithm 2, re-run Algorithm 5, and compare
     * against /U. Round-tripping buildRevision4()'s own output proves the
     * open direction is the exact inverse of the build direction.
     */
    public function testOpenRevision4RecoversTheFileKeyWithTheUserPassword()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $recovered = StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'open-me');

        $this->assertEquals($built['fileKey'], $recovered);
    }

    /**
     * ISO 32000-1 Annex C, Algorithm 7: the owner password does not derive
     * the file key directly - it decrypts /O back to the padded USER
     * password, which then feeds Algorithm 2. Deleting the owner fallback
     * from openRevision4() fails this test and nothing else in this file.
     */
    public function testOpenRevision4RecoversTheFileKeyWithTheOwnerPassword()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $recovered = StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'admin123');

        $this->assertEquals($built['fileKey'], $recovered);
    }

    /**
     * ISO 32000-1 Algorithm 2 step (f): when /EncryptMetadata is false, four
     * 0xFF bytes are appended to the key digest's input. That makes the flag
     * part of the KEY, not just of how metadata is handled, so honoring it is
     * the difference between opening such a document and telling its owner
     * their correct password is wrong.
     */
    public function testOpenRevision4HonorsEncryptMetadataFalseInTheKeyDerivation()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);

        // buildRevision4() always encrypts metadata, so its /U was computed
        // WITHOUT step (f). Claiming /EncryptMetadata false over that dict
        // must therefore derive a different key and be rejected - which is
        // only possible if the flag actually reaches the derivation.
        $built = StandardSecurityHandler::buildRevision4($security, $fileId);

        $this->assertEquals(
            $built['fileKey'],
            StandardSecurityHandler::openRevision4($built['dict'] + ['EncryptMetadata' => true], $fileId, 'open-me')
        );

        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);
        $this->expectExceptionMessage('password provided is incorrect');

        StandardSecurityHandler::openRevision4($built['dict'] + ['EncryptMetadata' => false], $fileId, 'open-me');
    }

    public function testDeriveRevision4FileKeyFromUserPasswordChangesWithEncryptMetadata()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $withMetadata = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(
            'open-me', $built['dict']['O'], $built['dict']['P'], $fileId, true
        );
        $without = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(
            'open-me', $built['dict']['O'], $built['dict']['P'], $fileId, false
        );

        // The default must stay "metadata is encrypted", so no existing
        // caller's behavior changes.
        $this->assertEquals($built['fileKey'], $withMetadata);
        $this->assertEquals($withMetadata, StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(
            'open-me', $built['dict']['O'], $built['dict']['P'], $fileId
        ));
        $this->assertEquals(16, strlen($without));
        $this->assertNotEquals($withMetadata, $without);
    }

    public function testOpenRevision4ThrowsForAnIncorrectPassword()
    {
        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);

        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'wrong-password');
    }

    /**
     * Algorithm 2 is a pure function of its inputs and will happily return a
     * 16-byte key for ANY password - the /U comparison is the only thing that
     * distinguishes right from wrong. An implementation that returned the
     * derived key without comparing would pass both round-trip tests above
     * and hand every caller a garbage key for a mistyped password, so pin
     * that the rejected key is not merely "an error" but specifically not the
     * real file key.
     */
    public function testOpenRevision4RejectsRatherThanReturningAWrongKey()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $wrongKey = StandardSecurityHandler::deriveRevision4FileKeyFromUserPassword(
            'wrong-password', $built['dict']['O'], $built['dict']['P'], $fileId
        );

        $this->assertEquals(16, strlen($wrongKey));
        $this->assertNotEquals($built['fileKey'], $wrongKey);

        try {
            StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'wrong-password');
            $this->fail('openRevision4() accepted an incorrect password');
        } catch (\Pop\Pdf\Build\Security\Exception $e) {
            $this->assertStringContainsString('incorrect', $e->getMessage());
        }
    }

    /**
     * ISO 32000-1 Algorithm 5 specifies only the FIRST 16 bytes of /U; the
     * remaining 16 are arbitrary padding brought along to reach the fixed
     * 32-byte width. buildRevision4() writes zeros there, but qpdf writes
     * random bytes and both are conforming - so openRevision4() must compare
     * 16 bytes, not 32.
     *
     * This is the one revision 4 read-path mistake that self-consistency can
     * never catch: comparing all 32 bytes round-trips perfectly against this
     * library's own output and rejects every file anyone else wrote. Here the
     * tail is deliberately overwritten with non-zero bytes, which a 32-byte
     * comparison would reject and a 16-byte comparison ignores.
     */
    public function testOpenRevision4ComparesOnlyTheFirstSixteenBytesOfU()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $dict = $built['dict'];
        $this->assertEquals(str_repeat("\x00", 16), substr($dict['U'], 16, 16));
        $dict['U'] = substr($dict['U'], 0, 16) . random_bytes(16);
        $this->assertEquals(32, strlen($dict['U']));

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision4($dict, $fileId, 'open-me'));
        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision4($dict, $fileId, 'admin123'));
    }

    /**
     * A malformed /Encrypt dictionary is a read-path input coming straight
     * off disk. It must produce a clear exception rather than a silent
     * "incorrect password", which would send a caller hunting for the wrong
     * problem entirely - /O in particular is fed WHOLE into Algorithm 2's
     * digest, so a wrong-length /O yields a wrong key with no other symptom.
     *
     * Note revision 4 gets none of the over-long /U and /O leniency
     * openRevision6() allows: that padding quirk belongs to the pre-standard
     * Adobe extension level 3 AES-256 revision, which postdates revision 4.
     */
    public function testOpenRevision4ThrowsForAMalformedEncryptDictionary()
    {
        $fileId = random_bytes(16);

        $cases = [
            'short /O'   => ['O' => random_bytes(20), 'U' => random_bytes(32), 'P' => -1],
            'long /O'    => ['O' => random_bytes(48), 'U' => random_bytes(32), 'P' => -1],
            'short /U'   => ['O' => random_bytes(32), 'U' => random_bytes(16), 'P' => -1],
            'long /U'    => ['O' => random_bytes(32), 'U' => random_bytes(48), 'P' => -1],
            'missing /P' => ['O' => random_bytes(32), 'U' => random_bytes(32)],
        ];

        foreach ($cases as $label => $dict) {
            try {
                StandardSecurityHandler::openRevision4($dict, $fileId, 'open-me');
                $this->fail('openRevision4() accepted a malformed dictionary: ' . $label);
            } catch (\Pop\Pdf\Build\Security\Exception $e) {
                $this->assertStringContainsString('malformed', $e->getMessage(), $label);
            }
        }
    }

    /**
     * An absent user password means the document opens with the EMPTY
     * password - the case every reader hits first on an owner-password-only
     * document, and the one an implementation that treated '' as "no password
     * supplied" would get wrong.
     */
    public function testOpenRevision4OpensAnOwnerOnlyDocumentWithTheEmptyPassword()
    {
        $security = new Security(null, 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision4($built['dict'], $fileId, ''));
        $this->assertEquals(
            $built['fileKey'], StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'admin123')
        );
    }

    /**
     * ISO 32000-1 Algorithm 2 step (a): revision 4 passwords are padded or
     * TRUNCATED to exactly 32 bytes. openRevision4() must apply the identical
     * truncation on both paths or a password longer than 32 bytes could never
     * reopen a document this library had just written.
     *
     * The tail past byte 32 is deliberately distinct from the repeated prefix
     * so that "only the first 32 bytes count" is the explicit subject rather
     * than an accident of a uniform password.
     */
    public function testOpenRevision4TruncatesBothPasswordsTo32Bytes()
    {
        $longUser  = str_repeat('u', 40) . 'USER-TAIL';
        $longOwner = str_repeat('o', 40) . 'OWNER-TAIL';
        $security  = new Security($longUser, $longOwner);
        $fileId    = random_bytes(16);
        $built     = StandardSecurityHandler::buildRevision4($security, $fileId);

        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision4($built['dict'], $fileId, $longUser));
        $this->assertEquals($built['fileKey'], StandardSecurityHandler::openRevision4($built['dict'], $fileId, $longOwner));

        // ...and anything sharing those first 32 bytes opens it too, which is
        // what truncation MEANS - on the owner path as much as the user one.
        $this->assertEquals(
            $built['fileKey'],
            StandardSecurityHandler::openRevision4($built['dict'], $fileId, str_repeat('u', 32) . 'DIFFERENT')
        );
        $this->assertEquals(
            $built['fileKey'],
            StandardSecurityHandler::openRevision4($built['dict'], $fileId, str_repeat('o', 32) . 'DIFFERENT')
        );
    }

    /**
     * The file key is bound to /P and the file /ID (Algorithm 2) and to the
     * document /ID again through /U (Algorithm 5). Opening with a /P or /ID
     * that is not the one the file was built with must therefore FAIL rather
     * than quietly return a plausible 16 bytes - which is exactly what would
     * happen if openRevision4() skipped the /U comparison.
     */
    public function testOpenRevision4FailsWhenPOrTheFileIdDoesNotMatch()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $tamperedP       = $built['dict'];
        $tamperedP['P']  = -4000;

        foreach ([[$tamperedP, $fileId], [$built['dict'], random_bytes(16)]] as [$dict, $id]) {
            foreach (['open-me', 'admin123'] as $password) {
                try {
                    StandardSecurityHandler::openRevision4($dict, $id, $password);
                    $this->fail('openRevision4() accepted a mismatched /P or /ID');
                } catch (\Pop\Pdf\Build\Security\Exception $e) {
                    $this->assertStringContainsString('incorrect', $e->getMessage());
                }
            }
        }
    }

    /**
     * An independent replay of Algorithm 7, deliberately running the 19
     * XOR'd-key rounds in ASCENDING order while recoverPaddedUserPassword()
     * runs them descending as the spec words it. Both must land on the padded
     * user password, which is what makes the order genuinely irrelevant
     * rather than merely asserted in a docblock: Rc4::crypt() rebuilds its
     * state per call, so the chain is a commutative XOR of 20 keystreams.
     *
     * The second half then pins that openRevision4()'s owner path really does
     * route through that recovered password - the key it returns is exactly
     * the one Algorithm 2 produces from these recovered bytes.
     */
    public function testOpenRevision4OwnerPathRoutesThroughTheRecoveredPaddedUserPassword()
    {
        $security = new Security('open-me', 'admin123');
        $fileId   = random_bytes(16);
        $built    = StandardSecurityHandler::buildRevision4($security, $fileId);

        $rc4Key    = $this->oRc4Key('admin123');
        $recovered = Rc4::crypt($rc4Key, $built['dict']['O']);
        for ($round = 1; $round <= 19; $round++) {
            $recovered = Rc4::crypt($this->xorKey($rc4Key, $round), $recovered);
        }

        $this->assertEquals($this->pad('open-me'), $recovered);

        // Algorithm 2 over the recovered padded password is the file key the
        // owner path must return.
        $digest = md5($recovered . $built['dict']['O'] . pack('V', $built['dict']['P'] & 0xFFFFFFFF) . $fileId, true);
        for ($i = 0; $i < 50; $i++) {
            $digest = md5(substr($digest, 0, 16), true);
        }

        $this->assertEquals(
            substr($digest, 0, 16),
            StandardSecurityHandler::openRevision4($built['dict'], $fileId, 'admin123')
        );
    }

    /**
     * The interoperability test that actually matters: open an AES-128 /
     * revision 4 file that qpdf - an entirely independent implementation -
     * encrypted, and which this library never wrote a byte of. Self-
     * consistency with buildRevision4() cannot catch a shared misreading of
     * Algorithms 2/3/5 (a mis-ordered digest input, big-endian /P, the wrong
     * 50-round truncation rule, a full-32-byte /U comparison); this can.
     *
     * And it does not stop at "both passwords produced the same 16 bytes" -
     * two identically-wrong paths would satisfy that. The recovered key is
     * used to actually decrypt the page content stream (Algorithm 1's
     * per-object key, then AES-128-CBC with the leading 16 bytes as the IV,
     * then Flate) and the result is checked for real PDF text-showing
     * operators. Nothing but the true file encryption key produces those.
     */
    public function testOpenRevision4RecoversTheFileKeyFromAQpdfEncryptedFile()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        // Not tempnam(): tempnam() creates a file AT the path it returns, so
        // appending '.pdf' to it leaves that original file orphaned in the
        // temp dir on every run. The try/finally guarantees both are removed
        // even when an assertion below throws.
        $source = sys_get_temp_dir() . '/pop_pdf_r4_src_' . uniqid() . '.pdf';
        $target = sys_get_temp_dir() . '/pop_pdf_r4_enc_' . uniqid() . '.pdf';

        try {
            copy(__DIR__ . '/../../tmp/test-extract.pdf', $source);

            // --use-aes=y is required, not decorative: without it qpdf 12
            // would emit RC4 (/CFM /V2) for a 128-bit key, and refuses to
            // write that at all without --allow-weak-crypto.
            exec(
                'qpdf --encrypt open-me admin123 128 --use-aes=y -- ' . escapeshellarg($source) . ' ' .
                escapeshellarg($target) . ' 2>&1',
                $output, $status
            );
            $this->assertEquals(0, $status, implode("\n", $output));

            $pdfData = (string)file_get_contents($target);
        } finally {
            foreach ([$source, $target] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        $dict   = $this->extractR4EncryptDict($pdfData);
        $fileId = $this->extractFirstFileId($pdfData);

        $fromUser  = StandardSecurityHandler::openRevision4($dict, $fileId, 'open-me');
        $fromOwner = StandardSecurityHandler::openRevision4($dict, $fileId, 'admin123');

        $this->assertEquals(16, strlen($fromUser));
        $this->assertEquals($fromUser, $fromOwner);

        // qpdf fills /U's trailing 16 bytes with non-zero data where this
        // library writes zeros. That is what makes the 16-byte comparison in
        // openRevision4() load-bearing, so assert the fixture really does
        // exercise it rather than trusting that it happens to.
        $this->assertNotEquals(str_repeat("\x00", 16), substr((string)$dict['U'], 16, 16));

        // Decrypt object 6, the page content stream, with the recovered key.
        $decrypted = $this->decryptAesV2Stream($pdfData, 6, $fromUser);
        $this->assertNotFalse($decrypted, 'AES-128-CBC decryption of the content stream failed');

        $content = @gzuncompress((string)$decrypted);
        $this->assertNotFalse($content, 'the decrypted content stream did not inflate');
        $this->assertStringContainsString('BT', (string)$content);
        $this->assertStringContainsString('Tf', (string)$content);
        $this->assertStringContainsString('re', (string)$content);

        // The negative control: a near-miss key must NOT produce readable
        // content, or the assertions above would prove nothing.
        $wrongKey = $fromUser;
        $wrongKey[0] = chr(ord($wrongKey[0]) ^ 0xFF);
        $this->assertFalse(@gzuncompress((string)$this->decryptAesV2Stream($pdfData, 6, $wrongKey)));

        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);
        StandardSecurityHandler::openRevision4($dict, $fileId, 'not-the-password');
    }

    /**
     * Pull /O, /U and /P out of a raw PDF's Standard security handler
     * dictionary, asserting on the way that the file really is revision 4 /
     * AES-128 - otherwise a change in qpdf's defaults could silently downgrade
     * the fixture to RC4 or revision 3 and the test above would still pass
     * while checking something else.
     *
     * Deliberately a small hand-rolled scan rather than anything from src/:
     * the point of the fixture test is to depend on as little of this library
     * as possible. The dictionary is located by following the trailer's
     * /Encrypt reference rather than by scanning forward from /Filter
     * /Standard, because qpdf sorts the keys and /CF therefore lands BEFORE
     * /Filter - a forward-only window would miss the very /CFM this method
     * exists to check.
     *
     * @param  string $pdfData
     * @return array<string, string|int>
     */
    protected function extractR4EncryptDict(string $pdfData): array
    {
        $this->assertEquals(1, preg_match('#/Encrypt\s+(\d+)\s+0\s+R#', $pdfData, $ref), 'no /Encrypt in trailer');
        $this->assertEquals(
            1,
            preg_match('#[\r\n]' . $ref[1] . '\s+0\s+obj\s*(<<.*?>>)\s*endobj#s', $pdfData, $m),
            'the /Encrypt object was not found'
        );
        $encrypt = $m[1];
        $this->assertStringContainsString('/Filter /Standard', $encrypt);

        $dict = [];
        foreach (['O', 'U'] as $key) {
            $this->assertEquals(
                1, preg_match('#/' . $key . '\s*<([0-9A-Fa-f]+)>#', $encrypt, $found), "no /{$key} in /Encrypt"
            );
            $dict[$key] = (string)hex2bin($found[1]);
            $this->assertEquals(32, strlen((string)$dict[$key]), "/{$key} is not 32 bytes");
        }

        $this->assertEquals(1, preg_match('#/P\s+(-?\d+)#', $encrypt, $p), 'no /P in /Encrypt');
        $dict['P'] = (int)$p[1];

        $this->assertEquals(1, preg_match('#/R\s+(\d+)#', $encrypt, $r), 'no /R in /Encrypt');
        $this->assertEquals(4, (int)$r[1], 'fixture is not revision 4');
        $this->assertEquals(1, preg_match('#/V\s+(\d+)#', $encrypt, $v), 'no /V in /Encrypt');
        $this->assertEquals(4, (int)$v[1], 'fixture is not /V 4');
        $this->assertStringContainsString('/CFM /AESV2', $encrypt, 'fixture is not AES-128');

        // Algorithm 2 step (f) appends 0xFFFFFFFF when metadata is left
        // unencrypted, which this handler does not implement. Assert the
        // fixture does not quietly wander into that case.
        $this->assertStringNotContainsString('/EncryptMetadata false', $encrypt);

        return $dict;
    }

    /**
     * The first element of the trailer's /ID array, which Algorithm 2 mixes
     * into the file key and Algorithm 5 into /U.
     */
    protected function extractFirstFileId(string $pdfData): string
    {
        $this->assertEquals(1, preg_match('#/ID\s*\[\s*<([0-9A-Fa-f]+)>#', $pdfData, $m), 'no /ID in trailer');

        return (string)hex2bin($m[1]);
    }

    /**
     * Decrypt one top-level stream object out of a raw AESV2-encrypted PDF,
     * given the file encryption key.
     *
     * ISO 32000-1 Algorithm 1: the per-object key is MD5 of the file key,
     * the object number as three low-order-first bytes, the generation number
     * as two, and - for AES only - the four extra bytes 0x73 0x41 0x6C 0x54
     * ("sAlT"), truncated to min(keyLength + 5, 16) bytes, which is 16 here.
     * The stream's leading 16 bytes are the CBC initialization vector.
     *
     * @return string|false
     */
    protected function decryptAesV2Stream(string $pdfData, int $objectNumber, string $fileKey)
    {
        $pattern = '#[\r\n]' . $objectNumber . '\s+0\s+obj\s*(<<.*?>>)\s*stream\r?\n#s';
        $this->assertEquals(1, preg_match($pattern, $pdfData, $m, PREG_OFFSET_CAPTURE), 'stream object not found');
        $this->assertEquals(1, preg_match('#/Length\s+(\d+)#', $m[1][0], $length), 'stream has no /Length');
        $this->assertStringContainsString('/FlateDecode', $m[1][0]);

        $raw = substr($pdfData, $m[0][1] + strlen($m[0][0]), (int)$length[1]);
        $this->assertEquals((int)$length[1], strlen($raw));

        $objectKey = substr(
            md5(
                $fileKey . substr(pack('V', $objectNumber), 0, 3) . substr(pack('V', 0), 0, 2) . "\x73\x41\x6C\x54",
                true
            ),
            0, 16
        );

        return openssl_decrypt(substr($raw, 16), 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, substr($raw, 0, 16));
    }

}
