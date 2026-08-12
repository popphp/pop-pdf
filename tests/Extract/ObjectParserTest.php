<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ObjectParserTest extends TestCase
{

    protected function parse(string $data): mixed
    {
        $tokenizer = new Tokenizer($data);
        $parser    = new ObjectParser($tokenizer);
        return $parser->parseValue();
    }

    public function testPlainNumber()
    {
        $this->assertEquals(5, $this->parse('5'));
    }

    public function testReference()
    {
        $value = $this->parse('12 0 R');
        $this->assertInstanceOf(Value\Reference::class, $value);
        $this->assertEquals(12, $value->objNum);
        $this->assertEquals(0, $value->gen);
    }

    public function testTwoNumbersNotAReference()
    {
        $tokenizer = new Tokenizer('12 0 /Foo');
        $parser    = new ObjectParser($tokenizer);
        $this->assertEquals(12, $parser->parseValue());
        $this->assertEquals(0, $parser->parseValue());
    }

    public function testArray()
    {
        $value = $this->parse('[1 2 /Foo (bar)]');
        $this->assertIsArray($value);
        $this->assertEquals(1, $value[0]);
        $this->assertEquals(2, $value[1]);
        $this->assertInstanceOf(Value\Name::class, $value[2]);
        $this->assertEquals('Foo', $value[2]->name);
        $this->assertEquals('bar', $value[3]);
    }

    public function testDictionary()
    {
        $value = $this->parse('<< /Type /Catalog /Pages 3 0 R /Count 2 >>');
        $this->assertIsArray($value);
        $this->assertInstanceOf(Value\Name::class, $value['Type']);
        $this->assertEquals('Catalog', $value['Type']->name);
        $this->assertInstanceOf(Value\Reference::class, $value['Pages']);
        $this->assertEquals(3, $value['Pages']->objNum);
        $this->assertEquals(2, $value['Count']);
    }

    public function testBooleansAndNull()
    {
        $this->assertTrue($this->parse('true'));
        $this->assertFalse($this->parse('false'));
        $this->assertNull($this->parse('null'));
    }

    public function testStreamWithDirectLength()
    {
        $data  = "<< /Length 5 >>\nstream\nHELLO\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLO', $value->raw);
        $this->assertEquals(5, $value->dict['Length']);
    }

    public function testStreamWithoutUsableLengthScansForEndstream()
    {
        $data  = "<< /Length 999 0 R >>\nstream\nHELLO\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLO', $value->raw);
    }

    public function testStreamWithWrongDirectLengthFallsBackToEndstreamScan()
    {
        // /Length 3 doesn't land on endstream (real content is 11 bytes) -
        // a declared-but-wrong /Length is a known real-world PDF corruption
        // pattern and must not silently truncate the stream.
        $data  = "<< /Length 3 >> stream\nHELLO WORLD\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLO WORLD', $value->raw);
    }

    public function testStreamWithNegativeLengthFallsBackToEndstreamScan()
    {
        $data  = "<< /Length -1 >>\nstream\nHELLO\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLO', $value->raw);
    }

    public function testNestingUpToCapSucceeds()
    {
        $data  = str_repeat('[', 64) . '0' . str_repeat(']', 64);
        $value = $this->parse($data);
        $this->assertIsArray($value);
    }

    public function testNestingPastCapThrows()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $data = str_repeat('[', 65) . '0' . str_repeat(']', 65);
        $this->parse($data);
    }

    public function testDepthResetsBetweenIndependentTopLevelCalls()
    {
        $data      = str_repeat('[', 64) . '0' . str_repeat(']', 64);
        $tokenizer = new Tokenizer($data . ' ' . $data);
        $parser    = new ObjectParser($tokenizer);

        $this->assertIsArray($parser->parseValue());
        $this->assertIsArray($parser->parseValue());
    }

    public function testNestingPastCapInDictValueThrows()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $data = '<< /A ' . str_repeat('[', 65) . '0' . str_repeat(']', 65) . ' >>';
        $this->parse($data);
    }

    public function testAdversarialLongBracketRunRegression()
    {
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $data = str_repeat('[', 100000);
        $this->parse($data);
    }

    public function testArrayEofThrows()
    {
        // No closing bracket - the tokenizer runs out of data mid-array.
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $this->parse('[1 2 3');
    }

    public function testStreamWithNoEndstreamMarkerAnywhereThrows()
    {
        // /Length is untrustworthy (a reference) AND there's no literal
        // 'endstream' anywhere in the data for the fallback scan to find.
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $this->parse("<< /Length 999 0 R >>\nstream\nHELLO");
    }

    public function testStreamTrimsTrailingCrlfBeforeEndstream()
    {
        // The fallback endstream scan must trim both a trailing "\n" and,
        // if present, the "\r" immediately before it - not just the "\n".
        $data  = "<< /Length 999 0 R >>\nstream\nHELLO\r\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLO', $value->raw);
    }

    public function testStreamWithFalseEndstreamMatchInsideTokenFallsBackToNextRealMatch()
    {
        // /Length is untrustworthy, so parseStreamData() falls back to a raw
        // strpos() scan for 'endstream', which is byte-oriented and doesn't
        // respect token boundaries - it finds the literal substring
        // 'endstream' embedded inside a larger keyword run ("endstreamy")
        // before the real terminator. Re-tokenizing at that position reads
        // the whole run as one keyword ("endstreamy" != "endstream"), so
        // parseStreamData() must re-scan from there to find the real
        // 'endstream' keyword.
        $data  = "<< /Length 999 0 R >>\nstream\nHELLOxendstreamy\nendstream";
        $value = $this->parse($data);
        $this->assertInstanceOf(Value\Stream::class, $value);
        $this->assertEquals('HELLOx', $value->raw);
    }

    public function testNestingPastCapAlternatingArrayDictThrows()
    {
        // Alternating [ << structure exercises both parseArray() and
        // parseDictOrStream() recursing into each other, not just one
        // shape repeated.
        $this->expectException(\Pop\Pdf\Extract\Exception::class);
        $open  = str_repeat('[<< /A ', 33);
        $close = str_repeat(' >>]', 33);
        $data  = $open . '0' . $close;
        $this->parse($data);
    }

}
