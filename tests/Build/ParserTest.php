<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf;
use Pop\Pdf\Build\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

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
        $doc = Pdf\Pdf::importFromFile(__DIR__ . '/../../docs/pdf-samples/pabs-client-api-v1.3.1.pdf');
        $totalPages = $doc->getNumberOfPages();

        $output   = (string) $doc;
        $reparsed = new Pdf\Extract\Document($output);
        $root     = $reparsed->getRoot();
        $pages    = $reparsed->resolve($root['Pages'] ?? null);

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

}