<?php

namespace Pop\Pdf\Test\Build\Html\Table;

use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Table\Cell;
use PHPUnit\Framework\TestCase;

class CellTest extends TestCase
{

    public function testBasicProperties()
    {
        $node = new Child('td');
        $cell = new Cell($node, 2, 3, 4, 5, true);

        $this->assertSame($node, $cell->getNode());
        $this->assertEquals(2, $cell->getRow());
        $this->assertEquals(3, $cell->getCol());
        $this->assertEquals(4, $cell->getRowSpan());
        $this->assertEquals(5, $cell->getColSpan());
        $this->assertTrue($cell->isHeader());
    }

    public function testDefaultsToOneByOneNonHeader()
    {
        $node = new Child('td');
        $cell = new Cell($node, 0, 0);

        $this->assertEquals(1, $cell->getRowSpan());
        $this->assertEquals(1, $cell->getColSpan());
        $this->assertFalse($cell->isHeader());
    }

    public function testSpansAreClampedToAtLeastOne()
    {
        $node = new Child('td');
        $cell = new Cell($node, 0, 0, 0, -1);

        $this->assertEquals(1, $cell->getRowSpan());
        $this->assertEquals(1, $cell->getColSpan());
    }

}
