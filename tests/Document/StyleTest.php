<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Style;
use PHPUnit\Framework\TestCase;

class StyleTest extends TestCase
{

    public function testGettersAndSetters()
    {
        $style = Style::create('bold', 'Arial,Bold', 12);
        $this->assertTrue($style->hasName());
        $this->assertTrue($style->hasFont());
        $this->assertTrue($style->hasSize());
        $this->assertEquals('bold', $style->getName());
        $this->assertEquals('Arial,Bold', $style->getFont());
        $this->assertEquals(12, $style->getSize());
    }

}
