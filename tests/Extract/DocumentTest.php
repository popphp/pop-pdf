<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{

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

}
