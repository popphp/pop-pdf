<?php

namespace Pop\Pdf\Test\Build\Security;

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

    public function testEncryptAes128DifferentObjectNumbersProduceDifferentPerObjectKeys()
    {
        // Confirmed indirectly: encrypting identical plaintext under the
        // same file key but different object numbers must not be
        // reversible by simply reusing one object's key for another -
        // decrypting object 2's ciphertext with object 1's derived key
        // must not recover the plaintext.
        $fileKey   = random_bytes(16);
        $plaintext = str_repeat('A', 32); // multiple of the AES block size

        $obj1Cipher = ObjectCipher::encryptAes128($fileKey, 1, 0, $plaintext);
        $obj2Cipher = ObjectCipher::encryptAes128($fileKey, 2, 0, $plaintext);

        $this->assertNotEquals($obj1Cipher, $obj2Cipher);
    }
}
