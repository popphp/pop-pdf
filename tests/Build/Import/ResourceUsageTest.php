<?php

namespace Pop\Pdf\Test\Build\Import;

use Pop\Pdf\Build\Import\ResourceUsage;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ResourceUsageTest extends TestCase
{

    /**
     * Build a PDF whose page (object 3) uses content streams 10+ and a shared
     * resource dictionary (object 4), from extra numbered object bodies
     */
    protected function buildDoc(array $objects): Document
    {
        $objects += [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources 4 0 R /Contents 10 0 R >>",
        ];
        ksort($objects);

        $max     = max(array_keys($objects));
        $header  = "%PDF-1.4\n";
        $cur     = $header;
        $offsets = [];
        for ($i = 1; $i <= $max; $i++) {
            $body        = $objects[$i] ?? 'null';
            $offsets[$i] = strlen($cur);
            $cur        .= "{$i} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($cur);
        $xref    = "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $xref .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return new Document($cur . $xref);
    }

    protected function stream(string $content, string $dict = ''): string
    {
        return "<< /Length " . strlen($content) . " {$dict} >>\nstream\n{$content}\nendstream";
    }

    protected function prune(Document $doc, array $contentObjNums): array
    {
        $refs = array_map(fn ($n) => new Value\Reference($n, 0), $contentObjNums);
        return ResourceUsage::prune($doc, $refs, $doc->resolve(new Value\Reference(4, 0)));
    }

    public function testKeepsOnlyTheResourcesTheContentNames()
    {
        $doc = $this->buildDoc([
            4  => "<< /ProcSet [/PDF /Text] /XObject << /Im1 5 0 R /Im2 6 0 R /Im3 7 0 R >> " .
                "/Font << /F1 8 0 R /F2 9 0 R >> /ExtGState << /GS1 11 0 R /GS2 12 0 R >> " .
                "/ColorSpace << /CS1 13 0 R /CS2 14 0 R >> /Shading << /Sh1 15 0 R /Sh2 16 0 R >> " .
                "/Pattern << /P1 17 0 R /P2 18 0 R >> /Properties << /MC1 19 0 R /MC2 20 0 R >> >>",
            10 => $this->stream(
                "/GS2 gs /CS1 cs 0.5 /P2 scn /Sh1 sh /OC /MC2 BDC /Im2 Do EMC BT /F1 12 Tf ET"
            ),
        ]);

        $resources = $this->prune($doc, [10]);

        $this->assertEquals(['Im2'], array_keys($resources['XObject']));
        $this->assertEquals(['F1'], array_keys($resources['Font']));
        $this->assertEquals(['GS2'], array_keys($resources['ExtGState']));
        $this->assertEquals(['CS1'], array_keys($resources['ColorSpace']));
        $this->assertEquals(['Sh1'], array_keys($resources['Shading']));
        $this->assertEquals(['P2'], array_keys($resources['Pattern']));
        $this->assertEquals(['MC2'], array_keys($resources['Properties']));
        // Not a named resource type - left as it is
        $this->assertCount(2, $resources['ProcSet']);
    }

    public function testOperandsAndOperatorSplitAcrossContentStreams()
    {
        $doc = $this->buildDoc([
            4  => "<< /XObject << /Im1 5 0 R /Im2 6 0 R >> >>",
            10 => $this->stream("q /Im2"),
            11 => $this->stream("Do Q"),
        ]);

        $this->assertEquals(['Im2'], array_keys($this->prune($doc, [10, 11])['XObject']));
    }

    public function testFollowsAFormThatInheritsThePageResources()
    {
        // Form 5 has no /Resources, so the /Im2 it draws is looked up in the
        // page's resources and must be kept
        $doc = $this->buildDoc([
            4  => "<< /XObject << /Fm1 5 0 R /Im1 6 0 R /Im2 7 0 R >> >>",
            5  => $this->stream("/Im2 Do", "/Type /XObject /Subtype /Form /BBox [0 0 10 10]"),
            10 => $this->stream("/Fm1 Do"),
        ]);

        $this->assertEquals(['Fm1', 'Im2'], array_keys($this->prune($doc, [10])['XObject']));
    }

    public function testDoesNotFollowAFormWithItsOwnResources()
    {
        // Form 5's /Im2 is looked up in its own resources (object 8), so it
        // doesn't make the page's /Im2 used
        $doc = $this->buildDoc([
            4  => "<< /XObject << /Fm1 5 0 R /Im1 6 0 R /Im2 7 0 R >> >>",
            5  => $this->stream("/Im2 Do", "/Type /XObject /Subtype /Form /BBox [0 0 10 10] /Resources 8 0 R"),
            8  => "<< /XObject << /Im2 7 0 R >> >>",
            10 => $this->stream("/Fm1 Do"),
        ]);

        $this->assertEquals(['Fm1'], array_keys($this->prune($doc, [10])['XObject']));
    }

    public function testInlineImageColorSpaceIsKept()
    {
        $doc = $this->buildDoc([
            4  => "<< /ColorSpace << /CS1 5 0 R /CS2 6 0 R >> /XObject << /Im1 7 0 R >> >>",
            10 => $this->stream("BI /W 1 /H 1 /BPC 8 /CS /CS2 ID \x00\xff EI /Im1 Do"),
        ]);

        $resources = $this->prune($doc, [10]);

        $this->assertEquals(['CS2'], array_keys($resources['ColorSpace']));
        $this->assertEquals(['Im1'], array_keys($resources['XObject']));
    }

    public function testUnreadableContentKeepsTheResourcesWhole()
    {
        $doc = $this->buildDoc([
            4  => "<< /XObject << /Im1 5 0 R /Im2 6 0 R >> >>",
            10 => $this->stream("/Im2 Do", "/Filter /NoSuchDecode"),
        ]);

        $resources = $this->prune($doc, [10]);

        $this->assertInstanceOf(Value\Reference::class, $resources['XObject']['Im1']);
        $this->assertCount(2, $resources['XObject']);
    }

}
