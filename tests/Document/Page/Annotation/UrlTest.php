<?php

namespace Pop\Pdf\Test\Document\Page\Annotation;

use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Page\Annotation\Url;
use Pop\Pdf\Build\Compiler;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{

    public function testEncryptWithReplacesTheUriWithEncryptedEscapedBytes()
    {
        $url = new Url(100, 20, 'https://example.com/secret');
        $url->encryptWith(fn (string $data) => "\x01\x02(fake-cipher)\\end");

        $stream = $url->getStream(5, 10, 20);

        $this->assertStringNotContainsString('https://example.com/secret', $stream);
        // Text::escape() must have run on the encryptor's output - raw
        // unescaped "(" / ")" / "\" from the fake ciphertext would otherwise
        // corrupt the dictionary syntax.
        $this->assertStringContainsString('\\(fake-cipher\\)\\\\end', $stream);
    }

    public function testGetStreamUsesThePlainUrlWhenNotEncrypted()
    {
        $url = new Url(100, 20, 'https://example.com/plain');
        $stream = $url->getStream(5, 10, 20);
        $this->assertStringContainsString('https://example.com/plain', $stream);
    }

    // Blocking qpdf + poppler verification - not just qpdf. Confirms the
    // encrypted annotation URI round-trips through a real password-protected
    // PDF and decrypts back to the exact original plaintext, and that
    // poppler correctly identifies the encryption algorithm (never RC4).
    public function testEncryptedAnnotationUrlSurvivesQpdfAndPopplerRoundTrip()
    {
        if ((shell_exec('which qpdf') === null) || (shell_exec('which pdftotext') === null)
            || (shell_exec('which pdfinfo') === null)) {
            $this->markTestSkipped('qpdf and poppler-utils are required for this test.');
        }

        $document = new Document();
        $document->addFont(Font::ARIAL);
        $page = new Page(Page::LETTER);
        $page->addUrl(new Page\Annotation\Url(150, 20, 'https://example.com/secret-path'), 50, 400);
        $document->addPage($page);
        $document->setSecurity(new Document\Security('open-me', 'admin123'));

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_url_enc_test_') . '.pdf';
        $compiler = new Compiler();
        $compiler->finalize($document);
        file_put_contents($tmpFile, $compiler->getOutput());

        $info = shell_exec('pdfinfo -upw open-me ' . escapeshellarg($tmpFile) . ' 2>&1');
        $checkOutput = [];
        exec('qpdf --check --password=open-me ' . escapeshellarg($tmpFile) . ' 2>&1', $checkOutput, $checkStatus);

        $decrypted = tempnam(sys_get_temp_dir(), 'pop_pdf_url_dec_test_') . '.pdf';
        $decOutput = [];
        exec('qpdf --password=open-me --decrypt ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($decrypted) . ' 2>&1', $decOutput, $decStatus);
        $decryptedBytes = file_get_contents($decrypted);

        // Also run pdftotext/pdftoppm against the still-encrypted file to
        // confirm poppler itself (not just qpdf) can walk the whole file
        // (including the annotation object) without a syntax error.
        $textOutput = [];
        exec('pdftotext -upw open-me ' . escapeshellarg($tmpFile) . ' - 2>&1', $textOutput, $textStatus);

        $ppmPrefix = tempnam(sys_get_temp_dir(), 'pop_pdf_url_ppm_test_');
        $ppmOutput = [];
        exec('pdftoppm -upw open-me -png ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($ppmPrefix) . ' 2>&1', $ppmOutput, $ppmStatus);

        unlink($tmpFile);
        unlink($decrypted);
        foreach (glob($ppmPrefix . '*.png') as $ppmFile) {
            unlink($ppmFile);
        }

        $this->assertStringContainsString('algorithm:AES-256', $info);
        $this->assertStringNotContainsString('algorithm:RC4', $info);
        $this->assertEquals(0, $checkStatus, implode("\n", $checkOutput));
        $this->assertEquals(0, $decStatus, implode("\n", $decOutput));
        $this->assertEquals(0, $textStatus, implode("\n", $textOutput));
        $this->assertEquals(0, $ppmStatus, implode("\n", $ppmOutput));
        foreach (array_merge($textOutput, $ppmOutput) as $line) {
            $this->assertStringNotContainsString('Syntax Error', $line);
        }
        $this->assertStringContainsString('https://example.com/secret-path', $decryptedBytes);
    }

}
