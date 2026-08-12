<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\PageObject;
use PHPUnit\Framework\TestCase;

class AbstractObjectTest extends TestCase
{

    public function testGetDataReturnsRawTemplate()
    {
        $page = new PageObject(612, 792, 4);

        $this->assertIsString($page->getData());
        $this->assertStringContainsString('[{page_index}]', $page->getData());
    }

    public function testGetDictionaryReferencesParsesArrayOfIndirectReferences()
    {
        $page = new PageObject();

        $this->assertEquals(['3', '5'], $page->getDictionaryReferences('[3 0 R 5 0 R]'));
    }

    public function testGetDictionaryReferencesParsesSingleIndirectReference()
    {
        $page = new PageObject();

        $this->assertEquals(['7'], $page->getDictionaryReferences('7 0 R'));
    }

}
