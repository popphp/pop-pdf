<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\PageClassifier;
use Pop\Pdf\Extract\Content\PageInfo;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class PageClassifierTest extends TestCase
{

    protected function minimalDoc(): Document
    {
        return new Document("%PDF-1.4\n%%EOF");
    }

    protected function imageResources(): array
    {
        return ['XObject' => ['Im1' => new Value\Stream(['Subtype' => new Value\Name('Image')], '')]];
    }

    public function testClipThenSingleImageIsImageOnly()
    {
        // The exact pattern real scan-to-PDF output uses: a clip rectangle
        // (re/W/n - the 'n' terminator means "end the path without
        // painting", so this produces no visible mark) followed by one
        // image draw.
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], $this->imageResources(), null, null, "q\n1 i\n0 0 612 792 re\nW n\n/GS1 gs\nq\n612 0 0 792 0 0 cm\n/Im1 Do\nQ\nQ\n");

        $this->assertTrue(PageClassifier::isImageOnly($doc, $page));
    }

    public function testActualPaintOperatorDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], [], null, null, '0 0 100 100 re f');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testSameImageDrawnTwiceDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], $this->imageResources(), null, null, '/Im1 Do /Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testTwoDistinctImagesDisqualifies()
    {
        $doc = $this->minimalDoc();
        $resources = ['XObject' => [
            'Im1' => new Value\Stream(['Subtype' => new Value\Name('Image')], ''),
            'Im2' => new Value\Stream(['Subtype' => new Value\Name('Image')], ''),
        ]];
        $page = new PageInfo([], $resources, null, null, '/Im1 Do /Im2 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testFormXObjectDrawDisqualifies()
    {
        $doc = $this->minimalDoc();
        $resources = ['XObject' => ['Fm1' => new Value\Stream(['Subtype' => new Value\Name('Form')], '')]];
        $page = new PageInfo([], $resources, null, null, '/Fm1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testClipOnlyWithZeroImagesDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], [], null, null, 'q re W n Q');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testTextPlusImageDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], $this->imageResources(), null, null, 'BT /F1 12 Tf (Hi) Tj ET /Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testInlineImageDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], [], null, null, "BI /W 1 /H 1 ID \x00 EI");

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testEmptyContentDisqualifies()
    {
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], [], null, null, '');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testUnresolvableDoTargetDisqualifies()
    {
        $doc  = $this->minimalDoc();
        // References a resource name that isn't in /XObject at all.
        $page = new PageInfo([], ['XObject' => []], null, null, '/DoesNotExist Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testMalformedOperandIsSkippedThenSingleImageStillClassifies()
    {
        // A stray ']' is a token type the object parser can't handle at the
        // top level - it must be skipped (not abort classification), and
        // the single image draw that follows still makes the page qualify.
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], $this->imageResources(), null, null, '] /Im1 Do');

        $this->assertTrue(PageClassifier::isImageOnly($doc, $page));
    }

    public function testDoWithNonNameOperandIsSkippedThenSubsequentImageStillClassifies()
    {
        // A numeric operand in front of 'Do' isn't a resource name - that Do
        // is skipped as a no-op, not treated as a Form/unresolvable draw.
        $doc  = $this->minimalDoc();
        $page = new PageInfo([], $this->imageResources(), null, null, '123 Do /Im1 Do');

        $this->assertTrue(PageClassifier::isImageOnly($doc, $page));
    }

    public function testXObjectResolvingToNonStreamDisqualifies()
    {
        $doc = $this->minimalDoc();
        $resources = ['XObject' => ['Im1' => new Value\Name('NotAStream')]];
        $page = new PageInfo([], $resources, null, null, '/Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testXObjectWithNonNameSubtypeDisqualifies()
    {
        $doc = $this->minimalDoc();
        $resources = ['XObject' => ['Im1' => new Value\Stream(['Subtype' => 'Image'], '')]];
        $page = new PageInfo([], $resources, null, null, '/Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testXObjectWithUnrecognizedSubtypeDisqualifies()
    {
        $doc = $this->minimalDoc();
        $resources = ['XObject' => ['Im1' => new Value\Stream(['Subtype' => new Value\Name('Group')], '')]];
        $page = new PageInfo([], $resources, null, null, '/Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

    public function testCircularXObjectResourceReferenceDisqualifiesInsteadOfThrowing()
    {
        // Object 5 refers to itself - resolving /XObject must hit Document's
        // circular-reference guard, which resolveIsImage() must catch and
        // treat as "can't prove this page is safe" rather than letting the
        // exception escape classification.
        $obj5    = "5 0 obj\n5 0 R\nendobj\n";
        $header  = "%PDF-1.4\n";
        $o5      = strlen($header);
        $xrefPos = strlen($header . $obj5);
        $xref    = "xref\n0 6\n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n" .
            "0000000000 65535 f \n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $o5) .
            "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        $doc = new Document($header . $obj5 . $xref);

        $page = new PageInfo([], ['XObject' => new Value\Reference(5, 0)], null, null, '/Im1 Do');

        $this->assertFalse(PageClassifier::isImageOnly($doc, $page));
    }

}
