<?php

namespace Pop\Pdf\Test\Build\Font\TrueType\Table;

use Pop\Pdf\Build\Font\TrueType;
use PHPUnit\Framework\TestCase;

class Os2Test extends TestCase
{

    /**
     * bos.otf's OS/2 table has family_class 0, which trips none of the
     * named branches in Os2's constructor. Patching the 2-byte family_class
     * field (offset 30 within the OS/2 table) to specific IBM font-class
     * values exercises each one. Constructed via the base TrueType class
     * (not TrueType\OpenType) so Os2 is built from TrueType::parseRequiredTables().
     */
    protected function patchedFont(int $familyClass): TrueType
    {
        $data = file_get_contents(__DIR__ . '/../../../../tmp/fonts/bos.otf');
        $font = new TrueType\OpenType(__DIR__ . '/../../../../tmp/fonts/bos.otf');
        $os2Offset = $font->tableInfo['OS/2']->offset;

        $patched = substr_replace($data, pack('n', ($familyClass << 8) & 0xFFFF), $os2Offset + 30, 2);

        return new TrueType(null, $patched);
    }

    public function testSerifFamilyClass()
    {
        // Classes 1-5 and 7 (Oldstyle/Transitional/Modern/Clarendon/Freeform/Miscellaneous Serif)
        $font = $this->patchedFont(1);
        $this->assertTrue($font->tables['OS/2']['flags']['isSerif']);
    }

    public function testSansSerifFamilyClassIsExplicitlyNotSerif()
    {
        // Class 8 (Sans Serif) explicitly sets isSerif to false
        $font = $this->patchedFont(8);
        $this->assertFalse($font->tables['OS/2']['flags']['isSerif']);
    }

    public function testScriptFamilyClass()
    {
        // Class 10 (Script)
        $font = $this->patchedFont(10);
        $this->assertTrue($font->tables['OS/2']['flags']['isScript']);
    }

    public function testSymbolicFamilyClass()
    {
        // Class 12 (Symbolic)
        $font = $this->patchedFont(12);
        $this->assertTrue($font->tables['OS/2']['flags']['isSymbolic']);
        $this->assertFalse($font->tables['OS/2']['flags']['isNonSymbolic']);
    }

    public function testUnicodeRange1OverridesToNonSymbolic()
    {
        $data = file_get_contents(__DIR__ . '/../../../../tmp/fonts/bos.otf');
        $font = new TrueType\OpenType(__DIR__ . '/../../../../tmp/fonts/bos.otf');
        $os2Offset = $font->tableInfo['OS/2']->offset;

        // Force family_class to 12 (symbolic) first...
        $patched = substr_replace($data, pack('n', 12 << 8), $os2Offset + 30, 2);
        // ...then set unicodeRange1=1, unicodeRange2-4=0, which overrides back to non-symbolic
        $patched = substr_replace($patched, pack('N4', 1, 0, 0, 0), $os2Offset + 33, 16);

        $patchedFont = new TrueType(null, $patched);

        $this->assertFalse($patchedFont->tables['OS/2']['flags']['isSymbolic']);
        $this->assertTrue($patchedFont->tables['OS/2']['flags']['isNonSymbolic']);
    }

}
