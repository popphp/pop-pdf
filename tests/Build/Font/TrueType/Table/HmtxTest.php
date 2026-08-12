<?php

namespace Pop\Pdf\Test\Build\Font\TrueType\Table;

use Pop\Pdf\Build\Font\TrueType;
use PHPUnit\Framework\TestCase;

class HmtxTest extends TestCase
{

    /**
     * In times.ttf, numberOfHMetrics == numberOfGlyphs, so Hmtx's padding
     * loop (which repeats the final glyph width for any glyph beyond the
     * declared metrics) never runs. Patching hhea's numberOfHMetrics field
     * down forces numberOfGlyphs > numberOfHMetrics, which exercises it.
     */
    public function testGlyphWidthsArePaddedWhenFewerMetricsThanGlyphs()
    {
        $data = file_get_contents(__DIR__ . '/../../../../tmp/fonts/times.ttf');
        $font = new TrueType(__DIR__ . '/../../../../tmp/fonts/times.ttf');

        $originalHMetrics = $font->numberOfHMetrics;
        $numberOfGlyphs    = $font->numberOfGlyphs;
        $this->assertSame($originalHMetrics, $numberOfGlyphs, 'fixture assumption changed');

        $hheaOffset   = $font->tableInfo['hhea']->offset;
        $reducedCount = $originalHMetrics - 9;
        $patched      = substr_replace($data, pack('n', $reducedCount), $hheaOffset + 34, 2);

        $patchedFont = new TrueType(null, $patched);

        $this->assertEquals($reducedCount, $patchedFont->numberOfHMetrics);
        $glyphWidths = $patchedFont->tables['hmtx']['glyphWidths'];
        $this->assertCount($numberOfGlyphs, $glyphWidths);

        // The padded entries repeat the last real metric's width
        $lastRealWidth = $glyphWidths[$reducedCount - 1];
        for ($i = $reducedCount; $i < $numberOfGlyphs; $i++) {
            $this->assertEquals($lastRealWidth, $glyphWidths[$i]);
        }
    }

}
