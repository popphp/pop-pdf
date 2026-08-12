<?php

namespace Pop\Pdf\Test\Build\Import;

use Pop\Pdf\Build\Import\ReferenceRewriter;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ReferenceRewriterTest extends TestCase
{

    public function testRewritesTopLevelReference()
    {
        $result = ReferenceRewriter::rewrite(new Value\Reference(5, 0), [5 => 105]);

        $this->assertInstanceOf(Value\Reference::class, $result);
        $this->assertEquals(105, $result->objNum);
        $this->assertEquals(0, $result->gen);
    }

    public function testUnmappedReferencePassesThroughUnchanged()
    {
        $result = ReferenceRewriter::rewrite(new Value\Reference(99, 0), [5 => 105]);

        $this->assertEquals(99, $result->objNum);
    }

    public function testRewritesReferencesNestedInDictAndArray()
    {
        $value = [
            'Kids'  => [new Value\Reference(3, 0), new Value\Reference(4, 0)],
            'Count' => 2,
        ];

        $result = ReferenceRewriter::rewrite($value, [3 => 103, 4 => 104]);

        $this->assertEquals(103, $result['Kids'][0]->objNum);
        $this->assertEquals(104, $result['Kids'][1]->objNum);
        $this->assertEquals(2, $result['Count']);
    }

    public function testRewritesSelfReferentialArrayWithoutInfiniteLoop()
    {
        // Not a real PHP reference cycle (that's impossible to build from
        // plain scalars/arrays) - just confirms an array containing the same
        // Reference object twice rewrites both occurrences independently.
        $ref   = new Value\Reference(3, 0);
        $value = [$ref, $ref];

        $result = ReferenceRewriter::rewrite($value, [3 => 103]);

        $this->assertEquals(103, $result[0]->objNum);
        $this->assertEquals(103, $result[1]->objNum);
    }

    public function testRewritesReferencesInsideStreamDictPreservingRawBytes()
    {
        $stream = new Value\Stream(['Length' => new Value\Reference(3, 0)], "raw bytes\x00\xff");

        $result = ReferenceRewriter::rewrite($stream, [3 => 103]);

        $this->assertInstanceOf(Value\Stream::class, $result);
        $this->assertEquals(103, $result->dict['Length']->objNum);
        $this->assertEquals("raw bytes\x00\xff", $result->raw);
    }

    public function testScalarsAndNamesPassThroughUnchanged()
    {
        $this->assertEquals(5, ReferenceRewriter::rewrite(5, [5 => 105]));
        $this->assertEquals('hello', ReferenceRewriter::rewrite('hello', []));

        $name = new Value\Name('Catalog');
        $this->assertSame($name, ReferenceRewriter::rewrite($name, []));
    }

}
