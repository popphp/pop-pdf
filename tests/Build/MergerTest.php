<?php

namespace Pop\Pdf\Test\Build;

use Pop\Pdf\Build\Merger;
use Pop\Pdf\Build\Exception;
use PHPUnit\Framework\TestCase;

class MergerTest extends TestCase
{

    public function testMergeFilesCombinesPageCounts()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);

        $docPages  = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/doc.pdf')->getNumberOfPages();
        $testPages = (new \Pop\Pdf\Build\Parser())->parseFile(__DIR__ . '/../tmp/test.pdf')->getNumberOfPages();

        $this->assertEquals($docPages + $testPages, $doc->getNumberOfPages());
    }

    public function testMergeDataCombinesPageCounts()
    {
        $merger = new Merger();
        $doc    = $merger->mergeData([
            file_get_contents(__DIR__ . '/../tmp/doc.pdf'),
            file_get_contents(__DIR__ . '/../tmp/test.pdf'),
        ]);

        $this->assertGreaterThan(0, $doc->getNumberOfPages());
    }

    public function testMergedOutputHasNoDuplicateObjectNumbers()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);

        $output = (string) $doc;

        preg_match_all('/(?:^|\n)(\d+) 0 obj/', $output, $matches);
        $numbers = $matches[1];

        $this->assertEquals(count($numbers), count(array_unique($numbers)));
    }

    public function testMergedOutputRoundTripsThroughReExtraction()
    {
        // A well-formed merge must itself be a parseable PDF - round-trip it
        // through the same robust reader used to build it.
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/test.pdf']);
        $bytes  = (string) $doc;

        $reparsed = new \Pop\Pdf\Extract\Document($bytes);
        $root     = $reparsed->getRoot();

        $this->assertNotEmpty($root);
    }

    public function testMergeRequiresAtLeastTwoSources()
    {
        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf']);
    }

    public function testMergeMissingFileThrows()
    {
        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/does-not-exist.pdf']);
    }

    public function testMergedPagesCountReflectsTotalLeafPagesNotSourceCount()
    {
        $merger = new Merger();
        $doc    = $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', __DIR__ . '/../tmp/image-only-2page.pdf']);
        $totalPages = $doc->getNumberOfPages();

        $output   = (string) $doc;
        $reparsed = new \Pop\Pdf\Extract\Document($output);
        $root     = $reparsed->getRoot();
        $pages    = $reparsed->resolve($root['Pages'] ?? null);

        $this->assertEquals($totalPages, $pages['Count']);
        // The master Pages node's own Kids count is the SOURCE count (2),
        // deliberately different from the total leaf page count - this is
        // exactly the distinction the bug conflated.
        $this->assertCount(2, $pages['Kids']);
        $this->assertNotEquals($pages['Count'], count($pages['Kids']));
    }

    public function testMergeWrapsExtractExceptionAsBuildException()
    {
        // mergeSources() reads each source via ObjectGraphReader, which in
        // turn calls Extract\Document::resolve() - a genuine circular
        // reference there raises an Extract\Exception that must surface as
        // this namespace's own Build\Exception, not leak the Extract type.
        $good = file_get_contents(__DIR__ . '/../tmp/doc.pdf');

        $header   = "%PDF-1.4\n";
        $obj1     = "1 0 obj\n1 0 R\nendobj\n";
        $body     = $obj1;
        $xrefPos  = strlen($header . $body);
        $circular = $header . $body .
            "xref\n0 2\n0000000000 65535 f \n" . sprintf("%010d 00000 n \n", strlen($header)) .
            "trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $this->expectException(Exception::class);

        $merger = new Merger();
        $merger->mergeData([$good, $circular]);
    }

    public function testMergeFilesRejectsEncryptedSource()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";

        $header  = "%PDF-1.4\n";
        $body    = $obj1 . $obj2 . $obj3;
        $offsets = [strlen($header), strlen($header . $obj1), strlen($header . $obj1 . $obj2)];
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 4\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offsets[0]) .
            sprintf("%010d 00000 n \n", $offsets[1]) .
            sprintf("%010d 00000 n \n", $offsets[2]) .
            "trailer\n<< /Size 4 /Root 1 0 R /Encrypt << /Filter /Standard >> >>\nstartxref\n{$xrefPos}\n%%EOF";

        $encryptedFile = __DIR__ . '/../tmp/encrypted-test.pdf';
        file_put_contents($encryptedFile, $header . $body . $xref);

        $this->expectException(Exception::class);

        try {
            $merger = new Merger();
            $merger->mergeFiles([__DIR__ . '/../tmp/doc.pdf', $encryptedFile]);
        } finally {
            unlink($encryptedFile);
        }
    }

}
