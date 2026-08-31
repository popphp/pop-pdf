<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Permissions;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    public function testDefaultsAllowEverything()
    {
        $permissions = new Permissions();
        $this->assertTrue($permissions->isPrintingAllowed());
        $this->assertTrue($permissions->isHighResPrintingAllowed());
        $this->assertTrue($permissions->isModifyingAllowed());
        $this->assertTrue($permissions->isCopyingAllowed());
        $this->assertTrue($permissions->isAnnotatingAllowed());
        $this->assertTrue($permissions->isFillingFormsAllowed());
        $this->assertTrue($permissions->isExtractingForAccessibilityAllowed());
        $this->assertTrue($permissions->isAssemblingAllowed());
    }

    public function testAllowMethodsAreFluentAndToggleFlags()
    {
        $permissions = new Permissions();
        $result = $permissions->allowPrinting(false)->allowCopying(false);

        $this->assertSame($permissions, $result);
        $this->assertFalse($permissions->isPrintingAllowed());
        $this->assertFalse($permissions->isCopyingAllowed());
        $this->assertTrue($permissions->isModifyingAllowed());
    }

    public function testToPValueWithEverythingAllowedIsAllOnes()
    {
        // -1 as a 32-bit signed int is 0xFFFFFFFF - every bit set, which is
        // how a fully-permissive P value is represented (PDF's reserved
        // bits must always read as 1, and every permission bit is "1 =
        // allowed" per ISO 32000-1 Table 22).
        $this->assertEquals(-1, (new Permissions())->toPValue());
    }

    public function testToPValueClearsOnlyTheDeniedBits()
    {
        $permissions = (new Permissions())->allowPrinting(false)->allowAssembling(false);
        $p = $permissions->toPValue();

        // Bit 3 (value 4, printing) and bit 11 (value 1024, assembling)
        // must be clear; every other permission bit stays set.
        $this->assertEquals(0, $p & 4);
        $this->assertEquals(0, $p & 1024);
        $this->assertEquals(8, $p & 8); // modifying still allowed
    }
}
