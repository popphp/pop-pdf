<?php

namespace Pop\Pdf\Test\Extract\Font;

use Pop\Pdf\Extract\Content\TextRun;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Font\Resolver;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{

    protected function minimalDoc(): Document
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2 . $obj3;
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

    public function testActualTextRunPassesThroughUnchanged()
    {
        $doc = $this->minimalDoc();
        $run = new TextRun(null, null, 'Already decoded', 0.0, 0.0, TextRun::SEPARATOR_NONE);

        $this->assertEquals('Already decoded', Resolver::decodeRun($run, $doc));
    }

    public function testDecodesSimpleFontRun()
    {
        $doc  = $this->minimalDoc();
        $font = ['Type' => new Value\Name('Font'), 'Subtype' => new Value\Name('TrueType'), 'Encoding' => new Value\Name('WinAnsiEncoding')];
        $run  = new TextRun('F1', 'Hi', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, $font);

        $this->assertEquals('Hi', Resolver::decodeRun($run, $doc));
    }

    public function testRunWithNoFontReturnsRawBytesUnchanged()
    {
        $doc = $this->minimalDoc();
        $run = new TextRun('F1', 'raw', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, null);

        $this->assertEquals('raw', Resolver::decodeRun($run, $doc));
    }

    public function testDecodeRunCachesFontInfoOnDocument()
    {
        $doc = $this->minimalDoc();

        // Two SEPARATELY-constructed font dicts (distinct Value\Name instances
        // each) that are structurally identical but not ===-identical, mirroring
        // how Interpreter's Tf handler re-resolves the font dict fresh on every
        // call rather than reusing one PHP array/object graph.
        $font1 = ['Type' => new Value\Name('Font'), 'Subtype' => new Value\Name('TrueType'), 'Encoding' => new Value\Name('WinAnsiEncoding')];
        $font2 = ['Type' => new Value\Name('Font'), 'Subtype' => new Value\Name('TrueType'), 'Encoding' => new Value\Name('WinAnsiEncoding')];

        $run1 = new TextRun('F1', 'Hi', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, $font1);
        $run2 = new TextRun('F1', 'Yo', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, $font2);

        Resolver::decodeRun($run1, $doc);
        Resolver::decodeRun($run2, $doc);

        $cacheProp = new \ReflectionProperty($doc, 'fontInfoCache');
        $cached = $cacheProp->getValue($doc);

        // $font1 and $font2 are structurally-identical (but not ===-identical) font
        // dict arrays - the cache key is content-based, so this must produce
        // exactly ONE cached entry, not two.
        $this->assertCount(1, $cached);
    }

    public function testNullRawBytesWithNoDecodedTextReturnsEmptyString()
    {
        $doc = $this->minimalDoc();
        $run = new TextRun('F1', null, null, 0.0, 0.0, TextRun::SEPARATOR_NONE);

        $this->assertEquals('', Resolver::decodeRun($run, $doc));
    }

    public function testUnresolvableFontDictFallsBackToRawBytes()
    {
        $doc = $this->minimalDoc();
        // $run->font is neither an array nor a Reference to one, so
        // FontInfo::resolve() returns null and the raw bytes pass through.
        $run = new TextRun('F1', 'raw', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, 'not-a-font-dict');

        $this->assertEquals('raw', Resolver::decodeRun($run, $doc));
    }

    public function testUnexpectedExceptionDuringResolutionFallsBackToRawBytes()
    {
        $doc = $this->minimalDoc();
        // A Closure can't be serialize()'d - md5(serialize($run->font)) throws,
        // which decodeRun() must catch and recover from by returning raw bytes.
        $run = new TextRun('F1', 'raw', null, 0.0, 0.0, TextRun::SEPARATOR_NONE, false, static fn() => null);

        $this->assertEquals('raw', Resolver::decodeRun($run, $doc));
    }

}
