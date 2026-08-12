<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\InfoObject;
use Pop\Pdf\Document\Metadata;
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

}
