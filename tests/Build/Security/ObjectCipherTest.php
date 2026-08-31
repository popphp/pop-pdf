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
}
