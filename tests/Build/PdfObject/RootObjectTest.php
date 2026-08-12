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

}
