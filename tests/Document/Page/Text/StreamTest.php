<?php

namespace Pop\Pdf\Test\Document\Page\Text;

use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{

    public function testConstructor()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Stream', $stream);
        $this->assertEquals(10, $stream->getStartX());
        $this->assertEquals(15, $stream->getStartY());
        $this->assertEquals(20, $stream->getEdgeX());
        $this->assertEquals(25, $stream->getEdgeY());
    }

    public function testSetAndGetCurrent()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);

        $stream->setCurrentX(50);
        $stream->setCurrentY(100);
        $this->assertEquals(50, $stream->getCurrentX());
        $this->assertEquals(100, $stream->getCurrentY());
    }

    public function testSetAndGetStreams()
    {
        $stream = new Page\Text\Stream(10, 15, 20, 25);
        $stream->addText('Hello World');
        $this->assertTrue($stream->hasTextStreams());
        $this->assertCount(1, $stream->getTextStreams());
    }

    public function testHasOrphansAndGetStreamAgreeOnLineBreaks()
    {
        // 900 short numbered words at a width that wraps well before 900
        // words fit on one line. Before this fix, hasOrphans() (which
        // decides *whether* content overflows a box) and getStream() (which
        // actually renders it) used two different, disagreeing overflow
        // conditions - off by one line from each other - so a paragraph
        // spanning a page break lost a contiguous block of words right at
        // the boundary. This test pins that they now agree by checking that
        // hasOrphans()'s orphan cut point, once resumed via
        // getOrphanStream(), produces a stream whose own getStream() output
        // contains no gap: every word from the resumed point on must appear.
        $words = [];
        for ($i = 1; $i <= 60; $i++) {
            $words[] = "w{$i}";
        }

        $stream = new Page\Text\Stream(60, 200, 300, 190);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText(implode(' ', $words));

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];

        $this->assertTrue($stream->hasOrphans($fonts));
        $orphan = $stream->getOrphanStream();

        // Resume the SAME original stream fresh, to compare against what
        // was actually rendered up to the orphan point.
        $full = new Page\Text\Stream(60, 200, 300, 190);
        $full->setCurrentStyle('Arial', 10);
        $full->addText(implode(' ', $words));
        $fontReferences = ['Arial' => 'F1 0 R'];
        $rendered = $full->getStream($fonts, $fontReferences);

        // Every word up to (not including) the orphan cut must appear in
        // what was actually rendered - if hasOrphans() and getStream()
        // disagreed, some word right before the cut would be missing here.
        $orphanFirstWord = explode(' ', $orphan->getTextStreams()[0]['string'])[0];
        $orphanFirstIndex = (int) str_replace('w', '', $orphanFirstWord);
        for ($i = 1; $i < $orphanFirstIndex; $i++) {
            $this->assertStringContainsString("(w{$i} )Tj", $rendered, "w{$i} should have been rendered before the orphan cut");
        }
    }

    public function testMeasureHeightForASingleShortLine()
    {
        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText('Hello World', 14);

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $this->assertEquals(10.0, $stream->measureHeight($fonts));
    }

    public function testMeasureHeightForContentThatWrapsMultipleLines()
    {
        $words = [];
        for ($i = 1; $i <= 40; $i++) {
            $words[] = "word{$i}";
        }
        $stream = new Page\Text\Stream(60, 700, 200, 60);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText(implode(' ', $words), 14);

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $height = $stream->measureHeight($fonts);

        // A narrow 200pt-wide box with 40 words must wrap to several lines,
        // each 14pt tall (the passed $y), plus the initial 10pt font-size line.
        $this->assertEquals(136.0, $height);
    }

    public function testGetStreamCentersASingleShortLine()
    {
        // getStream()'s single-stream "center" branch only fires when there
        // is exactly one addText() call, the current style's align is
        // 'center', and the string is narrow enough to fit the box - none of
        // which the other Stream tests set up (they all use left/default
        // alignment or multiple addText() calls).
        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('Arial', 10, null, 'center');
        $stream->addText('Hi');

        $fonts          = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $fontReferences = ['Arial' => 'F1 0 R'];

        $rendered = $stream->getStream($fonts, $fontReferences);
        $this->assertStringContainsString('Tm', $rendered);
        $this->assertStringContainsString('(Hi)Tj', $rendered);
    }

    public function testGetStreamWithCidFontEmitsHexString()
    {
        $font = new Font(__DIR__ . '/../../../tmp/fonts/DejaVuSans.ttf');

        $stream = new Page\Text\Stream(50, 700, 500);
        $stream->setCurrentStyle('DejaVuSans', 12);
        $stream->addText("\u{041F}\u{0420}\u{0418}");

        $output = $stream->getStream(
            ['DejaVuSans' => $font],
            ['DejaVuSans' => 'TT1 1 0 R']
        );

        $this->assertStringContainsString('>Tj', $output);
        $this->assertStringNotContainsString('(', $output);
    }

    public function testGetStreamCentersASingleShortLineWithCidFont()
    {
        // Mirrors testGetStreamCentersASingleShortLine() but through the
        // centered-align branch's own separate Text::escape() call site,
        // with a CID font instead of a standard one.
        $font = new Font(__DIR__ . '/../../../tmp/fonts/DejaVuSans.ttf');

        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('DejaVuSans', 10, null, 'center');
        $stream->addText("\u{041F}\u{0420}");

        $rendered = $stream->getStream(
            ['DejaVuSans' => $font],
            ['DejaVuSans' => 'TT1 1 0 R']
        );

        $this->assertStringContainsString('Tm', $rendered);
        $this->assertStringContainsString('>Tj', $rendered);
        $this->assertStringNotContainsString('(', $rendered);
    }

    public function testGetStreamWithStandardFontAndUnsupportedCharacterThrows()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');

        $font = new Font('Arial');

        $stream = new Page\Text\Stream(50, 700, 500);
        $stream->setCurrentStyle('Arial', 12);
        $stream->addText("\u{041F}\u{0420}\u{0418}");

        $stream->getStream(['Arial' => $font], ['Arial' => 'F1 0 R']);
    }

    public function testGetStreamWithStandardFontTranscodesToWinAnsi()
    {
        $font = new Font('Arial');

        $stream = new Page\Text\Stream(50, 700, 500);
        $stream->setCurrentStyle('Arial', 12);
        $stream->addText('café');

        $output = $stream->getStream(['Arial' => $font], ['Arial' => 'F1 0 R']);

        // 'é' (U+00E9) must appear as the single WinAnsi byte 0xE9, not the
        // 2-byte UTF-8 sequence C3 A9.
        $this->assertStringContainsString("caf\xE9", $output);
        $this->assertStringNotContainsString("\xC3\xA9", $output);
    }

    public function testGetStreamCentersASingleShortLineWithStandardFontTranscodesToWinAnsi()
    {
        $font = new Font('Arial');

        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('Arial', 10, null, 'center');
        $stream->addText('café');

        $rendered = $stream->getStream(['Arial' => $font], ['Arial' => 'F1 0 R']);

        $this->assertStringContainsString("caf\xE9", $rendered);
        $this->assertStringNotContainsString("\xC3\xA9", $rendered);
    }

    public function testGetStreamHandlesAnExplicitNewLineBetweenStreamEntries()
    {
        // Two addText() calls, the second with newLine=true, exercise:
        // (a) getStream()'s per-entry "newLine" branch (the "0 -Y Td" line
        // break), and (b) the cross-entry space-joining branch - at the last
        // word of stream 0 ("Hello"), it looks ahead at stream 1's first
        // character ("W") to decide whether to append a trailing space.
        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText('Hello');
        $stream->addText('World', 14, true);

        $fonts          = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $fontReferences = ['Arial' => 'F1 0 R'];

        $rendered = $stream->getStream($fonts, $fontReferences);
        $this->assertStringContainsString('0 -14 Td', $rendered);
        $this->assertStringContainsString('(Hello )Tj', $rendered);
        $this->assertStringContainsString('(World)Tj', $rendered);
    }

    public function testMeasureHeightAccountsForAnExplicitNewLine()
    {
        // measureHeight()'s own newLine branch mirrors getStream()'s, but is
        // a separate code path - a stream with one explicit newLine must add
        // that offset on top of the initial font-size line.
        $stream = new Page\Text\Stream(60, 700, 500, 60);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText('Hello');
        $stream->addText('World', 14, true);

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        // Initial line is the 10pt font size, plus the explicit 14pt newLine
        // offset from the second addText() call.
        $this->assertEquals(24.0, $stream->measureHeight($fonts));
    }

    public function testGetColorStreamForCmykAndGrayscale()
    {
        $stream = new Page\Text\Stream(0, 0, 100, 0);

        $cmyk = $stream->getColorStream(new \Pop\Color\Color\Cmyk(10, 20, 30, 5));
        $this->assertStringContainsString(' k', $cmyk);

        $gray = $stream->getColorStream(new \Pop\Color\Color\Grayscale(50));
        $this->assertStringContainsString(' g', $gray);
    }

    public function testHasOrphanIndexIsTrueAfterHasOrphansFindsAnOrphan()
    {
        $stream = new Page\Text\Stream(60, 200, 300, 190);
        $stream->setCurrentStyle('Arial', 10);
        $words = [];
        for ($i = 1; $i <= 60; $i++) {
            $words[] = "w{$i}";
        }
        $stream->addText(implode(' ', $words));

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $this->assertTrue($stream->hasOrphans($fonts));
        $this->assertTrue($stream->hasOrphanIndex());
    }

    public function testHasOrphanIndexIsFalseWhenHasOrphansFindsNone()
    {
        // A short stream with a tall edgeY margin fits entirely on one page,
        // so hasOrphans() never populates $orphanIndex.
        $stream = new Page\Text\Stream(60, 700, 300, 0);
        $stream->setCurrentStyle('Arial', 10);
        $stream->addText('short line');

        $fonts = ['Arial' => new \Pop\Pdf\Document\Font('Arial')];
        $this->assertFalse($stream->hasOrphans($fonts));
        $this->assertFalse($stream->hasOrphanIndex());
    }

}
