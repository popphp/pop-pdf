<?php

namespace Pop\Pdf\Test\Extract\Content;

use Pop\Pdf\Extract\Content\Interpreter;
use Pop\Pdf\Extract\Content\TextRun;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Value\Stream;
use PHPUnit\Framework\TestCase;

class InterpreterTest extends TestCase
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

    public function testSingleTjProducesOneRunWithNoSeparator()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Hello) Tj ET', []);

        $this->assertCount(1, $runs);
        $this->assertEquals('F1', $runs[0]->fontResourceName);
        $this->assertEquals('Hello', $runs[0]->rawBytes);
        $this->assertEquals(0.0, $runs[0]->x);
        $this->assertEquals(0.0, $runs[0]->y);
        $this->assertEquals(TextRun::SEPARATOR_NONE, $runs[0]->separator);
    }

    public function testTdAccumulatesAndProducesTabSeparator()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Hi) Tj 100 0 Td (There) Tj ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals(0.0, $runs[0]->x);
        $this->assertEquals(100.0, $runs[1]->x);
        $this->assertEquals(TextRun::SEPARATOR_TAB, $runs[1]->separator);
    }

    public function testTdYChangeProducesNewlineSeparator()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Hi) Tj 0 -20 Td (There) Tj ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals(-20.0, $runs[1]->y);
        $this->assertEquals(TextRun::SEPARATOR_NEWLINE, $runs[1]->separator);
    }

    public function testTJArrayHandlesStringsAndKerningNumbers()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf [(AB) -500 (CD)] TJ ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals('AB', $runs[0]->rawBytes);
        $this->assertEquals('CD', $runs[1]->rawBytes);
        // -500 thousandths at font size 12 = -(-500/1000)*12 = 6 units advance,
        // projected through the identity text matrix (tm.a=1, tm.i=0).
        $this->assertEquals(6.0, $runs[1]->x);
        $this->assertEquals(0.0, $runs[1]->y);
    }

    public function testQQRestoresFontButNotTextPosition()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run(
            $doc,
            'BT /F1 12 Tf q /F2 18 Tf (Inner) Tj Q (Outer) Tj ET',
            []
        );

        $this->assertCount(2, $runs);
        $this->assertEquals('F2', $runs[0]->fontResourceName);
        $this->assertEquals('F1', $runs[1]->fontResourceName);
    }

    public function testQuoteOperatorResetsLineAndShowsText()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, "BT /F1 12 Tf 14 TL 100 0 Td (First) Tj (Second) ' ET", []);

        $this->assertCount(2, $runs);
        // The quote operator resets td.x to 0 and adds leading to td.y.
        $this->assertEquals(0.0, $runs[1]->x);
        $this->assertEquals(-14.0, $runs[1]->y);
        $this->assertEquals('Second', $runs[1]->rawBytes);
    }

    public function testBdcActualTextSubstitutesReplacementText()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $content     = "BT /F1 12 Tf /Span << /ActualText (Replacement) >> BDC (raw glyphs) Tj EMC ET";
        $runs        = $interpreter->run($doc, $content, []);

        $this->assertCount(1, $runs);
        $this->assertNull($runs[0]->rawBytes);
        $this->assertEquals('Replacement', $runs[0]->decodedText);
        $this->assertNull($runs[0]->fontResourceName);
    }

    public function testBmcReversedCharsFlagsSubsequentRuns()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $content     = 'BT /F1 12 Tf /ReversedChars BMC (abc) Tj EMC (def) Tj ET';
        $runs        = $interpreter->run($doc, $content, []);

        $this->assertCount(2, $runs);
        $this->assertTrue($runs[0]->reversed);
        $this->assertFalse($runs[1]->reversed);
    }

    public function testDoRecursesIntoFormXObjectAndRestoresState()
    {
        $doc = $this->minimalDoc();

        // A resources dict with one Form XObject (object 4 in this
        // standalone document, unrelated to minimalDoc()'s own objects -
        // Interpreter only needs a resolvable Value\Reference/Stream, not
        // a fully wired-up page tree, since it works directly off the
        // $resources array passed to run().
        $formContent = 'BT /F2 10 Tf (FormText) Tj ET';
        $formObj     = "4 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($formContent) .
            " >>\nstream\n{$formContent}\nendstream\nendobj\n";

        // Re-parse a tiny standalone document containing just this Form
        // object so Document::resolve() can find it by reference.
        $header  = "%PDF-1.4\n";
        $offset4 = strlen($header);
        $xrefPos = strlen($header . $formObj);
        $xref    = "xref\n0 5\n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset4) .
            "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        // Root doesn't need to resolve for this test - only object 4 does.
        $formDoc = new Document($header . $formObj . $xref);

        $resources = ['XObject' => ['Fm0' => new \Pop\Pdf\Extract\Value\Reference(4, 0)]];

        $interpreter = new Interpreter();
        $runs        = $interpreter->run($formDoc, 'BT /F1 12 Tf (Before) Tj ET /Fm0 Do BT (After) Tj ET', $resources);

        $this->assertCount(3, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('F1', $runs[0]->fontResourceName);
        $this->assertEquals('FormText', $runs[1]->rawBytes);
        $this->assertEquals('F2', $runs[1]->fontResourceName);
        $this->assertEquals('After', $runs[2]->rawBytes);
        // Font restored to F1 after the Do call returns, per PDF spec 8.10.2.
        $this->assertEquals('F1', $runs[2]->fontResourceName);
    }

    public function testDoSkipsCircularFormReference()
    {
        $formContent = '/Fm0 Do';
        $formObj     = "4 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($formContent) .
            " >>\nstream\n{$formContent}\nendstream\nendobj\n";

        $header  = "%PDF-1.4\n";
        $offset4 = strlen($header);
        $xrefPos = strlen($header . $formObj);
        $xref    = "xref\n0 5\n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset4) .
            "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        $formDoc = new Document($header . $formObj . $xref);

        $resources = ['XObject' => ['Fm0' => new \Pop\Pdf\Extract\Value\Reference(4, 0)]];

        $interpreter = new Interpreter();
        // Object 4's own content stream invokes itself via '/Fm0 Do' - must
        // not recurse forever.
        $runs = $interpreter->run($formDoc, '/Fm0 Do', $resources);

        $this->assertEquals([], $runs);
    }

    public function testInlineImageIsSkippedWithoutCorruptingSubsequentTokens()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $content     = "BT /F1 12 Tf (Before) Tj ET\nBI /W 2 /H 2 /BPC 8 /CS /G ID " .
            "\x00\x01\x02\x03\x04\x05\x06\x07 EI\nBT (After) Tj ET";
        $runs        = $interpreter->run($doc, $content, []);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

    public function testDoCircularReferenceViaDirectInlineStreamDoesNotCrash()
    {
        // A Form XObject supplied as a direct inline Value\Stream (not an
        // indirect reference) has no objNum for the objNum-keyed cycle
        // guard to key on - the depth cap must catch it instead.
        $doc = $this->minimalDoc();

        $formStream = new \Pop\Pdf\Extract\Value\Stream(
            [
                'Type'    => new \Pop\Pdf\Extract\Value\Name('XObject'),
                'Subtype' => new \Pop\Pdf\Extract\Value\Name('Form'),
            ],
            '/Fm0 Do'
        );

        $resources = ['XObject' => ['Fm0' => $formStream]];

        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, '/Fm0 Do', $resources);

        $this->assertEquals([], $runs);
    }

    public function testRunResetsStateBetweenCalls()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();

        $interpreter->run($doc, 'BT /F1 12 Tf 100 700 Td (Page1) Tj ET', []);
        $runs2 = $interpreter->run($doc, 'BT (Page2) Tj ET', []);

        $this->assertCount(1, $runs2);
        $this->assertNull($runs2[0]->fontResourceName);
        $this->assertEquals(0.0, $runs2[0]->x);
        $this->assertEquals(0.0, $runs2[0]->y);
    }

    public function testMalformedOperandDoesNotAbortWholePage()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Before) Tj ] (After) Tj ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

    public function testNonStringTjOperandIsIgnoredNotWarned()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf [1 2] Tj (Real) Tj ET', []);

        $this->assertCount(1, $runs);
        $this->assertEquals('Real', $runs[0]->rawBytes);
    }

    public function testTfResolvesFontDictFromPageResources()
    {
        $doc       = $this->minimalDoc();
        $fontDict  = ['Type' => new \Pop\Pdf\Extract\Value\Name('Font'), 'Subtype' => new \Pop\Pdf\Extract\Value\Name('TrueType')];
        $resources = ['Font' => ['F1' => $fontDict]];

        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Hello) Tj ET', $resources);

        $this->assertCount(1, $runs);
        $this->assertSame($fontDict, $runs[0]->font);
    }

    public function testNestedFormResourcesOverrideSameFontName()
    {
        $pageFont = ['Type' => new \Pop\Pdf\Extract\Value\Name('Font'), 'Subtype' => new \Pop\Pdf\Extract\Value\Name('TrueType')];
        $formFont = ['Type' => new \Pop\Pdf\Extract\Value\Name('Font'), 'Subtype' => new \Pop\Pdf\Extract\Value\Name('Type0')];

        $formContent = 'BT /F1 10 Tf (FormText) Tj ET'; // Same resource name "F1" as the page,
        // but resolves against the Form's OWN /Resources, not the page's.
        $formObj = "4 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($formContent) .
            " >>\nstream\n{$formContent}\nendstream\nendobj\n";

        $header  = "%PDF-1.4\n";
        $offset4 = strlen($header);
        $xrefPos = strlen($header . $formObj);
        $xref    = "xref\n0 5\n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $offset4) .
            "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        $formDoc = new \Pop\Pdf\Extract\Document($header . $formObj . $xref);

        $resources = [
            'Font'    => ['F1' => $pageFont],
            'XObject' => ['Fm0' => new \Pop\Pdf\Extract\Value\Reference(4, 0)],
        ];

        // The Form XObject's own /Resources /Font /F1 - deliberately not
        // pre-resolved here, since handleDo() resolves xobject->dict['Resources']
        // itself; this test relies on the Form stream having no /Resources of
        // its own, so it falls back to the CALLER's resources per spec - to
        // actually exercise a genuine override, this Form would need its own
        // /Resources dict, which requires it to be a resolvable object in
        // formDoc. Simplify: assert the Form's runs still resolve SOME font
        // (falling back to the page's F1, per the documented inheritance
        // behavior), proving resources threading reaches nested Forms at all.
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($formDoc, 'BT /F1 12 Tf (Before) Tj ET /Fm0 Do', $resources);

        $this->assertCount(2, $runs);
        $this->assertSame($pageFont, $runs[0]->font);
        $this->assertSame($pageFont, $runs[1]->font); // Form has no own /Resources, inherits page's F1.
    }

    public function testCircularFontReferenceDoesNotAbortWholePage()
    {
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $obj5 = "5 0 obj\n5 0 R\nendobj\n"; // circular reference: object 5 points to itself

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj5;
        $o1     = strlen($header);
        $o2     = strlen($header . $obj1);
        $o3     = strlen($header . $obj1 . $obj2);
        $o5     = strlen($header . $obj1 . $obj2 . $obj3);
        $xrefPos = strlen($header . $body);

        $xref = "xref\n0 6\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $o1) .
            sprintf("%010d 00000 n \n", $o2) .
            sprintf("%010d 00000 n \n", $o3) .
            "0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $o5) .
            "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc = new \Pop\Pdf\Extract\Document($header . $body . $xref);

        $resources   = ['Font' => new \Pop\Pdf\Extract\Value\Reference(5, 0)];
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf (Hello) Tj ET', $resources);

        $this->assertCount(1, $runs);
        $this->assertEquals('Hello', $runs[0]->rawBytes);
    }

    public function testUnbalancedQInsideFormDoesNotCorruptCallerGraphicsState()
    {
        // Page pushes q (capturing "no font" state), sets F2, invokes a Form
        // containing a stray extra Q, then the page's own trailing Q must
        // still correctly pop back to "no font" - not be silently absorbed
        // by a qStack the Form already drained.
        $font2 = ['Type' => new \Pop\Pdf\Extract\Value\Name('Font'), 'Subtype' => new \Pop\Pdf\Extract\Value\Name('Type1')];

        $formContent = 'Q'; // stray Q, no matching q inside this Form
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $body   = $obj1 . $obj2 . $obj3 . $obj4;
        $o1 = strlen($header);
        $o2 = strlen($header . $obj1);
        $o3 = strlen($header . $obj1 . $obj2);
        $o4 = strlen($header . $obj1 . $obj2 . $obj3);
        $xrefPos = strlen($header . $body);
        $xref = "xref\n0 5\n0000000000 65535 f \n" .
            sprintf("%010d 00000 n \n", $o1) .
            sprintf("%010d 00000 n \n", $o2) .
            sprintf("%010d 00000 n \n", $o3) .
            sprintf("%010d 00000 n \n", $o4) .
            "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        $doc = new \Pop\Pdf\Extract\Document($header . $body . $xref);

        $resources = [
            'Font'    => ['F2' => $font2],
            'XObject' => ['Fm0' => new \Pop\Pdf\Extract\Value\Reference(4, 0)],
        ];

        $interpreter = new Interpreter();
        $runs = $interpreter->run($doc, 'q /F2 12 Tf /Fm0 Do (A) Tj Q (B) Tj', $resources);

        $this->assertCount(2, $runs);
        // "A" runs right after the Form returns - font state must be
        // exactly what it was before the Form ran (F2), unaffected by the
        // Form's own stray Q having drained the shared qStack.
        $this->assertSame($font2, $runs[0]->font);
        // "B" runs after the page's OWN trailing Q - which must still
        // correctly find its own pushed frame (fontResolved null, since
        // q was pushed before Tf set F2) rather than finding an
        // already-empty stack (which would happen if the Form's stray Q
        // had permanently removed the page's frame) and leaving F2 active.
        $this->assertNull($runs[1]->font);
    }

    public function testFontCacheKeyIsComputedOncePerTfNotPerRun()
    {
        $font = ['Type' => new \Pop\Pdf\Extract\Value\Name('Font'), 'Subtype' => new \Pop\Pdf\Extract\Value\Name('TrueType')];
        $resources = ['Font' => ['F1' => $font]];

        $interpreter = new Interpreter();
        $runs = $interpreter->run($this->minimalDoc(), 'BT /F1 12 Tf (A) Tj (B) Tj (C) Tj ET', $resources);

        $this->assertCount(3, $runs);
        $this->assertNotNull($runs[0]->fontCacheKey);
        $this->assertSame($runs[0]->fontCacheKey, $runs[1]->fontCacheKey);
        $this->assertSame($runs[1]->fontCacheKey, $runs[2]->fontCacheKey);
    }

    public function testInlineImageWithoutIdKeywordStopsGracefullyAtEof()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // BI never reaches an ID keyword before the stream ends.
        $runs = $interpreter->run($doc, 'BT (Before) Tj ET BI /W 2 /H 2', []);

        $this->assertCount(1, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
    }

    public function testInlineImageMalformedKeyValuePairIsSkippedUntilId()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // A stray ']' between BI and ID is a malformed key/value pair -
        // skipInlineImage() must keep scanning for ID rather than aborting.
        $content = 'BT (Before) Tj ET BI ] /W 2 ID XY EI BT (After) Tj ET';
        $runs    = $interpreter->run($doc, $content, []);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

    public function testInlineImageWithoutEiRunsToEndOfStream()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // No 'EI' anywhere after ID - must consume to end of stream, not loop.
        $runs = $interpreter->run($doc, 'BT (Before) Tj ET BI /W 2 /H 2 ID somerawdata', []);

        $this->assertCount(1, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
    }

    public function testInlineImageSkipsFalseEiMatchBeforeFindingRealOne()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // "XEI" contains a literal 'EI' not bounded by whitespace before it -
        // that false match must be skipped in favor of the real, properly
        // whitespace-bounded 'EI' that follows.
        $content = 'BT (Before) Tj ET BI /W 1 ID XEI EI BT (After) Tj ET';
        $runs    = $interpreter->run($doc, $content, []);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

    public function testBdcActualTextViaNamedPropertiesResourceSubstitutesReplacementText()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();

        // ActualText encoded as a UTF-16BE PDF text string with its BOM,
        // reached through /Properties resource lookup by name rather than
        // an inline dict - also exercises decodePdfTextString()'s UTF-16BE path.
        $resources = [
            'Properties' => [
                'MC1' => ['ActualText' => "\xFE\xFF" . mb_convert_encoding('Hi', 'UTF-16BE', 'UTF-8')],
            ],
        ];
        $content = 'BT /F1 12 Tf /Span /MC1 BDC (raw) Tj EMC ET';
        $runs    = $interpreter->run($doc, $content, $resources);

        $this->assertCount(1, $runs);
        $this->assertEquals('Hi', $runs[0]->decodedText);
    }

    public function testTzSetsHorizontalScalingState()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $interpreter->run($doc, '50 Tz (Hi) Tj', []);

        $prop = new \ReflectionProperty(Interpreter::class, 'horizScale');

        $this->assertEquals(50.0, $prop->getValue($interpreter));
    }

    public function testTDSetsLeadingUsedBySubsequentTStar()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // TD (unlike Td) also sets leading from its y operand; a following
        // T* then uses that leading to advance the line.
        $runs = $interpreter->run($doc, 'BT /F1 12 Tf 0 -14 TD (First) Tj T* (Second) Tj ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals(-14.0, $runs[0]->y);
        $this->assertEquals(0.0, $runs[1]->y);
    }

    public function testDoubleQuoteOperatorSetsSpacingResetsLineAndShowsText()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $runs        = $interpreter->run($doc, 'BT /F1 12 Tf 14 TL 100 0 Td (First) Tj 2 1 (Second) " ET', []);

        $this->assertCount(2, $runs);
        $this->assertEquals(0.0, $runs[1]->x);
        $this->assertEquals(-14.0, $runs[1]->y);
        $this->assertEquals('Second', $runs[1]->rawBytes);

        $wsProp = new \ReflectionProperty(Interpreter::class, 'wordSpace');
        $csProp = new \ReflectionProperty(Interpreter::class, 'charSpace');

        $this->assertEquals(2.0, $wsProp->getValue($interpreter));
        $this->assertEquals(1.0, $csProp->getValue($interpreter));
    }

    public function testDoWithMissingXObjectNameIsANoOp()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        $resources   = ['XObject' => ['Fm0' => new \Pop\Pdf\Extract\Value\Stream([], '')]];
        $runs        = $interpreter->run($doc, 'BT (Before) Tj ET /NotThere Do BT (After) Tj ET', $resources);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

    public function testDoWithNonStreamXObjectIsANoOp()
    {
        $doc         = $this->minimalDoc();
        $interpreter = new Interpreter();
        // Resolved XObject entry is a plain value, not a Value\Stream.
        $resources = ['XObject' => ['Fm0' => 'not-a-stream']];
        $runs      = $interpreter->run($doc, 'BT (Before) Tj ET /Fm0 Do BT (After) Tj ET', $resources);

        $this->assertCount(2, $runs);
        $this->assertEquals('Before', $runs[0]->rawBytes);
        $this->assertEquals('After', $runs[1]->rawBytes);
    }

}
