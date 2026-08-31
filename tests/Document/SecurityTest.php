<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Security;
use Pop\Pdf\Document\Permissions;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function testDefaultsToAes256WithNoPasswords()
    {
        $security = new Security();
        $this->assertEquals(Security::AES_256, $security->getAlgorithm());
        $this->assertFalse($security->hasUserPassword());
        $this->assertFalse($security->hasOwnerPassword());
        $this->assertNull($security->getUserPassword());
        $this->assertNull($security->getOwnerPassword());
    }

    public function testConstructorSetsPasswordsAndAlgorithm()
    {
        $security = new Security('open-me', 'admin123', null, Security::AES_128);
        $this->assertEquals('open-me', $security->getUserPassword());
        $this->assertEquals('admin123', $security->getOwnerPassword());
        $this->assertEquals(Security::AES_128, $security->getAlgorithm());
        $this->assertTrue($security->hasUserPassword());
        $this->assertTrue($security->hasOwnerPassword());
    }

    public function testGetPermissionsReturnsADefaultInstanceWhenNoneSet()
    {
        $security = new Security();
        $this->assertInstanceOf(Permissions::class, $security->getPermissions());
    }

    public function testSetPermissionsIsFluentAndStored()
    {
        $security    = new Security();
        $permissions = new Permissions();
        $result      = $security->setPermissions($permissions);

        $this->assertSame($security, $result);
        $this->assertSame($permissions, $security->getPermissions());
    }

    public function testEffectiveOwnerPasswordReturnsExplicitOwnerPasswordWhenSet()
    {
        $security = new Security('open-me', 'admin123');
        $this->assertEquals('admin123', $security->getEffectiveOwnerPassword());
    }

    public function testEffectiveOwnerPasswordGeneratesAndCachesARandomOneWhenNoneSet()
    {
        // Restricting a permission with no owner password is exactly the
        // scenario the auto-generated owner password protects: without
        // it, anyone could remove the restriction with a blank owner
        // password.
        $security = new Security('open-me');
        $security->getPermissions()->allowPrinting(false);

        $first  = $security->getEffectiveOwnerPassword();
        $second = $security->getEffectiveOwnerPassword();

        $this->assertNotEmpty($first);
        $this->assertEquals($first, $second);
        $this->assertFalse($security->hasOwnerPassword());
    }
}
