<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\ObjectStream;
use Pop\Pdf\Extract\Value\Stream;
use PHPUnit\Framework\TestCase;

class ObjectStreamTest extends TestCase
{

    public function testExpandObjectStream()
    {
        // Two objects: obj 5 = << /Type /Catalog >>, obj 6 = (Hello)
        $obj5 = '<< /Type /Catalog >>';
        $obj6 = '(Hello)';

        $header = "5 0 6 " . strlen($obj5) . " ";
        $body   = $obj5 . $obj6;

        $dict = ['N' => 2, 'First' => strlen($header)];
        $stream = new Stream($dict, $header . $body);

        $objects = ObjectStream::expand($stream);

        $this->assertCount(2, $objects);
        $this->assertIsArray($objects[5]);
        $this->assertInstanceOf(\Pop\Pdf\Extract\Value\Name::class, $objects[5]['Type']);
        $this->assertEquals('Catalog', $objects[5]['Type']->name);
        $this->assertEquals('Hello', $objects[6]);
    }

    public function testExpandTerminatesWhenNFarExceedsHeaderData()
    {
        // Only 2 real pairs exist in the header, but /N claims far more -
        // expand() must stop at the real data instead of trusting /N as
        // an unconditional loop bound.
        $obj5 = '<< /Type /Catalog >>';
        $obj6 = '(Hello)';

        $header = "5 0 6 " . strlen($obj5) . " ";
        $body   = $obj5 . $obj6;

        $dict   = ['N' => 2000000000, 'First' => strlen($header)];
        $stream = new Stream($dict, $header . $body);

        $objects = ObjectStream::expand($stream);

        $this->assertCount(2, $objects);
        $this->assertIsArray($objects[5]);
        $this->assertEquals('Hello', $objects[6]);
    }

    public function testExpandAdversarialHugeNRegression()
    {
        // Regression for the exact adversarial case found during design: a
        // small stream declaring /N 2000000000 must complete quickly with
        // bounded memory rather than looping until memory is exhausted.
        $obj5 = '<< /Type /Catalog >>';

        $header = "5 0 ";
        $body   = $obj5;

        $dict   = ['N' => 2000000000, 'First' => strlen($header)];
        $stream = new Stream($dict, $header . $body);

        $start   = microtime(true);
        $objects = ObjectStream::expand($stream);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(10.0, $elapsed);
        $this->assertCount(1, $objects);
        $this->assertIsArray($objects[5]);
    }

    public function testExpandWithNonNumericFirstDegradesGracefully()
    {
        // /First resolving to a Value object (not a raw int/float) must not
        // throw a PHP-level warning/TypeError when cast - it should degrade
        // to an empty object map, the same as any other unresolvable stream.
        $dict   = ['N' => 2, 'First' => new \Pop\Pdf\Extract\Value\Reference(9, 0)];
        $stream = new Stream($dict, '5 0 6 20 << /Type /Catalog >>(Hello)');

        $objects = ObjectStream::expand($stream);

        $this->assertCount(0, $objects);
    }

    public function testExpandWithNonNumericNDegradesGracefully()
    {
        $dict   = ['N' => new \Pop\Pdf\Extract\Value\Name('Foo'), 'First' => 9];
        $stream = new Stream($dict, '5 0 6 20 << /Type /Catalog >>(Hello)');

        $objects = ObjectStream::expand($stream);

        $this->assertCount(0, $objects);
    }

    public function testExpandWithNegativeFirstDegradesGracefully()
    {
        $dict   = ['N' => 1, 'First' => -5];
        $stream = new Stream($dict, '5 0 ');

        $objects = ObjectStream::expand($stream);

        $this->assertCount(0, $objects);
    }

    public function testExpandWithOddTrailingHeaderTokenStopsCleanly()
    {
        // The header holds a complete pair (5, 0) followed by a lone
        // trailing objNum token (6) with no matching offset - the second
        // eof check (after reading objNum but before offset) must break
        // out rather than reading past the header.
        $header = '5 0 6 ';
        $body   = '(Hello)';
        $dict   = ['N' => 2, 'First' => strlen($header)];
        $stream = new Stream($dict, $header . $body);

        $objects = ObjectStream::expand($stream);

        $this->assertCount(1, $objects);
        $this->assertArrayHasKey(5, $objects);
    }

    public function testExpandWithFirstExceedingRawLengthTerminates()
    {
        // /First far larger than the actual raw stream must not cause
        // substr() or the tokenizer to misbehave - it should just find no
        // real pairs in what little header data exists.
        $dict   = ['N' => 1, 'First' => 999999];
        $stream = new Stream($dict, '% comment');

        $objects = ObjectStream::expand($stream);

        $this->assertCount(0, $objects);
    }

}
