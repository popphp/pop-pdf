<?php

namespace Pop\Pdf\Test\Build\Font;

use Pop\Pdf\Build\Font\TrueType;
use PHPUnit\Framework\TestCase;

class TrueTypeTest extends TestCase
{

    /**
     * Take a real TTF fixture and patch specific bytes in its 'post' table
     * to force a non-zero italic angle and a fixed-pitch flag, then parse
     * it from an in-memory stream. This exercises the branches in
     * TrueType::parseCommonTables() that only trigger for italic/fixed-pitch
     * fonts, which none of the checked-in fixtures happen to be.
     */
    public function testPostTableItalicAndFixedPitchFlags()
    {
        $data = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $font = new TrueType(__DIR__ . '/../../tmp/fonts/times.ttf');
        $postOffset = $font->tableInfo['post']->offset;

        // italicAngle: signed Fixed 16.16 at postOffset+4, set to -10.0
        $italicBytes = pack('N', (int)(-10 * 65536) & 0xFFFFFFFF);
        $patched     = substr_replace($data, $italicBytes, $postOffset + 4, 4);

        // isFixedPitch: 2-byte field at postOffset+12, set to non-zero
        $fixedBytes = pack('n', 1);
        $patched    = substr_replace($patched, $fixedBytes, $postOffset + 12, 2);

        $patchedFont = new TrueType(null, $patched);

        $this->assertEquals(-10, $patchedFont->italicAngle);
        $this->assertTrue($patchedFont->flags['isItalic']);
        $this->assertTrue($patchedFont->flags['isFixedPitch']);
    }

    /**
     * bos.otf carries a real OS/2 table. Constructing it via the base
     * TrueType class directly (instead of TrueType\OpenType, which
     * overrides parseRequiredTables()) exercises TrueType's own OS/2
     * branch in parseRequiredTables().
     */
    public function testParseRequiredTablesOs2Branch()
    {
        $data = file_get_contents(__DIR__ . '/../../tmp/fonts/bos.otf');
        $font = new TrueType\OpenType(__DIR__ . '/../../tmp/fonts/bos.otf');
        $os2Offset = $font->tableInfo['OS/2']->offset;

        // family_class of 1 (Oldstyle Serif) at os2Offset+30 flags the font as serif
        $patched = substr_replace($data, pack('n', 1 << 8), $os2Offset + 30, 2);

        $patchedFont = new TrueType(null, $patched);

        $this->assertArrayHasKey('OS/2', $patchedFont->tables);
        $this->assertTrue($patchedFont->flags['isSerif']);
        // bos.otf's real fsType (8: Editable embedding) permits embedding.
        $this->assertTrue($patchedFont->embeddable);
    }

    /**
     * parseTtfTable() reads the sfnt table directory's first entry (bytes
     * 12-27) separately from the rest, then loops over the remaining
     * entries. An off-by-one there used to both drop that first entry from
     * tableInfo and read one entry past the real end of the directory. For
     * every fixture here the first entry (alphabetically first table tag)
     * is 'OS/2' - assert it's present, at the real byte offset from a raw
     * sfnt dump, and that no bogus/mangled key leaked in from reading past
     * the directory's end.
     */
    public function testParseTtfTableIncludesFirstDirectoryEntryAndStopsAtTheRealEnd()
    {
        $data   = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $header = unpack('nmajorVersion/nminorVersion/nnumberOfTables', substr($data, 0, 6));

        $expectedTags = [];
        $offset       = 12;
        for ($i = 0; $i < $header['numberOfTables']; $i++) {
            $tag            = trim(substr($data, $offset, 4));
            $entry          = unpack('Nchecksum/Noffset/Nlength', substr($data, $offset + 4, 12));
            $expectedTags[$tag] = $entry['offset'];
            $offset        += 16;
        }

        $font = new TrueType(__DIR__ . '/../../tmp/fonts/times.ttf');

        $this->assertArrayHasKey('OS/2', $font->tableInfo);
        $this->assertEquals($expectedTags, array_map(
            fn ($info) => $info->offset,
            (array) $font->tableInfo
        ));
    }

    /**
     * With the off-by-one fixed, OS/2 is now actually read for a plain
     * TrueType font - patch fsType to 2 (Restricted License embedding) and
     * confirm embeddable flips to false, not just that it stays a bool.
     */
    public function testEmbeddableIsFalseForRestrictedLicenseFsType()
    {
        $data      = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $font      = new TrueType(__DIR__ . '/../../tmp/fonts/times.ttf');
        $os2Offset = $font->tableInfo['OS/2']->offset;

        // fsType is a 2-byte field at OS/2 offset + 8.
        $patched = substr_replace($data, pack('n', 2), $os2Offset + 8, 2);

        $patchedFont = new TrueType(null, $patched);

        $this->assertFalse($patchedFont->embeddable);
    }

    /**
     * Same as above, but through TrueType\OpenType - whose own
     * parseRequiredTables() override previously never assigned
     * $this->properties['embeddable'] from OS/2 at all.
     */
    public function testOpenTypeEmbeddableIsFalseForRestrictedLicenseFsType()
    {
        $data      = file_get_contents(__DIR__ . '/../../tmp/fonts/bos.otf');
        $font      = new TrueType\OpenType(__DIR__ . '/../../tmp/fonts/bos.otf');
        $os2Offset = $font->tableInfo['OS/2']->offset;

        $patched = substr_replace($data, pack('n', 2), $os2Offset + 8, 2);

        $patchedFont = new TrueType\OpenType(null, $patched);

        $this->assertFalse($patchedFont->embeddable);
    }

    public function testOpenTypeEmbeddableIsTrueForUnrestrictedFsType()
    {
        $font = new TrueType\OpenType(__DIR__ . '/../../tmp/fonts/bos.otf');

        $this->assertTrue($font->embeddable);
    }

    /**
     * DejaVu Sans is this plan's fixture for exercising Cyrillic-capable
     * embedded fonts - confirm it parses cleanly and its cmap actually
     * covers a Cyrillic codepoint (U+041F, Cyrillic capital PE) before any
     * CID-emission code relies on it.
     */
    public function testDejaVuSansParsesAndCoversCyrillic()
    {
        $font = new TrueType(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf');

        $this->assertEquals('DejaVuSans', $font->tables['name']->postscriptName);
        $this->assertEquals(948, $font['cmap']['glyphNumbers'][0x041F]);
    }

    /**
     * parseCommonTables() previously trusted cmap subTables[0] blindly - if
     * a font's Microsoft Unicode subtable isn't first in the directory,
     * glyph lookups silently used whatever's at index 0 instead.
     *
     * times.ttf's cmap directory has exactly two entries, in this order:
     *   index 0 - platform 1 / encoding 0 (Macintosh Roman), format 0, a
     *             flat 256-byte table whose parsed result is a plain
     *             code => GID array with no 'glyphNumbers' key at all;
     *   index 1 - platform 3 / encoding 1 (Microsoft Unicode BMP), format 4,
     *             the segment-to-delta table that does carry 'glyphNumbers'.
     *
     * So the unpatched fixture already exercises the fix: the usable
     * subtable is NOT at index 0. The patched copy then swaps the two
     * 8-byte directory entries wholesale (IDs and offsets together), which
     * really does move the Microsoft Unicode subtable to index 0, and the
     * selected map must come out identical either way.
     */
    public function testCmapSelectsRecognizedSubtableRegardlessOfDirectoryOrder()
    {
        $data       = file_get_contents(__DIR__ . '/../../tmp/fonts/times.ttf');
        $font       = new TrueType(__DIR__ . '/../../tmp/fonts/times.ttf');
        $cmapOffset = $font->tableInfo['cmap']->offset;

        $entry0Pos = $cmapOffset + 4;
        $entry1Pos = $cmapOffset + 4 + 8;

        // As shipped: the Mac Roman format-0 subtable sits at index 0 and
        // exposes no 'glyphNumbers' - selecting it would break glyph lookup.
        $this->assertArrayNotHasKey(
            'glyphNumbers', (array)$font->tables['cmap']['subTables'][0]['parsed']
        );
        $this->assertEquals(
            $font->tables['cmap']['subTables'][1]['parsed']['glyphNumbers'],
            $font['cmap']['glyphNumbers']
        );

        // Swap the two directory entries whole, so the Microsoft Unicode
        // subtable is now the one at index 0.
        $entry0  = substr($data, $entry0Pos, 8);
        $entry1  = substr($data, $entry1Pos, 8);
        $patched = substr_replace($data, $entry1, $entry0Pos, 8);
        $patched = substr_replace($patched, $entry0, $entry1Pos, 8);

        $patchedFont = new TrueType(null, $patched);

        $this->assertArrayNotHasKey(
            'glyphNumbers', (array)$patchedFont->tables['cmap']['subTables'][1]['parsed']
        );
        $this->assertEquals(
            $patchedFont->tables['cmap']['subTables'][0]['parsed']['glyphNumbers'],
            $patchedFont['cmap']['glyphNumbers']
        );

        // Directory order changed, selected map did not.
        $this->assertEquals($font['cmap']['glyphNumbers'], $patchedFont['cmap']['glyphNumbers']);
    }

}
