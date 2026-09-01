<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf;
use Pop\Pdf\Build\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
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
     * Reserve a temp .pdf path, tracking BOTH it and the extension-less file
     * tempnam() actually creates, so neither is left behind.
     */
    protected function tempPdfPath(): string
    {
        $base = tempnam(sys_get_temp_dir(), 'parser_enc_test_');
        $path = $base . '.pdf';

        $this->tempFiles[] = $base;
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Produce a qpdf-encrypted copy of a fixture, skipping the calling test if
     * qpdf isn't available.
     *
     * qpdf exits 3 ("succeeded with warnings") for any source it had to
     * repair, so a non-zero status alone is NOT a reliable "qpdf is missing"
     * signal - existence of a non-empty output file is.
     */
    protected function qpdfEncrypt(string $source, string $bits = '256'): string
    {
        $encrypted = $this->tempPdfPath();
        $args      = ['--encrypt', 'open-me', 'admin123', $bits];

        // qpdf's default for 128 bits is RC4 (revision 3), which qpdf 11+
        // refuses to write and this library deliberately doesn't support;
        // --use-aes=y yields AESV2/revision 4. 256 bits is AES already and
        // rejects the flag outright.
        if ($bits === '128') {
            $args[] = '--use-aes=y';
        }

        $args = array_merge($args, ['--', $source, $encrypted]);
        $cmd  = 'qpdf ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';

        exec($cmd, $out, $status);

        if (!file_exists($encrypted) || (filesize($encrypted) === 0)) {
            $this->markTestSkipped('qpdf is not available (status ' . $status . '): ' . implode("\n", $out));
        }

        return $encrypted;
    }

    public function testGetObjectStreamsAndMap()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertTrue(is_array($parser->getObjectStreams()));
        $this->assertTrue(is_array($parser->getObjectMap()));
    }

    public function testInitFileDoesNotExistException()
    {
        $this->expectException('Pop\Pdf\Build\Exception');
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/bad.pdf');
    }

    public function testGetFile()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals(__DIR__ . '/../tmp/doc.pdf', $parser->getFile());
    }

    public function testGetData()
    {
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertStringContainsString('%PDF', $parser->getData());
    }

    public function testGetObjectStreamsAndMapFromData()
    {
        $parser = new Parser();
        $parser->parseData(file_get_contents(__DIR__ . '/../tmp/doc.pdf'));
        $this->assertTrue(is_array($parser->getObjectStreams()));
        $this->assertTrue(is_array($parser->getObjectMap()));
    }


    public function testGetDataFromData()
    {
        $parser = new Parser();
        $parser->parseData(file_get_contents(__DIR__ . '/../tmp/doc.pdf'));
        $this->assertStringContainsString('%PDF', $parser->getData());
    }

    public function testParseProducesCorrectPageCount()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals(3, $doc->getNumberOfPages());
    }

    public function testParsePreservesMetadata()
    {
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals('Test Title', $doc->getMetadata()->getTitle());
    }

    public function testCompiledOutputHasCorrectRootPagesLinkage()
    {
        $doc           = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/image-only-2page.pdf');
        $expectedPages = $doc->getNumberOfPages();

        $output = (string) $doc;

        $reparsed = new Pdf\Extract\Document($output);
        $root     = $reparsed->getRoot();
        $pages    = $reparsed->resolve($root['Pages'] ?? null);

        $this->assertIsArray($pages);
        $this->assertEquals('Pages', $pages['Type']->name);
        $this->assertEquals($expectedPages, $pages['Count']);
    }

    public function testImportPreservesFullPageCountForMultiLevelPageTree()
    {
        // Regression test for ObjectGraphReader::walkPagesTree() recursing
        // through a nested /Pages tree (root /Pages -> two intermediate
        // /Pages nodes -> two leaf /Page nodes each) rather than a single
        // flat /Kids list. MediaBox is set only on the root /Pages node to
        // also exercise inherited-attribute propagation down through the
        // intermediate nodes to each leaf.
        $catalog  = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $rootKids = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 4 /MediaBox [0 0 612 792] >>\nendobj\n";
        $branchA  = "3 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [5 0 R 6 0 R] /Count 2 >>\nendobj\n";
        $branchB  = "4 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [7 0 R 8 0 R] /Count 2 >>\nendobj\n";
        $pageA1   = "5 0 obj\n<< /Type /Page /Parent 3 0 R >>\nendobj\n";
        $pageA2   = "6 0 obj\n<< /Type /Page /Parent 3 0 R >>\nendobj\n";
        $pageB1   = "7 0 obj\n<< /Type /Page /Parent 4 0 R >>\nendobj\n";
        $pageB2   = "8 0 obj\n<< /Type /Page /Parent 4 0 R >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $objects = [$catalog, $rootKids, $branchA, $branchB, $pageA1, $pageA2, $pageB1, $pageB2];

        $offsets = [];
        $running = $header;
        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($running);
            $running        .= $object;
        }

        $xrefPos = strlen($running);
        $xref    = "xref\n0 9\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }
        $xref .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $data = $running . $xref;

        $doc        = Pdf\Pdf::importRawData($data);
        $totalPages = $doc->getNumberOfPages();

        $output   = (string) $doc;
        $reparsed = new Pdf\Extract\Document($output);
        $root     = $reparsed->getRoot();
        $pages    = $reparsed->resolve($root['Pages'] ?? null);

        $this->assertEquals(4, $totalPages);
        $this->assertEquals($totalPages, $pages['Count']);
    }

    public function testParseWithObjectStreamAndXrefStreamSource()
    {
        // Build\Parser's old regex-based scanning had no xref/object-stream
        // support at all - this fixture (test-extract-1.5.pdf) uses a real
        // /Type /XRef cross-reference stream, something the previous
        // implementation could not read correctly.
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/test-extract-1.5.pdf');
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testEditingAnImportedPageStillWorks()
    {
        $doc  = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/doc.pdf');
        $page = $doc->getPage(1);
        $page->addText('Appended', 'Arial', 10, 10);
        $doc->addFont(new Pdf\Document\Font(Pdf\Document\Font::ARIAL));

        $output = (string) $doc;

        $this->assertStringContainsString('%PDF', $output);
    }

    public function testGetFontsReturnsEmptyArray()
    {
        // Parser::$fonts is retained only for public API compatibility - the
        // Extract\Document-based implementation never populates it (font
        // resources are carried per-page instead), so getFonts() always
        // returns an empty array after a real parse.
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/../tmp/doc.pdf');
        $this->assertEquals([], $parser->getFonts());
    }

    public function testParseWrapsExtractExceptionAsBuildException()
    {
        // An Extract\Exception raised while constructing the underlying
        // Extract\Document (here: an /Encrypt entry, which extraction
        // refuses to handle) must surface as this namespace's own
        // Build\Exception, not leak the Extract\Exception type.
        $this->expectException('Pop\Pdf\Build\Exception');

        $data = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "trailer\n<< /Root 1 0 R /Encrypt 3 0 R >>\n%%EOF";

        $parser = new Parser();
        $parser->parseData($data);
    }

    public function testMissingInfoDoesNotClobberRealObjectAtIndexThree()
    {
        // Regression test: when a source PDF's trailer has no /Info entry,
        // Parser::parse() must still construct an InfoObject (placed at a
        // collision-free index) so that Compiler::setDocument() finds a real
        // imported InfoObject via its foreach loop, instead of falling into
        // its "synthesize a default InfoObject at hardcoded index 3" branch
        // and silently overwriting whatever real imported object landed
        // there - here, object 3 is a real content stream.
        $streamContent = '1 0 0 1 0 0 cm MARKERQ';

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Length " . strlen($streamContent) . " >>\nstream\n"
            . $streamContent . "\nendstream\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 3 0 R >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);
        $offset3 = strlen($header . $obj1 . $obj2);
        $offset4 = strlen($header . $obj1 . $obj2 . $obj3);

        $xrefPos = strlen($header . $body);

        // Deliberately no /Info key in the trailer.
        $xref = "xref\n0 5\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            sprintf("%010d 00000 n \n", $offset3) .
            sprintf("%010d 00000 n \n", $offset4) .
            "trailer\n<< /Size 5 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $data = $header . $body . $xref;

        $doc    = Pdf\Pdf::importRawData($data);
        $output = (string) $doc;

        $this->assertStringContainsString('MARKERQ', $output);
    }

    public function testParseFileOpensAnEncryptedPdfGivenTheCorrectPassword()
    {
        $encrypted = $this->qpdfEncrypt(__DIR__ . '/../tmp/test-extract.pdf');

        // Wrong/missing password must not succeed - proves the fixture
        // genuinely requires decryption to be read at all.
        $parserNoPassword = new Parser();
        try {
            $parserNoPassword->parseFile($encrypted);
            $this->fail('Expected an exception when parsing an encrypted PDF without a password.');
        } catch (\Pop\Pdf\Build\Exception $e) {
            // expected
        }

        $parser = new Parser();
        $doc    = $parser->parseFile($encrypted, null, 'open-me');

        $this->assertInstanceOf('Pop\Pdf\Document\AbstractDocument', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testParseDataOpensAnEncryptedPdfGivenTheCorrectPassword()
    {
        $data = file_get_contents($this->qpdfEncrypt(__DIR__ . '/../tmp/test-extract.pdf'));

        $parser = new Parser();
        $doc    = $parser->parseData($data, null, 'open-me');

        $this->assertInstanceOf('Pop\Pdf\Document\AbstractDocument', $doc);
        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testParseDoesNotCopyCiphertextInfoStringsIntoMetadata()
    {
        // A third-party encryptor's default is /StrF /StdCF, so a source PDF's
        // /Info strings are still raw AES ciphertext when this library reads
        // them - it has no string-decryption layer at all. Carrying those
        // bytes into Document\Metadata would republish binary garbage as the
        // document's title/author, so they are dropped instead.
        $plainDoc = (new Parser())->parseFile(__DIR__ . '/../tmp/test-extract.pdf');
        $encDoc   = (new Parser())->parseFile(
            $this->qpdfEncrypt(__DIR__ . '/../tmp/test-extract.pdf'), null, 'open-me'
        );

        // Control: the plain source really does carry /Info metadata, so the
        // assertion below is about the ciphertext and not about an /Info-less
        // fixture.
        $this->assertNotEquals('Pop PDF', $plainDoc->getMetadata()->getProducer());

        // The encrypted read falls back to the default Metadata instead.
        $this->assertEquals('Pop PDF', $encDoc->getMetadata()->getProducer());
        $this->assertEquals('Pop PDF', $encDoc->getMetadata()->getTitle());
    }

    public function testParseKeepsInfoStringsForADocumentWrittenWithStrFIdentity()
    {
        // This library's own encrypted output declares /StrF /Identity, so its
        // strings ARE readable and must still be copied through - the skip
        // above keys off the source's actual /StrF, not merely on "encrypted".
        $document = new \Pop\Pdf\Document();
        $document->addFont(new \Pop\Pdf\Document\Font('Arial'));
        $document->setSecurity(new \Pop\Pdf\Document\Security('open-me', 'admin123'));
        $document->getMetadata()->setTitle('Readable Title');

        $page = new \Pop\Pdf\Document\Page(\Pop\Pdf\Document\Page::LETTER);
        $page->addText(new \Pop\Pdf\Document\Page\Text('Hello', 12), 'Arial', 50, 700);
        $document->addPage($page);

        $path = $this->tempPdfPath();
        Pdf\Pdf::writeToFile($document, $path);

        $reread = (new Parser())->parseFile($path, null, 'open-me');

        $this->assertEquals('Readable Title', $reread->getMetadata()->getTitle());
    }

    public function testAnImportedEncryptedPdfWritesBackOutAsAStructurallyValidPdf()
    {
        // Regression pin for the read-path branch's final review. Importing a
        // third-party-encrypted PDF leaves its /Info strings as raw AES
        // ciphertext; substituting those bytes into (...) literal-string
        // syntax unescaped corrupted the output object structure roughly half
        // the time, with no exception and no warning. The corruption was
        // data-dependent (whether that particular ciphertext happened to
        // contain a paren or a backslash), so this re-encrypts several times -
        // each run gets a fresh random file key and therefore fresh
        // ciphertext.
        for ($i = 0; $i < 8; $i++) {
            $encrypted = $this->qpdfEncrypt(__DIR__ . '/../tmp/test-extract.pdf', (($i % 2) === 0) ? '128' : '256');
            $document  = Pdf\Pdf::importFromFile($encrypted, null, 'open-me');

            $written = $this->tempPdfPath();
            Pdf\Pdf::writeToFile($document, $written);

            $checkOutput = [];
            exec('qpdf --check ' . escapeshellarg($written) . ' 2>&1', $checkOutput);
            $check = implode("\n", $checkOutput);

            // qpdf reports structural damage as these parse errors; anything
            // else it warns about (e.g. a fixture's own pre-existing quirks)
            // is not what this test is pinning.
            $this->assertStringNotContainsString('expected endobj', $check);
            $this->assertStringNotContainsString('EOF while reading token', $check);
            $this->assertStringNotContainsString('parse error while reading object', $check);
        }
    }

}