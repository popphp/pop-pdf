<?php

namespace Pop\Pdf\Test\Build\Security;

use Pop\Pdf\Build\Security\Rc4;
use PHPUnit\Framework\TestCase;

class Rc4Test extends TestCase
{
    public function testKnownAnswerVector()
    {
        // Widely published RC4 test vector: key "Key", plaintext
        // "Plaintext" -> ciphertext BBF316E8D940AF0AD3 (hex).
        $ciphertext = Rc4::crypt('Key', 'Plaintext');
        $this->assertEquals('bbf316e8d940af0ad3', bin2hex($ciphertext));
    }

    public function testCryptIsSymmetric()
    {
        $key        = 'a-test-key';
        $plaintext  = 'The quick brown fox jumps over the lazy dog.';
        $ciphertext = Rc4::crypt($key, $plaintext);

        $this->assertNotEquals($plaintext, $ciphertext);
        $this->assertEquals($plaintext, Rc4::crypt($key, $ciphertext));
    }
}
