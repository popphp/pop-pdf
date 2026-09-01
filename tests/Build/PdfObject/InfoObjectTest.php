<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\InfoObject;
use Pop\Pdf\Document\Metadata;
use Pop\Pdf\Document\Page\Text;
use PHPUnit\Framework\TestCase;

class InfoObjectTest extends TestCase
{

    public function testConstructorSetsMetadataWhenProvided()
    {
        $metadata = new Metadata();
        $metadata->setTitle('My Title');

        $info = new InfoObject(3, $metadata);

        $this->assertSame($metadata, $info->getMetadata());
        $this->assertEquals('My Title', $info->getMetadata()->getTitle());
    }

    public function testGetMetadataLazyInitializesDefaultWhenNoneProvided()
    {
        $info     = new InfoObject();
        $metadata = $info->getMetadata();

        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertEquals('Pop PDF', $metadata->getTitle());
    }

    public function testToStringInitializesMetadataWhenNull()
    {
        $info   = new InfoObject(3);
        $result = (string) $info;

        $this->assertStringContainsString('/Creator(Pop PDF)', $result);
        $this->assertStringContainsString('/Author(Pop PDF)', $result);
        $this->assertStringContainsString('/Title(Pop PDF)', $result);
    }

    public function testParseWithAllFieldsPresentExtractsMetadataAndRoundTrips()
    {
        $stream = "3 0 obj\n<</Creator(Acme Creator)/CreationDate(D:20240101000000)/ModDate(D:20240102000000)" .
            "/Author(Jane Doe)/Title(Annual Report)/Subject(Finance)/Producer(Acme Producer)>>\nendobj\n";

        $info = InfoObject::parse($stream);

        $this->assertEquals(3, $info->getIndex());

        $metadata = $info->getMetadata();
        $this->assertEquals('Acme Creator', $metadata->getCreator());
        $this->assertEquals('D:20240101000000', $metadata->getCreationDate());
        $this->assertEquals('D:20240102000000', $metadata->getModDate());
        $this->assertEquals('Jane Doe', $metadata->getAuthor());
        $this->assertEquals('Annual Report', $metadata->getTitle());
        $this->assertEquals('Finance', $metadata->getSubject());
        $this->assertEquals('Acme Producer', $metadata->getProducer());

        $result = (string) $info;
        $this->assertStringContainsString('/Creator(Acme Creator)', $result);
        $this->assertStringContainsString('/Title(Annual Report)', $result);
        $this->assertStringContainsString('/Producer(Acme Producer)', $result);
    }

    public function testParseWithNoFieldsPresentFallsBackToDefaults()
    {
        $stream = "3 0 obj\n<<>>\nendobj\n";

        $info = InfoObject::parse($stream);

        $this->assertEquals(3, $info->getIndex());

        $result = (string) $info;
        $this->assertStringContainsString('/Creator(Pop PDF)', $result);
        $this->assertStringContainsString('/Title(Pop PDF)', $result);
        $this->assertStringContainsString('/Author(Pop PDF)', $result);
        $this->assertStringContainsString('/Subject(Pop PDF)', $result);
        $this->assertStringContainsString('/Producer(Pop PDF)', $result);
    }

    public function testEncryptWithEscapesRawCrLfBytesInEncryptedOutput()
    {
        // Regression pin for the bug found while qpdf-verifying Task 10:
        // AES ciphertext is arbitrary binary and routinely contains raw
        // 0x0D/0x0A bytes. A compliant literal-string reader normalizes an
        // *unescaped* CR (or CR/LF pair) to a bare LF (ISO 32000-1 7.3.4.2),
        // silently altering the byte and corrupting the whole AES-CBC block
        // it belongs to once decrypted. This test uses a deterministic fake
        // "encryptor" (rather than real, randomized AES output) so it pins
        // the escaping behavior itself, not a ~1/256-per-byte chance of the
        // real cipher happening to emit a CR/LF in a given run.
        $rawCipherBytes = "\\(\r\n)";

        $info = new InfoObject(3, (new Metadata())->setTitle('T'));
        $info->encryptWith(function (string $data) use ($rawCipherBytes): string {
            // Raw bytes a real cipher could plausibly emit: backslash,
            // parens, CR, and LF, none of them escaped by the encryptor
            // itself - escaping is InfoObject::encryptWith()'s job.
            return $rawCipherBytes;
        });

        $result = (string) $info;

        // The unescaped raw ciphertext must never appear as-is inside the
        // literal string - that's exactly the shape that would trigger a
        // reader's CR/LF normalization and corrupt the decrypted bytes.
        $this->assertStringNotContainsString('/Title(' . $rawCipherBytes . ')', $result);

        // What must appear instead is the ciphertext run through the same
        // escaping Text::escape() already applies everywhere else in this
        // codebase that emits a literal PDF string - tying InfoObject's
        // actual wiring to that shared convention, not a private,
        // independently-maintained (and, as found here, incomplete) one.
        $this->assertStringContainsString('/Title(' . Text::escape($rawCipherBytes) . ')', $result);
    }

    public function testToStringEscapesRawMetadataValuesToo()
    {
        // Regression pin for the read-path branch's final review: the PLAIN
        // (non-encrypted) substitution path dropped its metadata values into
        // (...) literal-string syntax with no escaping at all, so a single
        // unbalanced parenthesis or trailing backslash ended the string early
        // and corrupted the whole Info object. Build\Parser copies a source
        // PDF's /Info verbatim into Metadata, so the value here is exactly the
        // shape an imported document can carry.
        $metadata = (new Metadata())
            ->setTitle('Weird (unbalanced title \\ here')
            ->setAuthor('A) B (C')
            ->setSubject("line\r\nbreak")
            ->setCreator('back\\slash')
            ->setProducer('plain');

        $result = (string) (new InfoObject(3, $metadata));

        $this->assertStringContainsString('/Title(' . Text::escape('Weird (unbalanced title \\ here') . ')', $result);
        $this->assertStringContainsString('/Author(' . Text::escape('A) B (C') . ')', $result);
        $this->assertStringContainsString('/Subject(' . Text::escape("line\r\nbreak") . ')', $result);
        $this->assertStringContainsString('/Creator(' . Text::escape('back\\slash') . ')', $result);

        // Every parenthesis left in the output is a delimiter or an escaped
        // one - never a bare, unescaped paren from a value.
        $this->assertStringNotContainsString('/Title(Weird (unbalanced', $result);

        // Ordinary values are untouched, so this changes nothing for the
        // overwhelmingly common case.
        $this->assertStringContainsString('/Producer(plain)', $result);
    }

}
