<?php

namespace Pop\Pdf\Test\Extract\Value;

use Pop\Pdf\Extract\Value\Name;
use PHPUnit\Framework\TestCase;

class NameTest extends TestCase
{

    public function testConstructorAndToString()
    {
        $name = new Name('Type');
        $this->assertEquals('Type', $name->name);
        $this->assertEquals('Type', (string) $name);
    }

}
