<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\Document;
use PHPUnit\Framework\TestCase;

class DocumentIntegrationTest extends TestCase
{

    public function testResolvesRootAndPageCountForTestExtractFixture()
    {
        $doc  = Document::fromFile(__DIR__ . '/../tmp/test-extract.pdf');
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);

        $pages = $doc->resolve($root['Pages']);
        $this->assertEquals(1, $pages['Count']);
    }

    public function testResolvesRootAndPageCountForDocFixture()
    {
        $doc  = Document::fromFile(__DIR__ . '/../tmp/doc.pdf');
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);

        $pages = $doc->resolve($root['Pages']);
        $this->assertEquals(3, $pages['Count']);
    }

    public function testResolvesObjectStreamResidentObjectForPdf15Fixture()
    {
        $doc  = Document::fromFile(__DIR__ . '/../tmp/test-extract-1.5.pdf');
        $root = $doc->getRoot();

        $this->assertEquals('Catalog', $root['Type']->name);

        $pages = $doc->resolve($root['Pages']);
        $this->assertEquals(3, $pages['Count']);

        // Confirm this fixture actually exercises the inStream (ObjStm)
        // lookup path in Document::getObject() - not just the classic
        // xref-table path - by finding an object whose xref location is
        // "inStream"-based and asserting it resolves to a sensible value.
        $ref         = new \ReflectionClass($doc);
        $offsetsProp = $ref->getProperty('offsets');
        $offsets = $offsetsProp->getValue($doc);

        $inStreamObjNums = [];
        foreach ($offsets as $objNum => $location) {
            if (isset($location['inStream'])) {
                $inStreamObjNums[] = $objNum;
            }
        }

        $this->assertNotEmpty(
            $inStreamObjNums,
            'Expected this PDF 1.5 fixture to contain at least one ObjStm-resident object.'
        );

        // Object 9 is known (via inspection) to live inside object stream 8
        // and to be a /Type /Font dictionary - a concrete, non-trivial value
        // that can only have come from expanding the ObjStm.
        $this->assertContains(9, $inStreamObjNums);

        $font = $doc->getObject(9);
        $this->assertIsArray($font);
        $this->assertArrayHasKey('Type', $font);
        $this->assertEquals('Font', $font['Type']->name);
    }

}
