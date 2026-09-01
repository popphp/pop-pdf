<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\RootObject;
use PHPUnit\Framework\TestCase;

class RootObjectTest extends TestCase
{

    public function testParseExtractsIndexAndParentIndexWithoutMetadata()
    {
        $stream = "1 0 obj\n<</Pages 2 0 R/Type/Catalog>>\nendobj\n";

        $root = RootObject::parse($stream);

        $this->assertEquals(1, $root->getIndex());
        $this->assertEquals(2, $root->getParentIndex());

        $result = (string) $root;
        $this->assertStringContainsString('/Pages 2 0 R', $result);
        $this->assertStringContainsString('/Type/Catalog', $result);
    }

    public function testParseStripsMetadataReference()
    {
        $stream = "1 0 obj\n<</Metadata 8 0 R/Pages 2 0 R/Type/Catalog>>\nendobj\n";

        $root = RootObject::parse($stream);

        $this->assertEquals(1, $root->getIndex());
        $this->assertEquals(2, $root->getParentIndex());

        $result = (string) $root;
        $this->assertStringNotContainsString('Metadata', $result);
        $this->assertStringContainsString('/Pages 2 0 R', $result);
    }

    public function testVersionAndParentIndexGetters()
    {
        $root = new RootObject(1);
        $root->setVersion(1.4);

        $this->assertEquals(1.4, $root->getVersion());
        $this->assertEquals(2, $root->getParentIndex());

        $result = (string) $root;
        $this->assertStringStartsWith('%PDF-1.4', $result);
    }

    public function testWholeNumberVersionIsFormattedWithDecimalPoint()
    {
        // PHP casts a whole-number float to a string without a decimal
        // point (e.g. (string) 2.0 === '2'), which previously produced a
        // malformed '%PDF-2' header instead of the required '%PDF-2.0' -
        // a structurally invalid magic header that strict readers (e.g.
        // Adobe Acrobat) reject outright, even though lenient readers
        // (poppler, Chromium) tolerate it.
        $root = new RootObject(1);
        $root->setVersion(2.0);

        $result = (string) $root;
        $this->assertStringStartsWith('%PDF-2.0' . "\n", $result);
    }

    public function testVersionOneIsFormattedWithDecimalPoint()
    {
        $root = new RootObject(1);
        $root->setVersion(1.0);

        $result = (string) $root;
        $this->assertStringStartsWith('%PDF-1.0' . "\n", $result);
    }

}
