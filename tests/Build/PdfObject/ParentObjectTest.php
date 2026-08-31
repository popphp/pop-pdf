<?php

namespace Pop\Pdf\Test\Build\PdfObject;

use Pop\Pdf\Build\PdfObject\ParentObject;
use PHPUnit\Framework\TestCase;

class ParentObjectTest extends TestCase
{

    public function testParseExtractsIndexCountAndArrayOfKids()
    {
        $stream = "2 0 obj\n<</Type/Pages/Count 3/Kids[3 0 R 5 0 R 9 0 R]>>\nendobj\n";

        $parent = ParentObject::parse($stream);

        $this->assertEquals(2, $parent->getIndex());
        $this->assertEquals(3, $parent->getCount());
        $this->assertEquals(['3', '5', '9'], $parent->getKids());

        $result = (string) $parent;
        $this->assertStringContainsString('/Count 3', $result);
        $this->assertStringContainsString('3 0 R 5 0 R 9 0 R', $result);
    }

    public function testParseExtractsSingleNonArrayKid()
    {
        $stream = "2 0 obj\n<</Type/Pages/Count 1/Kids 7 0 R>>\nendobj\n";

        $parent = ParentObject::parse($stream);

        $this->assertEquals(2, $parent->getIndex());
        $this->assertEquals(1, $parent->getCount());
        $this->assertEquals(['7'], $parent->getKids());
    }

    public function testAddAndRemoveKid()
    {
        $parent = new ParentObject(2);
        $parent->addKid(3);
        $parent->addKid(5);

        $this->assertTrue($parent->hasKid(3));
        $this->assertTrue($parent->hasKid(5));

        $parent->removeKid(3);

        $this->assertFalse($parent->hasKid(3));
        $this->assertTrue($parent->hasKid(5));
    }

    public function testCountOverride()
    {
        $parent = new ParentObject(2);
        $parent->addKid(10);
        $parent->setCount(5);

        $this->assertEquals(5, $parent->getCount());
    }

    public function testDeferredKidsDefaultToEmptyAndAreNotPartOfKids()
    {
        $parent = new ParentObject(2);

        $this->assertFalse($parent->hasDeferredKids());
        $this->assertEquals([], $parent->getDeferredKids());
        $this->assertEquals([], $parent->getKids());
    }

    public function testSetDeferredKidsStoresThemSeparatelyFromKids()
    {
        $parent = new ParentObject(2);
        $parent->addKid(3);
        $parent->setDeferredKids([7, 9]);

        $this->assertTrue($parent->hasDeferredKids());
        $this->assertEquals([7, 9], $parent->getDeferredKids());
        // Deferred kids are not real kids until something explicitly adds
        // them via addKid() - setDeferredKids() alone must not affect getKids().
        $this->assertEquals([3], $parent->getKids());
    }

}
