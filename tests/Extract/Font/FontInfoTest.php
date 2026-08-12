<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Font\FontInfo;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class FontInfoTest extends TestCase
{

    protected function minimalDoc(): Document
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

        return new Document($header . $body . $xref);
    }

    /**
     * Build a Document with the standard Catalog/Pages/Page (objects 1-3)
     * plus caller-supplied extra objects (numbered 4, 5, 6, ... in order).
     */
    protected function docWithExtraObjects(array $extraObjects): Document
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $objects = array_merge([$obj1, $obj2, $obj3], $extraObjects);

        $body    = '';
        $offsets = [];
        $pos     = strlen($header);
        foreach ($objects as $obj) {
            $offsets[] = $pos;
            $body     .= $obj;
            $pos      += strlen($obj);
        }

        $xrefPos = strlen($header . $body);
        $count   = count($objects) + 1;

        $xref = "xref\n0 {$count}\n" . "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }
        $xref .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n" .
            "startxref\n{$xrefPos}\n%%EOF";

        return new Document($header . $body . $xref);
    }

    public function testResolvesSimpleFontWithNamedEncoding()
    {
        $doc = $this->minimalDoc();
        $fontDict = [
            'Type'     => new Value\Name('Font'),
            'Subtype'  => new Value\Name('TrueType'),
            'Encoding' => new Value\Name('WinAnsiEncoding'),
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertFalse($info->isType0);
        $this->assertInstanceOf(Value\Name::class, $info->encoding);
        $this->assertEquals('WinAnsiEncoding', $info->encoding->name);
        $this->assertNull($info->toUnicodeCMap);
    }

    public function testResolvesSimpleFontWithDifferencesEncoding()
    {
        $doc = $this->minimalDoc();
        $fontDict = [
            'Type'    => new Value\Name('Font'),
            'Subtype' => new Value\Name('TrueType'),
            'Encoding' => [
                'BaseEncoding' => new Value\Name('WinAnsiEncoding'),
                'Differences'  => [1, new Value\Name('bullet')],
            ],
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertFalse($info->isType0);
        $this->assertIsArray($info->encoding);
        $this->assertEquals('WinAnsiEncoding', $info->encoding['BaseEncoding']->name);
    }

    public function testResolvesType0FontWithIdentityEncoding()
    {
        $doc = $this->minimalDoc();
        $fontDict = [
            'Type'            => new Value\Name('Font'),
            'Subtype'         => new Value\Name('Type0'),
            'Encoding'        => new Value\Name('Identity-H'),
            'DescendantFonts' => [
                [
                    'Type'         => new Value\Name('Font'),
                    'Subtype'      => new Value\Name('CIDFontType2'),
                    'CIDToGIDMap'  => new Value\Name('Identity'),
                ],
            ],
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertTrue($info->isType0);
        $this->assertEquals('Identity-H', $info->encoding->name);
        $this->assertEquals('Identity', $info->cidToGidMap);
    }

    public function testReturnsNullForUnresolvableFont()
    {
        $doc = $this->minimalDoc();

        $this->assertNull(FontInfo::resolve($doc, 'not a font dict'));
        $this->assertNull(FontInfo::resolve($doc, null));
    }

    public function testResolvesType0FontWithIndirectlyReferencedEncodingName()
    {
        // Object 4 holds the /Encoding value itself, indirectly referenced -
        // resolveType0() must follow that reference before inspecting it.
        $obj4 = "4 0 obj\n/Identity-H\nendobj\n";
        $doc  = $this->docWithExtraObjects([$obj4]);

        $fontDict = [
            'Type'            => new Value\Name('Font'),
            'Subtype'         => new Value\Name('Type0'),
            'Encoding'        => new Value\Reference(4, 0),
            'DescendantFonts' => [
                [
                    'Type'        => new Value\Name('Font'),
                    'Subtype'     => new Value\Name('CIDFontType2'),
                    'CIDToGIDMap' => new Value\Name('Identity'),
                ],
            ],
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertInstanceOf(Value\Name::class, $info->encoding);
        $this->assertEquals('Identity-H', $info->encoding->name);
    }

    public function testResolvesType0FontWithIndirectlyReferencedEmbeddedCMapEncoding()
    {
        // Object 4 is a stream containing a raw CMap program - once the
        // /Encoding reference resolves to a Stream, resolveType0() must
        // decode and parse it into a cidMappings array rather than treating
        // it as a named encoding.
        $cmapProgram = "1 begincidrange\n<0000> <00FF> 0\nendcidrange\n";
        $obj4 = "4 0 obj\n<< /Length " . strlen($cmapProgram) . " >>\nstream\n" .
            $cmapProgram . "\nendstream\nendobj\n";
        $doc  = $this->docWithExtraObjects([$obj4]);

        $fontDict = [
            'Type'            => new Value\Name('Font'),
            'Subtype'         => new Value\Name('Type0'),
            'Encoding'        => new Value\Reference(4, 0),
            'DescendantFonts' => [
                [
                    'Type'        => new Value\Name('Font'),
                    'Subtype'     => new Value\Name('CIDFontType2'),
                    'CIDToGIDMap' => new Value\Name('Identity'),
                ],
            ],
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertIsArray($info->encoding);
        $this->assertEquals(255, $info->encoding['cidMappings'][0x00FF]);
    }

    public function testResolvesType0FontWithIndirectlyReferencedCIDToGIDMapStream()
    {
        // Object 4 is a CIDToGIDMap stream (2 bytes per CID): cid0->gid0,
        // cid1->gid5, cid2->gid10 - resolveType0() must decode it via the
        // filter Registry rather than only accepting the 'Identity' name.
        $mapBytes = "\x00\x00\x00\x05\x00\x0a";
        $obj4 = "4 0 obj\n<< /Length " . strlen($mapBytes) . " >>\nstream\n" .
            $mapBytes . "\nendstream\nendobj\n";
        $doc  = $this->docWithExtraObjects([$obj4]);

        $fontDict = [
            'Type'            => new Value\Name('Font'),
            'Subtype'         => new Value\Name('Type0'),
            'Encoding'        => new Value\Name('Identity-H'),
            'DescendantFonts' => [
                [
                    'Type'        => new Value\Name('Font'),
                    'Subtype'     => new Value\Name('CIDFontType2'),
                    'CIDToGIDMap' => new Value\Reference(4, 0),
                ],
            ],
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertEquals($mapBytes, $info->cidToGidMap);
    }

    public function testResolvesSimpleFontWithIndirectlyReferencedEncodingName()
    {
        // Object 4 holds the /Encoding value itself, indirectly referenced -
        // resolveSimple() must follow that reference before storing it.
        $obj4 = "4 0 obj\n/MacRomanEncoding\nendobj\n";
        $doc  = $this->docWithExtraObjects([$obj4]);

        $fontDict = [
            'Type'     => new Value\Name('Font'),
            'Subtype'  => new Value\Name('TrueType'),
            'Encoding' => new Value\Reference(4, 0),
        ];

        $info = FontInfo::resolve($doc, $fontDict);

        $this->assertFalse($info->isType0);
        $this->assertInstanceOf(Value\Name::class, $info->encoding);
        $this->assertEquals('MacRomanEncoding', $info->encoding->name);
    }

}
