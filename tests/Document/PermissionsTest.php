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

    public function testToPValueWithEverythingAllowedIsAllOnesExceptTheReservedLowBits()
    {
        // Every permission bit is "1 = allowed" and every unassigned bit
        // from 7 upward must read as 1, so a fully-permissive P value is
        // 0xFFFFFFFF with only bits 1 and 2 (combined value 3) cleared -
        // ISO 32000-1 Table 22 reserves those two as "must be 0".
        $this->assertEquals(-4, (new Permissions())->toPValue());
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

    public function testToPValueAlwaysLeavesReservedBits1And2Clear()
    {
        // ISO 32000-1 Table 22: bits 1 and 2 are reserved and must be 0,
        // no matter which permissions are allowed or denied.
        $everything = new Permissions();
        $this->assertEquals(0, $everything->toPValue() & 3);

        $nothing = (new Permissions())
            ->allowPrinting(false)
            ->allowHighResPrinting(false)
            ->allowModifying(false)
            ->allowCopying(false)
            ->allowAnnotating(false)
            ->allowFillingForms(false)
            ->allowExtractingForAccessibility(false)
            ->allowAssembling(false);
        $this->assertEquals(0, $nothing->toPValue() & 3);

        $mixed = (new Permissions())->allowPrinting(false)->allowCopying(false);
        $this->assertEquals(0, $mixed->toPValue() & 3);
    }
}
