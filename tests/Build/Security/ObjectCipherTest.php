<?php

namespace Pop\Pdf\Test\Build\Security;

use Pop\Pdf\Build\Security\Exception;
use Pop\Pdf\Build\Security\ObjectCipher;
use PHPUnit\Framework\TestCase;

class ObjectCipherTest extends TestCase
{
    public function testEncryptAes256ProducesDataLongerThanPlaintextWithIvPrefix()
    {
        $fileKey    = random_bytes(32);
        $plaintext  = 'Hello, encrypted world!';
        $ciphertext = ObjectCipher::encryptAes256($fileKey, $plaintext);

        // 16-byte IV + at least one 16-byte PKCS#7-padded AES block.
        $this->assertGreaterThanOrEqual(16 + 16, strlen($ciphertext));
        $this->assertNotEquals($plaintext, substr($ciphertext, 16));
    }

    public function testEncryptAes256IsNotDeterministic()
    {
        // A random IV each call means encrypting the same plaintext twice
        // never produces identical ciphertext - this is what actually
        // hides patterns in per-object content.
        $fileKey = random_bytes(32);
        $a = ObjectCipher::encryptAes256($fileKey, 'same plaintext');
        $b = ObjectCipher::encryptAes256($fileKey, 'same plaintext');

        $this->assertNotEquals($a, $b);
    }

    public function testDeriveObjectKeyDiffersByObjectNumber()
    {
        // Directly test that deriveObjectKey produces different output for
        // different object numbers, independent of IV randomness in the
        // encryption methods. This uses reflection to test the protected
        // deriveObjectKey method.
        $fileKey = random_bytes(16);
        $method  = new \ReflectionMethod(ObjectCipher::class, 'deriveObjectKey');
        $method->setAccessible(true);

        $key1 = $method->invoke(null, $fileKey, 1, 0);
        $key2 = $method->invoke(null, $fileKey, 2, 0);

        $this->assertNotEquals($key1, $key2);
    }

    public function testDecryptAes256RecoversTheOriginalPlaintext()
    {
        $fileKey    = random_bytes(32);
        $plaintext  = 'Round-trip me through AES-256-CBC.';
        $ciphertext = ObjectCipher::encryptAes256($fileKey, $plaintext);

        $this->assertEquals($plaintext, ObjectCipher::decryptAes256($fileKey, $ciphertext));
    }

    public function testDecryptAes128RecoversTheOriginalPlaintextForTheCorrectObject()
    {
        $fileKey    = random_bytes(16);
        $plaintext  = str_repeat('A', 48);
        $ciphertext = ObjectCipher::encryptAes128($fileKey, 7, 0, $plaintext);

        $this->assertEquals($plaintext, ObjectCipher::decryptAes128($fileKey, 7, 0, $ciphertext));
    }

    public function testDecryptAes128FailsToRecoverPlaintextForTheWrongObjectNumber()
    {
        // Confirms decrypt genuinely uses the per-object key, not just the
        // file key - decrypting under the wrong object's derived key must not
        // silently produce the right plaintext. AES-CBC's PKCS#7 padding
        // check almost always rejects a wrong-key decrypt outright (which
        // ObjectCipher surfaces as an Exception); on the astronomically
        // rare chance padding happens to validate anyway, the recovered
        // bytes still must not equal the original plaintext. Either outcome
        // proves the per-object key - not just the file key - was used;
        // if decryption incorrectly ignored the object number and reused
        // the same key as encryption, this would neither throw nor differ,
        // and the assertion below would catch that.
        $fileKey    = random_bytes(16);
        $plaintext  = str_repeat('B', 48);
        $ciphertext = ObjectCipher::encryptAes128($fileKey, 7, 0, $plaintext);

        try {
            $recovered = ObjectCipher::decryptAes128($fileKey, 8, 0, $ciphertext);
            $this->assertNotEquals($plaintext, $recovered);
        } catch (Exception $e) {
            $this->assertStringContainsString('decryption failed', $e->getMessage());
        }
    }
}
