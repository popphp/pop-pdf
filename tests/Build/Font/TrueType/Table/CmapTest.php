<?php

namespace Pop\Pdf\Test\Build\Font\TrueType\Table;

use Pop\Pdf\Build\Font\TrueType;
use PHPUnit\Framework\TestCase;

class CmapTest extends TestCase
{

    /**
     * times.ttf's cmap has two sub-tables: 'Mac Roman' (platform 1, encoding 0)
     * and 'Microsoft Unicode' (platform 3, encoding 1). Relabeling the second
     * sub-table's platform/encoding id pair lets us hit the other named
     * branches in Cmap::parseSubTables() that no checked-in fixture reaches.
     */
    protected function patchSecondSubTablePlatformEncoding(int $platformId, int $encodingId): TrueType
    {
        $data = file_get_contents(__DIR__ . '/../../../../tmp/fonts/times.ttf');
        $font = new TrueType(__DIR__ . '/../../../../tmp/fonts/times.ttf');
        $cmapOffset = $font->tableInfo['cmap']->offset;

        // Sub-table directory entries start at cmapOffset+4, 8 bytes each:
        // platformId(2) + encodingId(2) + offset(4). The second entry is at +8.
        $entryPos = $cmapOffset + 4 + 8;
        $patched  = substr_replace($data, pack('n2', $platformId, $encodingId), $entryPos, 4);

        return new TrueType(null, $patched);
    }

    public function testUnicode20Encoding()
    {
        // platformId 0, encodingId 0 => 'Unicode 2.0'
        $font = $this->patchSecondSubTablePlatformEncoding(0, 0);
        $this->assertEquals('Unicode 2.0', $font->tables['cmap']['subTables'][1]->encoding);
    }

    public function testUnknownEncoding()
    {
        // A platform/encoding combination not matched by any named case
        $font = $this->patchSecondSubTablePlatformEncoding(2, 5);
        $this->assertEquals('Unknown', $font->tables['cmap']['subTables'][1]->encoding);
    }

}
