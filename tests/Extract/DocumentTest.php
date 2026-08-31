<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\Content\PageWalker;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
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
        $base = tempnam(sys_get_temp_dir(), 'extract_doc_enc_test_');
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
     * 128-bit encryption needs --use-aes=y: qpdf's default for 128 bits is
     * RC4 (revision 3), which qpdf 11+ refuses to write at all without
     * --allow-weak-crypto and which this library deliberately does not
     * support. Only --use-aes=y yields AESV2/revision 4.
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

    /**
     * Produce a qpdf-normalized but UNENCRYPTED copy of the same fixture, so
     * the two files differ only by encryption - object numbering and stream
     * bytes are otherwise identical, which is what makes a byte-for-byte
     * comparison between them meaningful.
     */
    protected function qpdfNormalize(string $fixture): string
    {
        return $this->runQpdf([__DIR__ . '/../tmp/' . $fixture]);
    }

    /**
     * Map every non-cross-reference stream object to a hash of its bytes.
     * Cross-reference streams are excluded because the encrypted file legitimately
     * has a different one (it carries an extra /Encrypt object), not because
     * of anything decryption did.
     */
    protected function streamHashes(Document $doc): array
    {
        $hashes = [];

        foreach ($doc->getObjectNumbers() as $objNum) {
            $value = $doc->getObject($objNum);

            if (!($value instanceof Value\Stream)) {
                continue;
            }

            $type = $value->dict['Type'] ?? null;

            if (($type instanceof Value\Name) && ($type->name === 'XRef')) {
                continue;
            }

            $hashes[$objNum] = hash('sha256', $value->raw);
        }

        ksort($hashes);

        return $hashes;
    }

    protected function buildSimplePdf(): string
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);
        $offset3 = strlen($header . $obj1 . $obj2);

        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            sprintf("%010d 00000 n \n", $offset3) .
            "trailer\n<< /Size 4 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        return $header . $body . $xref;
    }

    public function testResolvesRootAndObjects()
    {
        $doc  = new Document($this->buildSimplePdf());
        $root = $doc->getRoot();

        $this->assertInstanceOf(Value\Name::class, $root['Type']);
        $this->assertEquals('Catalog', $root['Type']->name);

        $pages = $doc->resolve($root['Pages']);
        $this->assertEquals(1, $pages['Count']);

        $page = $doc->resolve($pages['Kids'][0]);
        $this->assertInstanceOf(Value\Name::class, $page['Type']);
        $this->assertEquals('Page', $page['Type']->name);
    }

    public function testMergesHybridXRefStmAlongsideClassicPrevChain()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /Extra >>\nendobj\n";

        $header = "%PDF-1.5\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);
        $offset3 = strlen($header . $obj1 . $obj2);
        $offset4 = strlen($header . $obj1 . $obj2 . $obj3);

        // Supplemental xref stream: one type-1 entry for object 4 only.
        $row        = chr(1) . chr(($offset4 >> 8) & 0xFF) . chr($offset4 & 0xFF) . chr(0);
        $compressed = gzcompress($row);
        $xrefStmPos = strlen($header . $body);
        $xrefStmObj = "5 0 obj\n<< /Type /XRef /W [1 2 1] /Index [4 1] /Size 6 /Filter /FlateDecode /Length "
            . strlen($compressed) . " >>\nstream\n" . $compressed . "\nendstream\nendobj\n";

        $bodyWithXrefStm = $body . $xrefStmObj;

        // A harmless older classic xref section for /Prev to point at.
        $prevXref    = "xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\n";
        $prevXrefPos = strlen($header . $bodyWithXrefStm);
        $fullBody    = $bodyWithXrefStm . $prevXref;

        $xrefPos = strlen($header . $fullBody);
        $xref = "xref\n0 4\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            sprintf("%010d 00000 n \n", $offset3) .
            "trailer\n<< /Size 4 /Root 1 0 R /XRefStm {$xrefStmPos} /Prev {$prevXrefPos} >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $pdf = $header . $fullBody . $xref;

        $doc  = new Document($pdf);
        $root = $doc->getRoot();
        $this->assertEquals('Catalog', $root['Type']->name);

        // Object 4 exists ONLY in the supplemental /XRefStm, not in the
        // classic table - this only resolves if both are merged.
        $extra = $doc->getObject(4);
        $this->assertIsArray($extra);
        $this->assertEquals('Extra', $extra['Type']->name);
    }

    public function testFallsBackToRepairWhenXrefIsCorrupt()
    {
        $data = $this->buildSimplePdf();
        // Corrupt the xref keyword so the xref-based path is unusable.
        $corrupted = str_replace("xref\n0 4", "XXXX\n0 4", $data);

        $doc  = new Document($corrupted);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
    }

    public function testThrowsOnEncryptedDocument()
    {
        $data = $this->buildSimplePdf();
        $encrypted = str_replace(
            '<< /Size 4 /Root 1 0 R >>',
            '<< /Size 4 /Root 1 0 R /Encrypt 5 0 R >>',
            $data
        );

        $this->expectException(Exception::class);
        new Document($encrypted);
    }

    public function testThrowsWhenFileDoesNotExist()
    {
        $this->expectException(Exception::class);
        Document::fromFile('/nonexistent/path/to.pdf');
    }

    public function testResolveThrowsOnCircularReferenceInsteadOfRecursingForever()
    {
        // Object 1 points at object 2, which points back at object 1 -
        // resolve() must detect the cycle rather than recursing until PHP
        // hits its call-stack limit.
        $obj1 = "1 0 obj\n2 0 R\nendobj\n";
        $obj2 = "2 0 obj\n1 0 R\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);

        $xrefPos = strlen($header . $body);
        $xref = "xref\n0 3\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            "trailer\n<< /Size 3 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $pdf = $header . $body . $xref;

        $doc = new Document($pdf);

        $this->expectException(Exception::class);
        $doc->getRoot();
    }

    public function testGetFromObjectStreamThrowsOnSelfReferencingStream()
    {
        // The xref claims object 1 both lives inside object stream 1 and
        // *is* itself served from object stream 1 - a self-reference that
        // must not recurse forever via getObject()/getFromObjectStream().
        $header = "%PDF-1.5\n";

        // A minimal-looking stream object for object 1; its content is
        // irrelevant since the cycle should be detected before it matters.
        $obj1 = "1 0 obj\n<< /Type /ObjStm /N 1 /First 0 /Length 0 >>\nstream\n\nendstream\nendobj\n";

        $offset1 = strlen($header);
        $xrefPos = strlen($header . $obj1);

        // Classic xref can't express "in stream" entries directly, so build
        // the offsets/trailer via the same shape Document::load() expects,
        // driving this through a corrupted-but-parseable xref stream is
        // more complex than needed here - instead exercise the protected
        // path directly through a Document subclass-free approach: use
        // reflection to seed offsets pointing object 1 at itself.
        $trailer = "trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        $xref    = "xref\n0 2\n0000000000 65535 f \n" . sprintf("%010d 00000 n \n", $offset1) . $trailer;

        $pdf = $header . $obj1 . $xref;

        $doc = new Document($pdf);

        $ref = new \ReflectionClass($doc);
        $offsetsProp = $ref->getProperty('offsets');
        $offsets = $offsetsProp->getValue($doc);
        $offsets[1] = ['inStream' => 1, 'index' => 0];
        $offsetsProp->setValue($doc, $offsets);

        $this->expectException(Exception::class);
        $doc->getObject(1);
    }

    public function testRepairRecoversObjectStreamResidentObjectsWhenXrefIsUnusable()
    {
        // Take a real PDF 1.5+ fixture whose objects are largely packed
        // into an ObjStm (compressed object stream), then corrupt the
        // "startxref" marker so the classic xref-loading path can't locate
        // the cross-reference stream at all. That forces Document::load()
        // into the Repair::scan() fallback, which only finds objects via
        // literal "N G obj" text - it cannot see objects packed inside an
        // ObjStm's stream body. Document::loadViaRepair() must additionally
        // detect any recovered /Type /ObjStm object, expand it, and seed
        // the resulting objects into the cache so they remain resolvable.
        $data = file_get_contents(__DIR__ . '/../tmp/test-extract-1.5.pdf');
        $this->assertNotFalse($data, 'Fixture test-extract-1.5.pdf must exist.');

        $corrupted = str_replace('startxref', 'XXXXXXXXX', $data);
        $this->assertNotEquals($data, $corrupted, 'Expected to find a startxref marker to corrupt.');

        $doc  = new Document($corrupted);
        $root = $doc->getRoot();
        $this->assertEquals('Catalog', $root['Type']->name);

        // Object 9 is known (via inspection of this fixture) to be a
        // /Type /Font dictionary that lives only inside object stream 8 -
        // it never appears as literal "9 0 obj" text, so it can only be
        // recovered via the ObjStm-expansion pass added to repair.
        $font = $doc->getObject(9);
        $this->assertIsArray($font);
        $this->assertArrayHasKey('Type', $font);
        $this->assertEquals('Font', $font['Type']->name);
    }

    public function testMalformedXrefStreamWFieldFallsBackToRepairInsteadOfLeakingTypeError()
    {
        // A non-integer /W entry causes a raw PHP TypeError deep inside
        // Xref\Stream::parse()'s arithmetic. Document::load() must catch
        // that (not just Extract\Exception) and fall back to repair rather
        // than letting the TypeError escape the constructor.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header = "%PDF-1.5\n";
        $body   = $obj1 . $obj2 . $obj3;
        $xrefPos = strlen($header . $body);

        $xrefDict = "<< /Type /XRef /W [1 2 /Bogus] /Size 4 /Root 1 0 R /Length 0 >>";
        $xrefObj  = "4 0 obj\n{$xrefDict}\nstream\n\nendstream\nendobj\n";

        $pdf = $header . $body . $xrefObj . "startxref\n{$xrefPos}\n%%EOF";

        $doc  = new Document($pdf);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
    }

    public function testGetOrResolveFontInfoCachesFactoryResult()
    {
        $doc = new Document("%PDF-1.4\n%%EOF");

        $calls   = 0;
        $factory = function () use (&$calls) {
            $calls++;
            return 'computed-value';
        };

        $result1 = $doc->getOrResolveFontInfo('key1', $factory);
        $result2 = $doc->getOrResolveFontInfo('key1', $factory);

        $this->assertEquals(1, $calls);
        $this->assertSame($result1, $result2);
    }

    public function testGetOrResolveFontInfoDistinctKeysComputeSeparately()
    {
        $doc = new Document("%PDF-1.4\n%%EOF");

        $calls   = 0;
        $factory = function () use (&$calls) {
            $calls++;
            return "value-{$calls}";
        };

        $result1 = $doc->getOrResolveFontInfo('key1', $factory);
        $result2 = $doc->getOrResolveFontInfo('key2', $factory);

        $this->assertEquals(2, $calls);
        $this->assertNotEquals($result1, $result2);
    }

    public function testGetOrResolveFontInfoBoundsMemoryRetention()
    {
        $doc = new Document("%PDF-1.4\n%%EOF");

        for ($i = 0; $i < 20; $i++) {
            $bigValue = str_repeat('x', 10 * 1024 * 1024); // 10MB each
            $result = $doc->getOrResolveFontInfo("key-{$i}", fn() => $bigValue);
            $this->assertSame($bigValue, $result); // factory result is always correct, cached or not
        }

        $cacheProp = new \ReflectionProperty($doc, 'fontInfoCache');
        $cached = $cacheProp->getValue($doc);

        // 20 x 10MB = 200MB requested, but the cache is bounded to 64MB -
        // only a subset should actually be retained.
        $this->assertLessThan(20, count($cached));
        $this->assertGreaterThan(0, count($cached));
    }

    public function testGetOrResolveFontInfoMruSlotCoversOversizedSingleEntry()
    {
        $doc = new Document("%PDF-1.4\n%%EOF");

        $calls   = 0;
        $factory = function () use (&$calls) {
            $calls++;
            return str_repeat('x', 70 * 1024 * 1024); // exceeds the 64MB budget on its own
        };

        for ($i = 0; $i < 5; $i++) {
            $doc->getOrResolveFontInfo('oversized', $factory);
        }

        $this->assertEquals(1, $calls);
    }

    public function testGetObjectNumbersReturnsEveryXrefObject()
    {
        $doc     = new Document($this->buildSimplePdf());
        $numbers = $doc->getObjectNumbers();
        sort($numbers);

        $this->assertEquals([1, 2, 3], $numbers);
    }

    public function testFromFileThrowsWhenFileIsUnreadable()
    {
        $path = tempnam(sys_get_temp_dir(), 'pop-pdf-unreadable-');
        file_put_contents($path, 'irrelevant');
        chmod($path, 0000);

        try {
            $this->expectException(Exception::class);
            Document::fromFile($path);
        } finally {
            chmod($path, 0644);
            unlink($path);
        }
    }

    public function testGetRootThrowsWhenRootDoesNotResolveToADictionary()
    {
        // Object 1 (the trailer's /Root target) is a plain integer, not a
        // dictionary - getRoot() must reject that rather than returning it.
        $obj1 = "1 0 obj\n42\nendobj\n";

        $header = "%PDF-1.4\n";
        $offset1 = strlen($header);
        $xrefPos = strlen($header . $obj1);

        $xref = "xref\n0 2\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            "trailer\n<< /Size 2 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $doc = new Document($header . $obj1 . $xref);

        $this->expectException(Exception::class);
        $doc->getRoot();
    }

    public function testGetObjectReturnsNullForUnknownObjectNumber()
    {
        $doc = new Document($this->buildSimplePdf());
        $this->assertNull($doc->getObject(9999));
    }

    public function testParseAtThrowsWhenObjKeywordMissing()
    {
        // Seed a bogus offset directly (via reflection, matching the
        // circular-reference test above) so getObject() parses at a
        // position that isn't the start of an "N G obj" header - landing
        // mid-body, on "endobj\n2 0 obj...", means the third token read is
        // a number ("0"), not the expected "obj" keyword.
        $data   = $this->buildSimplePdf();
        $offset = strpos($data, 'endobj');
        $this->assertNotFalse($offset);

        $doc = new Document($data);

        $ref = new \ReflectionClass($doc);
        $offsetsProp = $ref->getProperty('offsets');
        $offsets = $offsetsProp->getValue($doc);
        $offsets[99] = ['offset' => $offset];
        $offsetsProp->setValue($doc, $offsets);

        $this->expectException(Exception::class);
        $doc->getObject(99);
    }

    public function testGetFromObjectStreamThrowsWhenTargetIsNotAStream()
    {
        // Object 1 in buildSimplePdf() is a plain Catalog dictionary, not a
        // stream - claiming it as an object stream via a seeded inStream
        // location must be rejected.
        $doc = new Document($this->buildSimplePdf());

        $ref = new \ReflectionClass($doc);
        $offsetsProp = $ref->getProperty('offsets');
        $offsets = $offsetsProp->getValue($doc);
        $offsets[99] = ['inStream' => 1, 'index' => 0];
        $offsetsProp->setValue($doc, $offsets);

        $this->expectException(Exception::class);
        $doc->getObject(99);
    }

    public function testMalformedStartxrefValueFallsBackToRepair()
    {
        // startxref's value is not a number at all - loadViaXref() must
        // throw internally (caught by load()) and fall back to repair
        // rather than leaking the failure out of the constructor.
        $data = $this->buildSimplePdf();
        $corrupted = preg_replace('/startxref\n\d+\n/', "startxref\nXYZ\n", $data);
        $this->assertNotEquals($data, $corrupted);

        $doc  = new Document($corrupted);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
    }

    public function testRepairSkipsObjectsThatFailToParseWhileStillFindingTheCatalog()
    {
        // No xref/startxref/trailer at all, forcing full repair. Object 1
        // is an unterminated array (throws while parsing, both during
        // Document::findCatalogReference()'s scan and again during
        // expandObjectStreamsFromRepair()'s scan) - both scans must catch
        // that and keep going rather than aborting the whole repair, so
        // object 2's real Catalog is still found.
        $obj1 = "1 0 obj\n[1 2 3\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Catalog /Pages 3 0 R >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $pdf    = $header . $obj1 . $obj2;

        $doc  = new Document($pdf);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
    }

    public function testIsUsableRejectsXrefWithOutOfRangeOffsetAndFallsBackToRepair()
    {
        // The xref table's offset for object 3 points far past the end of
        // the data - isUsable()'s sample check must reject the whole
        // section (rather than trust a garbage offset) and fall back to
        // repair, which can still recover the real objects by scanning for
        // literal "N G obj" text.
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3;

        $offset1 = strlen($header);
        $offset2 = strlen($header . $obj1);

        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n" .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset1) .
            sprintf("%010d 00000 n \n", $offset2) .
            sprintf("%010d 00000 n \n", 9999999999) . // object 3: garbage offset
            "trailer\n<< /Size 4 /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        $doc  = new Document($header . $body . $xref);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
        $page = $doc->resolve($doc->resolve($root['Pages'])['Kids'][0]);
        $this->assertEquals('Page', $page['Type']->name);
    }

    public function testRepairSkipsObjectStreamThatFailsToExpand()
    {
        // No xref/startxref/trailer at all, forcing full repair. Object 9
        // parses fine as a stream and looks like a valid /Type /ObjStm
        // container, but its /Filter is unsupported - ObjectStream::expand()
        // throws while decoding it. expandObjectStreamsFromRepair() must
        // catch that and continue rather than losing the whole repair pass
        // (object 1's real Catalog must still resolve).
        $obj1   = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objBad = "9 0 obj\n<< /Type /ObjStm /N 1 /First 0 /Filter /BogusDecode /Length 5 >>\nstream\nHELLO\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $pdf    = $header . $obj1 . $objBad;

        $doc  = new Document($pdf);
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);
    }

    public function testExpandObjectStreamsFromRepairSkipsLocationsWithoutAnOffset()
    {
        // Repair::scan() itself never produces an "inStream" location (it
        // only finds objects via literal "N G obj" text), but
        // expandObjectStreamsFromRepair() is written to tolerate that shape
        // anyway - exercise it directly to confirm a location with no
        // 'offset' key is skipped rather than causing an undefined-key
        // error.
        $doc = new Document("%PDF-1.4\n%%EOF");

        $method = new \ReflectionMethod(Document::class, 'expandObjectStreamsFromRepair');

        $result = $method->invoke($doc, [1 => ['inStream' => 5, 'index' => 0]]);

        $this->assertSame([], $result);
    }

    public function testFindCatalogReferenceSkipsLocationsWithoutAnOffset()
    {
        $doc = new Document("%PDF-1.4\n%%EOF");

        $method = new \ReflectionMethod(Document::class, 'findCatalogReference');

        $result = $method->invoke($doc, [1 => ['inStream' => 5, 'index' => 0]]);

        $this->assertNull($result);
    }

    public static function encryptionFixtureProvider(): array
    {
        return [
            'AES-256, classic xref'   => ['test-extract.pdf', '256'],
            'AES-128, classic xref'   => ['test-extract.pdf', '128'],
            'AES-256, object streams' => ['test-extract-1.5.pdf', '256'],
            'AES-128, object streams' => ['test-extract-1.5.pdf', '128'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('encryptionFixtureProvider')]
    public function testConstructorOpensAnEncryptedPdfGivenTheCorrectUserPassword(string $fixture, string $bits)
    {
        $encrypted = $this->qpdfEncrypt($fixture, $bits);

        $doc = new Document(file_get_contents($encrypted), 'open-me');

        $this->assertTrue($doc->isEncrypted());
        $this->assertNotEmpty($doc->getTrailer());
        $this->assertEquals('Catalog', $doc->getRoot()['Type']->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('encryptionFixtureProvider')]
    public function testConstructorOpensAnEncryptedPdfGivenTheOwnerPassword(string $fixture, string $bits)
    {
        $encrypted = $this->qpdfEncrypt($fixture, $bits);

        $doc = new Document(file_get_contents($encrypted), 'admin123');

        $this->assertEquals('Catalog', $doc->getRoot()['Type']->name);
    }

    public function testConstructorThrowsForAnEncryptedPdfWithNoPassword()
    {
        $encrypted = $this->qpdfEncrypt('test-extract.pdf', '256');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('a password is required');

        new Document(file_get_contents($encrypted));
    }

    public function testConstructorThrowsForAnEncryptedPdfWithTheWrongPassword()
    {
        $encrypted = $this->qpdfEncrypt('test-extract.pdf', '256');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('password provided is incorrect');

        new Document(file_get_contents($encrypted), 'wrong-password');
    }

    public function testFromFileOpensAnEncryptedPdfGivenAPassword()
    {
        $encrypted = $this->qpdfEncrypt('test-extract.pdf', '256');

        $doc = Document::fromFile($encrypted, 'open-me');

        $this->assertTrue($doc->isEncrypted());
        $this->assertEquals('Catalog', $doc->getRoot()['Type']->name);
    }

    public function testAnEmptyUserPasswordOpensADocumentEncryptedWithOne()
    {
        // An empty string is a real, openable password; null means "none was
        // supplied at all" and must still be rejected. The two must not
        // collapse into each other.
        $encrypted = $this->qpdfEncrypt('test-extract.pdf', '256', '');

        $this->assertTrue((new Document(file_get_contents($encrypted), ''))->isEncrypted());

        $this->expectException(Exception::class);
        new Document(file_get_contents($encrypted));
    }

    /**
     * The blocking end-to-end proof that decryption is wired up correctly:
     * every stream of a qpdf-ENCRYPTED file (a file this library never wrote)
     * must come back byte-for-byte identical to the same stream in an
     * otherwise-identical unencrypted file, not merely "non-empty" or "didn't
     * throw".
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('encryptionFixtureProvider')]
    public function testEncryptedPdfStreamsAreByteIdenticalToTheUnencryptedSource(string $fixture, string $bits)
    {
        $encrypted = $this->qpdfEncrypt($fixture, $bits);
        $plain     = $this->qpdfNormalize($fixture);

        $encDoc   = new Document(file_get_contents($encrypted), 'open-me');
        $plainDoc = new Document(file_get_contents($plain));

        $encHashes   = $this->streamHashes($encDoc);
        $plainHashes = $this->streamHashes($plainDoc);

        $this->assertNotEmpty($encHashes);
        $this->assertSame(array_keys($plainHashes), array_keys($encHashes));

        // /Metadata is compared separately: qpdf Flate-compresses it when it
        // encrypts but leaves it raw when it merely normalizes, so the two
        // files' bytes legitimately differ there even though the decrypted
        // content is identical (asserted below, after inflating).
        foreach ($encHashes as $objNum => $hash) {
            $type = $encDoc->getObject($objNum)->dict['Type'] ?? null;

            if (($type instanceof Value\Name) && ($type->name === 'Metadata')) {
                $encMeta   = $encDoc->getObject($objNum)->raw;
                $plainMeta = $plainDoc->getObject($objNum)->raw;
                $inflated  = @gzuncompress($encMeta);

                $this->assertSame($plainMeta, ($inflated === false) ? $encMeta : $inflated);
                continue;
            }

            $this->assertSame($plainHashes[$objNum], $hash, "Stream object {$objNum} did not decrypt correctly");
        }
    }

    /**
     * Page CONTENT specifically - the decoded content stream every downstream
     * consumer (Interpreter, text extraction, merging) actually reads - must
     * match the unencrypted source exactly. For the 1.5 fixture this also
     * proves object-stream handling: its page tree lives inside an /ObjStm,
     * which is decrypted ONCE as a whole; double-decrypting the objects
     * packed inside it would corrupt the page tree outright.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('encryptionFixtureProvider')]
    public function testEncryptedPdfPageContentMatchesTheUnencryptedSource(string $fixture, string $bits)
    {
        $encrypted = $this->qpdfEncrypt($fixture, $bits);
        $plain     = $this->qpdfNormalize($fixture);

        $encPages   = PageWalker::walk(new Document(file_get_contents($encrypted), 'open-me'));
        $plainPages = PageWalker::walk(new Document(file_get_contents($plain)));

        $this->assertNotEmpty($plainPages);
        $this->assertCount(count($plainPages), $encPages);

        foreach ($plainPages as $i => $plainPage) {
            $this->assertNotSame('', $plainPage->content);
            $this->assertSame($plainPage->content, $encPages[$i]->content);
        }
    }

    public function testRejectsAnRc4EncryptedPdfRatherThanDecryptingItToGarbage()
    {
        // qpdf 11+ refuses to WRITE RC4 without --allow-weak-crypto. RC4 is
        // revision 3 / V 2, which shares no /CF crypt-filter machinery with
        // AES - detecting it by revision alone would mistake it for AES-128
        // and hand every caller silent garbage, so it has to be rejected
        // explicitly.
        $encrypted = $this->runQpdf([
            '--allow-weak-crypto', '--encrypt', 'open-me', 'admin123', '128', '--',
            __DIR__ . '/../tmp/test-extract.pdf',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unsupported encryption configuration');

        new Document(file_get_contents($encrypted), 'open-me');
    }

    public function testThrowsWhenTheEncryptDictionaryCannotBeLocated()
    {
        // /Encrypt points at an object number the xref doesn't know about.
        $pdf = str_replace(
            '/Root 1 0 R',
            '/Root 1 0 R /Encrypt 99 0 R',
            $this->buildSimplePdf()
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not locate');

        new Document($pdf, 'open-me');
    }

    public function testThrowsWhenTheEncryptDictionaryIsNotADictionary()
    {
        $pdf = str_replace('/Root 1 0 R', '/Root 1 0 R /Encrypt 42', $this->buildSimplePdf());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing or malformed');

        new Document($pdf, 'open-me');
    }

    public function testAnIndirectFileIdDoesNotFatalWhileOpeningAnEncryptedPdf()
    {
        // /ID as an indirect reference is malformed, but must surface as a
        // catchable Exception rather than a fatal "cannot use object as array"
        // Error while the revision 4 file key is being derived.
        $encryptObj = "4 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 " .
            "/CF << /StdCF << /CFM /AESV2 /Length 16 >> >> /StmF /StdCF /StrF /StdCF " .
            '/O <' . str_repeat('61', 32) . '> /U <' . str_repeat('62', 32) . "> /P -4 >>\nendobj\n";

        $pdf = str_replace(
            "trailer\n<< /Size 4 /Root 1 0 R >>",
            "trailer\n<< /Size 5 /Root 1 0 R /Encrypt 4 0 R /ID 9 0 R >>",
            $this->buildSimplePdf()
        );

        // Splice the /Encrypt object in and point the xref at it.
        $insertAt = strpos($pdf, 'xref');
        $pdf      = substr($pdf, 0, $insertAt) . $encryptObj . substr($pdf, $insertAt);
        $pdf      = str_replace('0 4', '0 5', $pdf);
        $pdf      = str_replace(
            "trailer\n",
            sprintf("%010d 00000 n \ntrailer\n", $insertAt),
            $pdf
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('password provided is incorrect');

        new Document($pdf, 'open-me');
    }

    public function testStreamCryptFilterMethodResolvesTheStmFCryptFilter()
    {
        $doc    = new Document($this->buildSimplePdf());
        $method = new \ReflectionMethod(Document::class, 'streamCryptFilterMethod');

        // /V below 4 has no /CF map at all - always RC4.
        $this->assertEquals('V2', $method->invoke($doc, ['V' => 2]));
        $this->assertEquals('', $method->invoke($doc, []));

        // /StmF defaults to /Identity when absent (ISO 32000-1 Table 20).
        $this->assertEquals('Identity', $method->invoke($doc, ['V' => 4]));
        $this->assertEquals('Identity', $method->invoke($doc, ['V' => 5, 'StmF' => new Value\Name('Identity')]));

        // A named crypt filter is looked up in /CF, not assumed from /R.
        $this->assertEquals('AESV2', $method->invoke($doc, [
            'V' => 4, 'StmF' => new Value\Name('StdCF'),
            'CF' => ['StdCF' => ['CFM' => new Value\Name('AESV2')]],
        ]));
        $this->assertEquals('AESV3', $method->invoke($doc, [
            'V' => 5, 'StmF' => new Value\Name('StdCF'),
            'CF' => ['StdCF' => ['CFM' => new Value\Name('AESV3')]],
        ]));

        // A /StmF naming a crypt filter that isn't in /CF is undeterminable.
        $this->assertEquals('', $method->invoke($doc, ['V' => 5, 'StmF' => new Value\Name('StdCF')]));
    }

    public function testParseAtReportsTheObjectGenerationNumber()
    {
        // Revision 4 (AES-128) derives its per-object key from the object's
        // generation number as well as its object number, so parseAt() has to
        // hand back the real generation rather than assuming 0. Every qpdf
        // fixture writes generation 0, which is exactly why this needs its own
        // test - a hardcoded 0 would pass every one of them.
        $pdf = "%PDF-1.4\n7 3 obj\n<< /Type /Page >>\nendobj\n";

        $doc    = new Document($this->buildSimplePdf());
        $method = new \ReflectionMethod(Document::class, 'parseAt');

        (new \ReflectionProperty(Document::class, 'data'))->setValue($doc, $pdf);

        $generation = null;
        $args       = [strlen("%PDF-1.4\n"), &$generation];
        $value      = $method->invokeArgs($doc, $args);

        $this->assertEquals('Page', $value['Type']->name);
        $this->assertSame(3, $generation);
    }

    public function testDecryptStreamLeavesCrossReferenceStreamsAlone()
    {
        // A cross-reference stream is never encrypted - it has to be readable
        // before the /Encrypt dictionary it points at can even be found.
        $doc = new Document($this->buildSimplePdf());

        (new \ReflectionProperty(Document::class, 'fileKey'))->setValue($doc, str_repeat("\x00", 32));
        (new \ReflectionProperty(Document::class, 'encryptionAlgorithm'))->setValue($doc, 'AES256');

        $method = new \ReflectionMethod(Document::class, 'decryptStream');
        $stream = new Value\Stream(['Type' => new Value\Name('XRef')], 'not ciphertext');

        $this->assertSame($stream, $method->invoke($doc, 5, 0, $stream));
    }

    public function testDecryptStreamReportsAFailedDecrypt()
    {
        $doc = new Document($this->buildSimplePdf());

        (new \ReflectionProperty(Document::class, 'fileKey'))->setValue($doc, str_repeat("\x00", 32));
        (new \ReflectionProperty(Document::class, 'encryptionAlgorithm'))->setValue($doc, 'AES256');

        $method = new \ReflectionMethod(Document::class, 'decryptStream');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not decrypt the stream of object 7');

        $method->invoke($doc, 7, 0, new Value\Stream([], 'too short'));
    }

}
