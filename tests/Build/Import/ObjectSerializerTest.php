<?php

namespace Pop\Pdf\Test\Build\Import;

use Pop\Pdf\Build\Import\ObjectSerializer;
use Pop\Pdf\Build\Exception;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ObjectSerializerTest extends TestCase
{

    public function testSerializeName()
    {
        $this->assertEquals('/Catalog', ObjectSerializer::serializeValue(new Value\Name('Catalog')));
    }

    public function testSerializeNameEscapesSpecialBytes()
    {
        // A raw space (0x20) is not a valid literal name byte - it must be
        // hex-escaped as #20, mirroring how Tokenizer::readName() decodes it.
        $this->assertEquals('/A#20B', ObjectSerializer::serializeValue(new Value\Name('A B')));
    }

    public function testSerializeInt()
    {
        $this->assertEquals('5', ObjectSerializer::serializeValue(5));
    }

    public function testSerializeWholeFloatDropsDecimal()
    {
        $this->assertEquals('1', ObjectSerializer::serializeValue(1.0));
    }

    public function testSerializeFractionalFloat()
    {
        $this->assertEquals('3.5', ObjectSerializer::serializeValue(3.5));
    }

    public function testSerializeBoolAndNull()
    {
        $this->assertEquals('true', ObjectSerializer::serializeValue(true));
        $this->assertEquals('false', ObjectSerializer::serializeValue(false));
        $this->assertEquals('null', ObjectSerializer::serializeValue(null));
    }

    public function testSerializeLiteralString()
    {
        $this->assertEquals('(Hello)', ObjectSerializer::serializeValue('Hello'));
    }

    public function testSerializeLiteralStringEscapesParensAndBackslash()
    {
        $input    = 'a(b)c' . '\\' . 'd';
        $expected = '(' . 'a' . '\\' . '(' . 'b' . '\\' . ')' . 'c' . '\\' . '\\' . 'd' . ')';

        $this->assertEquals($expected, ObjectSerializer::serializeValue($input));
    }

    public function testSerializeReferenceAlwaysUsesGenerationZero()
    {
        $this->assertEquals('5 0 R', ObjectSerializer::serializeValue(new Value\Reference(5, 7)));
    }

    public function testSerializeKeyword()
    {
        $this->assertEquals('foo', ObjectSerializer::serializeValue(new Value\Keyword('foo')));
    }

    public function testSerializeDict()
    {
        $dict = ['Type' => new Value\Name('Catalog'), 'Pages' => new Value\Reference(2, 0)];

        $this->assertEquals('<< /Type /Catalog /Pages 2 0 R >>', ObjectSerializer::serializeDict($dict));
    }

    public function testSerializeArray()
    {
        $this->assertEquals('[ 1 2 3 ]', ObjectSerializer::serializeArray([1, 2, 3]));
    }

    public function testSerializeValueDispatchesNestedArraysAndDicts()
    {
        $value = [
            'Kids'  => [new Value\Reference(3, 0), new Value\Reference(4, 0)],
            'Count' => 2,
        ];

        $this->assertEquals('<< /Kids [ 3 0 R 4 0 R ] /Count 2 >>', ObjectSerializer::serializeValue($value));
    }

    public function testSerializeNegativeFloat()
    {
        $this->assertEquals('-2.5', ObjectSerializer::serializeValue(-2.5));
    }

    public function testSerializeSmallNegativeFloatRoundingToZero()
    {
        // -0.0000001 rounds to -0.000000 at 6 decimals, then strips to "-0"
        // Must be normalized to "0", not returned as "-0"
        $this->assertEquals('0', ObjectSerializer::serializeValue(-0.0000001));
    }

    public function testSerializeNegativeZero()
    {
        // -0.0 should serialize as "0"
        $this->assertEquals('0', ObjectSerializer::serializeValue(-0.0));
    }

    public function testSerializeValueThrowsOnUnsupportedType()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error: Cannot serialize a PDF value of type stdClass.');

        ObjectSerializer::serializeValue(new \stdClass());
    }

}
