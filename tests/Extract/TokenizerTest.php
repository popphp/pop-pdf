<?php

namespace Pop\Pdf\Test\Extract;

use Pop\Pdf\Extract\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{

    public function testNumbers()
    {
        $t = new Tokenizer('12 -3.5 .5 1');
        $this->assertEquals(['type' => 'number', 'value' => 12], $t->next());
        $this->assertEquals(['type' => 'number', 'value' => -3.5], $t->next());
        $this->assertEquals(['type' => 'number', 'value' => 0.5], $t->next());
        $this->assertEquals(['type' => 'number', 'value' => 1], $t->next());
        $this->assertEquals('eof', $t->next()['type']);
    }

    public function testName()
    {
        $t = new Tokenizer('/Type /Pa#67es');
        $this->assertEquals(['type' => 'name', 'value' => 'Type'], $t->next());
        $this->assertEquals(['type' => 'name', 'value' => 'Pages'], $t->next());
    }

    public function testLiteralStringWithEscapesAndNesting()
    {
        $t = new Tokenizer('(Hello (World)\\n\\051end)');
        $token = $t->next();
        $this->assertEquals('string', $token['type']);
        $this->assertEquals("Hello (World)\n)end", $token['value']);
    }

    public function testHexString()
    {
        $t = new Tokenizer('<48656C6C6F>');
        $this->assertEquals(['type' => 'string', 'value' => 'Hello'], $t->next());
    }

    public function testHexStringOddDigits()
    {
        $t = new Tokenizer('<48656C6C6F0>');
        $token = $t->next();
        $this->assertEquals('Hello' . chr(0x00), substr($token['value'], 0, 6));
    }

    public function testDictAndArrayDelimiters()
    {
        $t = new Tokenizer('<< /A [1 2] >>');
        $this->assertEquals(['type' => 'dict_start', 'value' => '<<'], $t->next());
        $this->assertEquals(['type' => 'name', 'value' => 'A'], $t->next());
        $this->assertEquals(['type' => 'array_start', 'value' => '['], $t->next());
        $this->assertEquals(['type' => 'number', 'value' => 1], $t->next());
        $this->assertEquals(['type' => 'number', 'value' => 2], $t->next());
        $this->assertEquals(['type' => 'array_end', 'value' => ']'], $t->next());
        $this->assertEquals(['type' => 'dict_end', 'value' => '>>'], $t->next());
    }

    public function testKeywordsAndComments()
    {
        $t = new Tokenizer("obj % a comment\nendobj true false null R");
        $this->assertEquals(['type' => 'keyword', 'value' => 'obj'], $t->next());
        $this->assertEquals(['type' => 'keyword', 'value' => 'endobj'], $t->next());
        $this->assertEquals(['type' => 'keyword', 'value' => 'true'], $t->next());
        $this->assertEquals(['type' => 'keyword', 'value' => 'false'], $t->next());
        $this->assertEquals(['type' => 'keyword', 'value' => 'null'], $t->next());
        $this->assertEquals(['type' => 'keyword', 'value' => 'R'], $t->next());
    }

    public function testPositionSaveRestore()
    {
        $t = new Tokenizer('1 2 3');
        $t->next();
        $pos = $t->getPosition();
        $t->next();
        $t->setPosition($pos);
        $this->assertEquals(['type' => 'number', 'value' => 2], $t->next());
    }

    public function testLiteralStringMoreEscapeSequences()
    {
        // \r, \t, \b, \f control-character escapes.
        $t = new Tokenizer('(\\r\\t\\b\\f)');
        $token = $t->next();
        $this->assertEquals('string', $token['type']);
        $this->assertEquals("\r\t\x08\x0C", $token['value']);
    }

    public function testLiteralStringEscapedParensAndBackslash()
    {
        // \( , \) , \\ - escaped delimiter/backslash characters that must
        // be kept literally rather than interpreted structurally.
        $t = new Tokenizer('(\(\)\\\\)');
        $token = $t->next();
        $this->assertEquals('string', $token['type']);
        $this->assertEquals('()' . chr(92), $token['value']);
    }

    public function testLiteralStringUnrecognizedEscapeKeepsCharacterLiterally()
    {
        // Per the PDF spec, a backslash before a character with no defined
        // escape meaning is simply ignored, keeping the character.
        $t = new Tokenizer('(\\q)');
        $token = $t->next();
        $this->assertEquals('q', $token['value']);
    }

    public function testLiteralStringBackslashLineContinuation()
    {
        // A backslash immediately followed by an actual EOL byte is a line
        // continuation - it contributes nothing to the decoded value.
        $lf = new Tokenizer('(' . '\\' . "\n" . 'AB)');
        $this->assertEquals('AB', $lf->next()['value']);

        $cr = new Tokenizer('(' . '\\' . "\r" . 'AB)');
        $this->assertEquals('AB', $cr->next()['value']);

        $crlf = new Tokenizer('(' . '\\' . "\r\n" . 'CD)');
        $this->assertEquals('CD', $crlf->next()['value']);
    }

    public function testLiteralStringUnterminatedAtEofDoesNotThrow()
    {
        // A backslash as the very last byte of the data (no character after
        // it to escape) must stop cleanly rather than reading past the end.
        $t = new Tokenizer('(AB' . '\\');
        $token = $t->next();
        $this->assertEquals('string', $token['type']);
        $this->assertEquals('AB', $token['value']);
    }

    public function testDegenerateSignOrDotAloneIsKeyword()
    {
        // A lone '+', '-', or '.' with no digits following isn't a valid
        // number - it falls back to a single-character keyword token.
        $plus = new Tokenizer('+ 5');
        $this->assertEquals(['type' => 'keyword', 'value' => '+'], $plus->next());

        $dot = new Tokenizer('. 5');
        $this->assertEquals(['type' => 'keyword', 'value' => '.'], $dot->next());
    }

    public function testManyStrayAngleClosesDoNotBlowTheCallStack()
    {
        // Each stray '>' followed by non-'>' content used to recurse via
        // next() -> readAngleClose() -> next() with no loop, so a long run
        // of these would overflow the call stack. It must now resolve to a
        // single token quickly instead of crashing.
        $data = str_repeat('> ', 300000) . 'true';
        $t    = new Tokenizer($data);

        $token = $t->next();

        $this->assertEquals(['type' => 'keyword', 'value' => 'true'], $token);
    }

}
