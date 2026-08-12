<?php

namespace Pop\Pdf\Test\Extract\Value;

use Pop\Pdf\Extract\Value\Reference;
use PHPUnit\Framework\TestCase;

class ReferenceTest extends TestCase
{

    public function testConstructor()
    {
        $ref = new Reference(5, 0);
        $this->assertEquals(5, $ref->objNum);
        $this->assertEquals(0, $ref->gen);
    }

}
