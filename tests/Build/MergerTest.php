<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf\Build\Merger;
use Pop\Pdf\Build\Exception;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Form;
use PHPUnit\Framework\TestCase;

class MergerTest extends TestCase
{

    /**
     * Temp files created by a test, removed in tearDown()
     * @var array
     */
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];
    }

    /**
     * Reserve a temp .pdf path, tracking both it and the extension-less file
     * tempnam() actually creates, so neither is left behind.
     */
    protected function tempPdfPath(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'merger_enc_test_');
        $path = $base . '.pdf';

        $this->tempFiles[] = $base;
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Run qpdf, returning the output path. Skips the test if qpdf is not
     * available.
     *
     * qpdf exits 3 ("succeeded with warnings") for any source it had to
     * repair, so a non-zero status alone is NOT a reliable "qpdf is missing"
     * signal - keying the skip off it would silently turn every encryption
     * test into a false pass. Existence of a non-empty output file is.
     */
    protected function runQpdf(array $args): string
    {
        $out  = $this->tempPdfPath();
        $args = array_merge($args, [$out]);
        $cmd  = 'qpdf ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';

        exec($cmd, $output, $status);

        if (!file_exists($out) || (filesize($out) === 0)) {
            $this->markTestSkipped(
                'qpdf is not available to produce an encrypted fixture (status ' . $status . '): ' .
                implode("\n", $output)
            );
        }

        return $out;
    }

    /**
     * Produce a qpdf-encrypted copy of one of this repo's plain fixtures.
     *
     * 128-bit encryption needs --use-aes=y: qpdf 11+ refuses to write RC4
     * (its default for 128 bits) at all without --allow-weak-crypto, and
     * this library deliberately does not support RC4. 256-bit encryption
     * forces AES automatically.
     */
    protected function qpdfEncrypt(string $fixture, string $bits, string $user = 'open-me'): string
    {
        $args = ['--encrypt', $user, 'admin123', $bits];

        if ($bits === '128') {
            $args[] = '--use-aes=y';
        }

        $args[] = '--';
        $args[] = __DIR__ . '/../tmp/' . $fixture;

        return $this->runQpdf($args);
    }

    public function testMergeFilesCombinesPageCounts()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);

        $docPages  = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/doc.pdf')->getNumberOfPages();
        $testPages = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/test.pdf')->getNumberOfPages();

        $this->assertEquals($docPages + $testPages, $doc->getNumberOfPages());
    }

    public function testMergeDataCombinesPageCounts()
    {
        $merger = new Merger();
        $doc    = $merger->mergeData([
            file_get_contents(__DIR__ . '/../tmp/doc.pdf'),
            file_get_contents(__DIR__ . '/../tmp/test.pdf'),
        ]);

        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testMergedOutputHasNoDuplicateObjectNumbers()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);

        $output = (string) $doc;

        preg_match_all('/(?:^|\n)(\d+) 0 obj/', $output, $matches);
        $numbers = $matches[1];

        $this->assertEquals(count($numbers), count(array_unique($numbers)));
    }

    public function testMergedOutputRoundTripsThroughReExtraction()
    {
        // A well-formed merge must itself be a parseable PDF - round-trip it
        // through the same robust reader used to build it.
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);
        $bytes  = (string) $doc;

        $reparsed = new \Pop\Pdf\Extract\Document($bytes);
        $root     = $reparsed->getRoot();

        $this->assertNotEmpty($root);
    }

    public function testMergeFilesIntoProvidedDocumentReturnsThatSameInstance()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf'], $starter);

        $this->assertSame($starter, $doc);
        $this->assertTrue($doc->hasFont('Arial'));
    }

    public function testMergeDataIntoProvidedDocumentReturnsThatSameInstance()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $merger = new Merger();
        $doc    = $merger->mergeData([
            file_get_contents(__DIR__ . '/../tmp/doc.pdf'),
            file_get_contents(__DIR__ . '/../tmp/test.pdf'),
        ], $starter);

        $this->assertSame($starter, $doc);
        $this->assertTrue($doc->hasFont('Arial'));
    }

    public function testMergeFilesIntoProvidedDocumentAddsToItsExistingPages()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));
        $starter->addPage(new Page(Page::LETTER));

        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf'], $starter);

        $docPages  = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/doc.pdf')->getNumberOfPages();
        $testPages = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/test.pdf')->getNumberOfPages();

        // The starter document's own pre-existing page plus both merged sources.
        $this->assertEquals(1 + $docPages + $testPages, $doc->getNumberOfPages());
    }

    public function testMergeFilesIntoProvidedDocumentPlacesItsExistingPagesBeforeMergedContent()
    {
        $starter = new Document();
        $starter->addFont(new Font('Arial'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('STARTER PAGE MARKER', 12), 'Arial', 50, 700);
        $starter->addPage($page);

        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf'], $starter);

        $tmpFile = __DIR__ . '/../tmp/merger-order-test.pdf';
        \Pop\Pdf\Pdf::writeToFile($doc, $tmpFile);

        try {
            $text = \Pop\Pdf\Pdf::extractTextFromFile($tmpFile);

            $starterPosition = strpos($text, 'STARTER PAGE MARKER');
            $mergedPosition  = strpos($text, 'One More Time!');

            $this->assertNotFalse($starterPosition);
            $this->assertNotFalse($mergedPosition);
            $this->assertLessThan($mergedPosition, $starterPosition);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testMergeRequiresAtLeastTwoSources()
    {
        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf']);
    }

    public function testMergeMissingFileThrows()
    {
        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/does-not-exist.pdf']);
    }

    public function testMergedPagesCountReflectsTotalLeafPagesNotSourceCount()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/image-only-2page.pdf']);
        $totalPages = $doc->getNumberOfPages();

        $output   = (string) $doc;
        $reparsed = new \Pop\Pdf\Extract\Document($output);
        $root     = $reparsed->getRoot();
        $pages    = $reparsed->resolve($root['Pages'] ?? null);

        $this->assertEquals($totalPages, $pages['Count']);
        // The master Pages node's own Kids count is the SOURCE count (2),
        // deliberately different from the total leaf page count - this is
        // exactly the distinction the bug conflated.
        $this->assertCount(2, $pages['Kids']);
        $this->assertNotEquals($pages['Count'], count($pages['Kids']));
    }

    public function testMergeDoesNotLeaveOrphanedFormObjectsInOutput()
    {
        // Merging doesn't reconstruct a combined /AcroForm across sources
        // (documented scope limitation - widgets are dropped from the
        // page's /Annots in ObjectGraphReader::translatePage()), but the
        // source's own AcroForm dictionary and Widget annotation objects
        // must be omitted entirely rather than carried over as dead,
        // unreferenced objects in the merged file.
        $formDoc = new Document();
        $formDoc->addForm(new Form('contact_form'));
        $formPage = new Page(Page::LETTER);
        $formPage->addField(new Page\Field\Text('name', 'Arial', 10), 'contact_form', 50, 200);
        $formDoc->addFont(new Font('Arial'));
        $formDoc->addPage($formPage);

        $formFile = __DIR__ . '/../tmp/merger-form-source.pdf';
        \Pop\Pdf\Pdf::writeToFile($formDoc, $formFile);

        try {
            $merger = new Merger();
            $doc    = $merger->mergeFiles([$formFile, __DIR__ . '/../tmp/test.pdf']);
            $output = (string) $doc;

            $this->assertStringNotContainsString('/AcroForm', $output);
            $this->assertStringNotContainsString('/Widget', $output);
            $this->assertDoesNotMatchRegularExpression('/<<\s*\/Fields\s*\[/', $output);
        } finally {
            unlink($formFile);
        }
    }

    public function testMergeWrapsExtractExceptionAsBuildException()
    {
        // mergeSources() reads each source via ObjectGraphReader, which in
        // turn calls Extract\Document::resolve() - a genuine circular
        // reference there raises an Extract\Exception that must surface as
        // this namespace's own Build\Exception, not leak the Extract type.
        $good = file_get_contents(__DIR__ . '/../tmp/doc.pdf');

        $header   = "%PDF-1.4\n";
        $obj1     = "1 0 obj\n1 0 R\nendobj\n";
        $body     = $obj1;
        $xrefPos  = strlen($header . $body);
        $circular = $header . $body .
            "xref\n0 2\n0000000000 65535 f \n" . sprintf("%010d 00000 n \n", strlen($header)) .
            "trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeData([$good, $circular]);
    }

    public function testMergeFilesRejectsEncryptedSource()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2 . $obj3;
        $offsets = [strlen($header), strlen($header . $obj1), strlen($header . $obj1 . $obj2)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[0]) .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 4 /Root 1 0 R /Encrypt << /Filter /Standard >> >>\nstartxref\n{$xrefPos}\n%%EOF";

        $encryptedFile = __DIR__ . '/../tmp/encrypted-test.pdf';
        file_put_contents($encryptedFile, $header . $body . $xref);

        $this->expectException(Exception::class);

        try {
            $merger = new Merger();
            $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', $encryptedFile]);
        } finally {
            unlink($encryptedFile);
        }
    }

    public function testMergeFilesOpensAMixOfEncryptedAndPlainSourcesWithPerSourcePassword()
    {
        // test-extract-1.5.pdf (plain, 3 leaf pages) and test-extract.pdf
        // (1 leaf page, encrypted here) are merged together. A real page
        // count (not just extracted text) proves the encrypted source's
        // object graph was actually walked and spliced in, not silently
        // dropped or substituted for the plain one.
        $plainPages     = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/test-extract-1.5.pdf')->getNumberOfPages();
        $encryptedFixture = $this->qpdfEncrypt('test-extract.pdf', '256');

        $merger = new Merger();
        $doc    = $merger->mergeFiles(
            [__DIR__ . '/../tmp/test-extract-1.5.pdf', $encryptedFixture],
            new Document(),
            [1 => 'open-me']
        );

        $this->assertInstanceOf(Document::class, $doc);
        $this->assertEquals($plainPages + 1, $doc->getNumberOfPages());

        // Resolved content, not just extracted text: round-trip the merged
        // document through Pdf::writeToFile()/extractTextFromFile() and
        // confirm markers unique to EACH source are present, proving the
        // encrypted source's page content stream was genuinely decrypted
        // and expanded rather than merely counted.
        $tmpFile = $this->tempPdfPath();
        \Pop\Pdf\Pdf::writeToFile($doc, $tmpFile);
        $text = \Pop\Pdf\Pdf::extractTextFromFile($tmpFile);

        $this->assertStringContainsString('Hello World Again!', $text);
        $this->assertStringContainsString('Thanks for stopping by!', $text);
    }

    public function testMergeFilesOpensAMixOfEncryptedAndPlainSourcesViaMergeData()
    {
        $plainPages       = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/test-extract-1.5.pdf')->getNumberOfPages();
        $encryptedFixture = $this->qpdfEncrypt('test-extract.pdf', '256');

        $merger = new Merger();
        $doc    = $merger->mergeData(
            [
                file_get_contents(__DIR__ . '/../tmp/test-extract-1.5.pdf'),
                file_get_contents($encryptedFixture),
            ],
            new Document(),
            [1 => 'open-me']
        );

        $this->assertEquals($plainPages + 1, $doc->getNumberOfPages());
    }

    public function testMergeFilesWithoutPasswordStillFailsForEncryptedSource()
    {
        // Proves the password parameter is actually load-bearing: omitting
        // it (backward-compatible call shape) must still fail to open the
        // encrypted source, not silently succeed without decrypting it.
        $encryptedFixture = $this->qpdfEncrypt('test-extract.pdf', '256');

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles([__DIR__ . '/../tmp/test-extract-1.5.pdf', $encryptedFixture]);
    }

    public function testMergeFilesWithWrongPasswordFailsForEncryptedSource()
    {
        $encryptedFixture = $this->qpdfEncrypt('test-extract.pdf', '256');

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles(
            [__DIR__ . '/../tmp/test-extract-1.5.pdf', $encryptedFixture],
            new Document(),
            [1 => 'definitely-wrong-password']
        );
    }

    public function testMergeFilesKeepsPasswordsIndexedToTheirOwnSourceNotShifted()
    {
        // Both sources encrypted, each with a DIFFERENT password, keyed by
        // their own index - proves passwords are not shifted or applied to
        // the wrong source.
        $encryptedA = $this->qpdfEncrypt('test-extract.pdf', '256', 'password-a');
        $encryptedB = $this->qpdfEncrypt('test-extract-1.5.pdf', '256', 'password-b');

        $merger = new Merger();
        $doc    = $merger->mergeFiles(
            [$encryptedA, $encryptedB],
            new Document(),
            [0 => 'password-a', 1 => 'password-b']
        );

        $this->assertEquals(1 + 3, $doc->getNumberOfPages());
    }

    public function testMergeFilesRejectsSwappedPasswords()
    {
        // The inverse of the previous test: applying each source's password
        // to the OTHER source must fail, confirming the indices are not
        // interchangeable (i.e. not just "any password unlocks any source").
        $encryptedA = $this->qpdfEncrypt('test-extract.pdf', '256', 'password-a');
        $encryptedB = $this->qpdfEncrypt('test-extract-1.5.pdf', '256', 'password-b');

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles(
            [$encryptedA, $encryptedB],
            new Document(),
            [0 => 'password-b', 1 => 'password-a']
        );
    }

    /**
     * A source encrypted with /EncryptMetadata false (qpdf's
     * --cleartext-metadata) that also carries a real /Metadata stream is a
     * documented, deliberately-unfixed limitation (StandardSecurityHandler
     * does not implement Algorithm 2 step (f) / skip cleartext streams) -
     * AES-256 opens the file (key derivation is unaffected) and then tries
     * to AES-decrypt a /Metadata stream that was never actually encrypted,
     * which fails. This test's job is only to confirm that failure surfaces
     * as a clean, catchable Build\Exception out of mergeFiles() rather than
     * an uncaught fatal error - NOT to make the source mergeable.
     */
    public function testMergeSurfacesEncryptMetadataFalseAsCatchableBuildException()
    {
        $xmp    = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta><?xpacket end="w"?>';
        $xmpLen = strlen($xmp);

        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R /Metadata 4 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << >> >>',
            4 => "<< /Type /Metadata /Subtype /XML /Length {$xmpLen} >>\nstream\n{$xmp}\nendstream",
        ];

        $header = "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n";
        $body   = '';
        $offsets = [];
        foreach ($objs as $num => $def) {
            $offsets[$num] = strlen($header . $body);
            $body .= "{$num} 0 obj\n{$def}\nendobj\n";
        }

        $xrefPos = strlen($header . $body);
        $count   = count($objs) + 1;
        $xref    = "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $trailer = "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $sourceFile = $this->tempPdfPath();
        file_put_contents($sourceFile, $header . $body . $xref . $trailer);

        $encryptedFixture = $this->runQpdf([
            '--encrypt', 'open-me', 'admin123', '256', '--cleartext-metadata', '--', $sourceFile,
        ]);

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles(
            [__DIR__ . '/../tmp/doc.pdf', $encryptedFixture],
            new Document(),
            [1 => 'open-me']
        );
    }

}
