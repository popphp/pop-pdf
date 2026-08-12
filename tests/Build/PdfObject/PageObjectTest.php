<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\PageObject;
use PHPUnit\Framework\TestCase;

class PageObjectTest extends TestCase
{

    public function testDefaultPageRendersUnchangedWithoutExtras()
    {
        $page   = new PageObject(612, 792, 4);
        $result = (string) $page;

        $this->assertStringContainsString('/Type/Page', $result);
        $this->assertStringContainsString('/MediaBox[0 0 612 792]', $result);
        $this->assertStringNotContainsString('#', $result);
    }

    public function testPageExtraIsRenderedIntoTheDict()
    {
        $page = new PageObject(612, 792, 4);
        $page->setPageExtra('/Rotate 90');

        $result = (string) $page;

        $this->assertStringContainsString('/Rotate 90', $result);
    }

    public function testOtherResourcesIsRenderedIntoTheResourcesDict()
    {
        $page = new PageObject(612, 792, 4);
        $page->setOtherResources('/ColorSpace<</CS0 6 0 R>>');

        $result = (string) $page;

        $this->assertStringContainsString('/Resources', $result);
        $this->assertStringContainsString('/ColorSpace<</CS0 6 0 R>>', $result);
    }

    public function testPageExtraAndOtherResourcesCoexistWithFontsAndXObjects()
    {
        $page = new PageObject(612, 792, 4);
        $page->setPageExtra('/Rotate 90');
        $page->setOtherResources('/ColorSpace<</CS0 6 0 R>>');
        $page->addFontReference('/F1 7 0 R');
        $page->addXObjectReference('/Im0 8 0 R');
        $page->addContentIndex(9);

        $result = (string) $page;

        $this->assertStringContainsString('/Rotate 90', $result);
        $this->assertStringContainsString('/ColorSpace<</CS0 6 0 R>>', $result);
        $this->assertStringContainsString('/Font<</F1 7 0 R>>', $result);
        $this->assertStringContainsString('/XObject<</Im0 8 0 R>>', $result);
        $this->assertStringContainsString('/Contents[9 0 R]', $result);
    }

    public function testParseFullFeaturedStreamWithArrayReferences()
    {
        $stream = "4 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents[5 0 R 6 0 R]" .
            "/Annots[7 0 R 12 0 R]/Resources<</ProcSet[/PDF/Text]/Font<</F1 8 0 R>>/XObject<</Im0 9 0 R>>" .
            "/ExtGState<</GS0 10 0 R>>>>/Group<</Type/Group/S/Transparency/CS/DeviceRGB>>>>\nendobj\n";

        $page = PageObject::parse($stream);

        $this->assertEquals(4, $page->getIndex());
        $this->assertEquals(2, $page->getParentIndex());
        $this->assertEquals(612, $page->getWidth());
        $this->assertEquals(792, $page->getHeight());
        $this->assertEquals([5, 6], $page->getContent());
        $this->assertEquals([7, 12], $page->getAnnots());
        $this->assertEquals([8 => '/F1 8 0 R'], $page->getFonts());
        $this->assertEquals([9 => '/Im0 9 0 R'], $page->getXObjects());

        $result = (string) $page;
        $this->assertStringNotContainsString('[{', $result);
        $this->assertStringContainsString('/MediaBox[0 0 612 792]', $result);
        $this->assertStringContainsString('/Contents[5 0 R 6 0 R]', $result);
        $this->assertStringContainsString('/Annots[7 0 R 12 0 R]', $result);
        $this->assertStringContainsString('/Group<</Type/Group/S/Transparency/CS/DeviceRGB>>', $result);
    }

    public function testParseStreamWithoutResourcesUsesSingleNonArrayReferences()
    {
        $stream = "9 0 obj\n<</Type/Page/Parent 3 0 R/MediaBox[0 0 300 400]/Contents 15 0 R/Annots 16 0 R>>\nendobj\n";

        $page = PageObject::parse($stream);

        $this->assertEquals(9, $page->getIndex());
        $this->assertEquals(3, $page->getParentIndex());
        $this->assertEquals(300, $page->getWidth());
        $this->assertEquals(400, $page->getHeight());
        $this->assertEquals([15], $page->getContent());
        $this->assertEquals([16], $page->getAnnots());
        $this->assertEquals([], $page->getFonts());
        $this->assertEquals([], $page->getXObjects());

        $result = (string) $page;
        $this->assertStringNotContainsString('[{', $result);
        $this->assertStringContainsString('/Contents[15 0 R]', $result);
        $this->assertStringContainsString('/Annots[16 0 R]', $result);
        $this->assertStringContainsString('/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI]>>', $result);
    }

    public function testParseStreamWithInlineResourcesDictAndNoIndirectReferences()
    {
        $stream = "11 0 obj\n<</Type/Page/Parent 3 0 R/MediaBox[0 0 200 300]" .
            "/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI]>>>>\nendobj\n";

        $page = PageObject::parse($stream);

        $this->assertEquals(200, $page->getWidth());
        $this->assertEquals(300, $page->getHeight());

        $result = (string) $page;
        $this->assertStringNotContainsString('[{', $result);
        $this->assertStringContainsString('/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI]>>', $result);
    }

    public function testParseWithAnnotsBeforeTrailingSingleContentsReference()
    {
        // Annots is followed by another key ('/Contents'), exercising the '/' delimiter
        // branch for Annots; Contents is the last key before '>>', exercising the '>'
        // delimiter branch for Contents.
        $stream = "20 0 obj\n<</Type/Page/Parent 3 0 R/MediaBox[0 0 100 100]/Annots 21 0 R/Contents 22 0 R>>\nendobj\n";

        $page = PageObject::parse($stream);

        $this->assertEquals([21], $page->getAnnots());
        $this->assertEquals([22], $page->getContent());
    }

    public function testParseWithAnnotsFollowedByAnUnrecognizedKeyHitsTheSlashDelimiterBranch()
    {
        // Contents/Font/XObject/ExtGState/Group/Resources all get replaced with a
        // '[{...}]' placeholder (note the literal brackets) before Annots is parsed,
        // so any of THOSE following Annots reintroduces a '[' into the remaining
        // substring and is caught by the earlier bracket check instead. Only a key
        // this method doesn't otherwise recognize leaves a bare '/' for Annots'
        // non-array branch to actually split on.
        $stream = "20 0 obj\n<</Type/Page/Parent 3 0 R/MediaBox[0 0 100 100]/Annots 21 0 R/CustomKey 5>>\nendobj\n";

        $page = PageObject::parse($stream);

        $this->assertEquals([21], $page->getAnnots());
    }

    public function testSetAnnotsContentXObjectsFontsAndGetters()
    {
        $page = new PageObject(612, 792, 4);
        $page->setAnnots([1, 2]);
        $page->setContent([3, 4]);
        $page->setXObjects(['/Im0 5 0 R', '/Im1 6 0 R']);
        $page->setFonts(['/F1 7 0 R', '/F2 8 0 R']);

        $this->assertEquals([1, 2], $page->getAnnots());
        $this->assertEquals([3, 4], $page->getContent());
        $this->assertEquals([5 => '/Im0 5 0 R', 6 => '/Im1 6 0 R'], $page->getXObjects());
        $this->assertEquals([7 => '/F1 7 0 R', 8 => '/F2 8 0 R'], $page->getFonts());

        // hasAnnot()/hasContent() check by array key (position), not by value
        $this->assertTrue($page->hasAnnot(0));
        $this->assertTrue($page->hasAnnot(1));
        $this->assertFalse($page->hasAnnot(2));
        $this->assertTrue($page->hasContent(0));
        $this->assertTrue($page->hasContent(1));
        $this->assertFalse($page->hasContent(2));

        $page->addContentIndex(10);
        $this->assertEquals(10, $page->getCurrentContentIndex());
    }

    public function testToStringInsertsAnnotsXObjectsFontsWhenPlaceholdersAbsent()
    {
        $page = new PageObject(612, 792, 4);
        $page->setData(
            "[{page_index}] 0 obj\n<</Type/Page/Parent [{parent}] 0 R/MediaBox[0 0 [{width}] [{height}]]" .
            "[{content_objects}]/Resources<</ProcSet[/PDF/Text/ImageB/ImageC/ImageI]>>>>\nendobj\n"
        );
        $page->addAnnotIndex(5);
        $page->addXObjectReference('/Im0 8 0 R');
        $page->addFontReference('/F1 7 0 R');

        $result = (string) $page;

        $this->assertStringContainsString('/Annots[5 0 R]/MediaBox', $result);
        $this->assertStringContainsString('/XObject<</Im0 8 0 R>>/Font<</F1 7 0 R>>/ProcSet', $result);
    }

}
