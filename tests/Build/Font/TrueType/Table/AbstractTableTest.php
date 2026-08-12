<?php

namespace Pop\Pdf\Test\Build\Font\TrueType\Table;

use Pop\Pdf\Build\Font\TrueType;
use PHPUnit\Framework\TestCase;

class AbstractTableTest extends TestCase
{

    /**
     * Any concrete table (e.g. 'head', parsed from a real TTF) is enough
     * to exercise AbstractTable's ArrayAccess/magic-method plumbing.
     */
    protected function getTable(): TrueType\Table\Head
    {
        $font = new TrueType(__DIR__ . '/../../../../tmp/fonts/times.ttf');
        return $font->tables['head'];
    }

    public function testMagicSetGetIsset()
    {
        $table = $this->getTable();
        $table->customProp = 'hello';
        $this->assertTrue(isset($table->customProp));
        $this->assertEquals('hello', $table->customProp);
    }

    public function testMagicUnset()
    {
        $table = $this->getTable();
        $table->customProp = 'hello';
        $this->assertTrue(isset($table->customProp));
        unset($table->customProp);
        $this->assertFalse(isset($table->customProp));
    }

    public function testOffsetUnsetWhenSet()
    {
        $table = $this->getTable();
        $table['customOffset'] = 'value';
        $this->assertTrue(isset($table['customOffset']));
        unset($table['customOffset']);
        $this->assertFalse(isset($table['customOffset']));
    }

    public function testOffsetUnsetWhenNotSetIsNoOp()
    {
        $table = $this->getTable();
        $this->assertFalse(isset($table['neverSet']));
        // Should not throw or error even though the key was never set
        unset($table['neverSet']);
        $this->assertFalse(isset($table['neverSet']));
    }

}
