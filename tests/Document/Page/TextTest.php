<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Color\Color;;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Page\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{

    public function testGetSize()
    {
        $text = new Text('Hello World', 12);
        $this->assertEquals(12, $text->getSize());
    }

    public function testSetMbString()
    {
        $text = new Text("mb string åèä test", 12);
        $this->assertTrue($text->hasString());
        $this->assertEquals(18, mb_strlen($text->getString()));
    }

    public function testSetStrings()
    {
        $text = new Text();
        $text->setStrings([
            'hello world', new Text('how are you?')
        ]);
        $this->assertCount(2, $text->getStrings());
    }

    public function testSetTextStream()
    {
        $text = new Text();
        $text->setTextStream(new Text\Stream(0, 0, 0, 0));
        $this->assertTrue($text->hasTextStream());
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Stream', $text->getTextStream());
    }

    public function testEscape()
    {
        $text = new Text("Testing (Hello World) What's up\n Man!", 12);
        $this->assertEquals("Testing \(Hello World\) What's up\\n Man!", $text->getString());
    }

    public function testAddStringWithOffset()
    {
        $text = new Text('', 12);
        $text->addStringWithOffset('Hello', 10);
        $text->addStringWithOffset("mb string åèä test", 10);
        $this->assertEquals(2, count($text->getStringsWithOffset()));
    }

    public function testSetFillColor()
    {
        $text = new Text('Hello World', 12);
        $text->setFillColor(new Color\Rgb(255, 0, 0));
        $this->assertInstanceOf('Pop\Color\Color\Rgb', $text->getFillColor());
    }

    public function testSetStrokeColor()
    {
        $text = new Text('Hello World', 12);
        $text->setStrokeColor(new Color\Rgb(255, 0, 0));
        $this->assertInstanceOf('Pop\Color\Color\Rgb', $text->getStrokeColor());
    }

    public function testSetStroke()
    {
        $text = new Text('Hello World', 12);
        $text->setStroke(5, 10, 15);
        $this->assertEquals(5, $text->getStroke()['width']);
        $this->assertEquals(10, $text->getStroke()['dashLength']);
        $this->assertEquals(15, $text->getStroke()['dashGap']);
    }

    public function testSetRotation()
    {
        $text = new Text('Hello World', 12);
        $text->setRotation(45);
        $this->assertEquals(45, $text->getRotation());
    }

    public function testSetRotationException()
    {
        $this->expectException('OutOfRangeException');
        $text = new Text('Hello World', 12);
        $text->setRotation(120);
    }

    public function testSetTextParams()
    {
        $text = new Text('Hello World', 12);
        $text->setTextParams(10, 10, 10, 10, -45, 1);
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text', $text);
    }

    public function testSetTextParamsException1()
    {
        $this->expectException('OutOfRangeException');
        $text = new Text('Hello World', 12);
        $text->setTextParams(10, 10, 10, 10, -120, 1);
    }

    public function testSetTextParamsException2()
    {
        $this->expectException('OutOfRangeException');
        $text = new Text('Hello World', 12);
        $text->setTextParams(10, 10, 10, 10, -45, 10);
    }

    public function testGetStream()
    {
        $text = new Text('Hello World', 12);
        $text->setFillColor(new Color\Rgb(255, 0, 0));
        $text->setStrokeColor(new Color\Rgb(255, 0, 0));
        $text->setStroke(5, 10, 15);
        $text->setTextParams(10, 10, 10, 10, -45, 1);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testGetStreamCmyk()
    {
        $text = new Text('Hello World', 12);
        $text->setFillColor(new Color\Cmyk(100, 0, 0, 0));
        $text->setStrokeColor(new Color\Cmyk(100, 0, 0, 0));
        $text->setStroke(5, 10, 15);
        $text->setTextParams(10, 10, 10, 10, -45, 1);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testGetStreamGray()
    {
        $text = new Text('Hello World', 12);
        $text->setFillColor(new Color\Grayscale(50));
        $text->setStrokeColor(new Color\Grayscale(50));
        $text->setStroke(5, 10, 15);
        $text->setTextParams(10, 10, 10, 10, -45, 1);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testSetAndGetCharWrap()
    {
        $text = new Text('Hello World Hello World Hello World Hello World Hello World Hello World ', 12);
        $text->setCharWrap(24, 10);
        $this->assertEquals(24, $text->getCharWrap());
        $this->assertEquals(10, $text->getLeading());
        $this->assertTrue($text->hasCharWrap());
        $this->assertEquals(3, $text->getNumberOfWrappedLines());
    }

    public function testSetAndGetLeading()
    {
        $text = new Text('Hello World', 12);
        $text->setLeading(10);
        $this->assertEquals(10, $text->getLeading());
        $this->assertTrue($text->hasLeading());
    }

    public function testSetAndGetAlignment()
    {
        $text = new Text('Hello World', 12);
        $text->setAlignment(Text\Alignment::createLeft(50, 550));
        $this->assertTrue($text->hasAlignment());
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Alignment', $text->getAlignment());
    }

    public function testSetAndGetWrap()
    {
        $text = new Text('Hello World', 12);
        $text->setWrap(Text\Wrap::createLeft(50, 550));
        $this->assertTrue($text->hasWrap());
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Text\Wrap', $text->getWrap());
    }

    public function testGetStreamWithRotation1()
    {
        $text = new Text('Hello World', 12);
        $text->setRotation(40);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testGetStreamWithRotation2()
    {
        $text = new Text('Hello World', 12);
        $text->setRotation(80);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testGetStreamWithRotation3()
    {
        $text = new Text('Hello World', 12);
        $text->setRotation(-80);
        $this->assertStringContainsString('MF1', $text->getStream('MF1 1 0 R', 20, 200));
    }

    public function testGetPartialStreamWithFontRef()
    {
        $text = new Text('Hello World', 12);
        $this->assertStringContainsString('/MF1 12 Tf', $text->getPartialStream('/MF1 1 0 R'));
    }

    public function testGetPartialStreamWithStringOffsets()
    {
        $text = new Text('Hello World', 12);
        $text->addStringWithOffset("What's up?", 12);
        $stream = $text->getPartialStream();
        $this->assertStringContainsString("[(Hello World) -12 (What's up?)]TJ", $stream);
    }

    public function testGetPartialStreamWithCharWrap()
    {
        $text = new Text('Hello World Hello World Hello World Hello World Hello World Hello World', 12);
        $text->setCharWrap(24);
        $stream = $text->getPartialStream();
        $this->assertStringContainsString("0 -12 Td", $stream);
        $this->assertStringContainsString(")Tj", $stream);
    }

    public function testEscapeIsCallableStatically()
    {
        $this->assertEquals('Hello \(World\)', Text::escape('Hello (World)'));
    }

    public function testEscapeConvertsRealBackspaceCharacter()
    {
        // Regression test: the search list's "\b" entry is a PHP string
        // literal (backslash followed by 'b'), not a recognized escape
        // sequence for chr(8) - so a real backspace byte in the subject was
        // passing straight through unescaped.
        $this->assertEquals('Before \b After', Text::escape('Before ' . chr(8) . ' After'));
    }

    public function testSetStringsConvertsMultibyteStringsAndTextObjects()
    {
        // Both branches of setStrings()'s array_map() closure convert a
        // multibyte string to UTF-8 - one for a nested Text object's own
        // string (returned in place of the object), one for a plain string
        // element (which is then also escaped, same as setString() does).
        // Neither is exercised by the existing testSetStrings(), which only
        // uses plain ASCII.
        $text = new Text();
        $text->setStrings([new Text('café'), 'café (test)']);

        $strings = $text->getStrings();
        $this->assertCount(2, $strings);
        $this->assertIsString($strings[0]);
        $this->assertEquals('café', $strings[0]);
        // The plain-string element must have gone through escape() too.
        $this->assertEquals('café \(test\)', $strings[1]);
    }

    public function testSetFont()
    {
        $text = new Text('Hello World', 12);
        $font = new Font('Arial');
        $text->setFont($font);
        $this->assertTrue($text->hasFont());
        $this->assertSame($font, $text->getFont());
    }

    public function testGetPartialStreamWithCidFontEmitsHexString()
    {
        $text = new Text("\u{041F}\u{0420}\u{0418}", 36);
        $text->setFont(new Font(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf'));

        $stream = $text->getPartialStream();

        $this->assertStringContainsString('<', $stream);
        $this->assertStringContainsString('>Tj', $stream);
        $this->assertStringNotContainsString('(', $stream);
    }

    public function testGetPartialStreamWithStandardFontAndUnsupportedCharacterThrows()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        $text = new Text("\u{041F}\u{0420}\u{0418}", 36);
        $text->setFont(new Font('Arial'));
        $text->getPartialStream();
    }

    public function testGetPartialStreamWithCidFontAndCharWrapWrapsByCharacterCount()
    {
        // "ПРИ" is 3 Cyrillic characters (6 UTF-8 bytes) - a byte-based wrap
        // at width 7 would never fit two words plus a space (7 chars, 13
        // bytes) on one line, but a character-based wrap should.
        $word = "\u{041F}\u{0420}\u{0418}";
        $font = new Font(__DIR__ . '/../../tmp/fonts/DejaVuSans.ttf');
        // Computed from the same font instance, so this is the ground-truth
        // glyph-hex for the combined string - not a re-derivation that could
        // independently share the same mistake as the code under test.
        $expectedTwoWordHex = $font->stringToGidHex($word . ' ' . $word);

        $text = new Text(str_repeat($word . ' ', 10), 12);
        $text->setFont($font);
        $text->setCharWrap(7);

        $stream = $text->getPartialStream();

        $this->assertStringContainsString('<' . $expectedTwoWordHex . '>', $stream);
    }

    public function testGetPartialStreamWithStandardFontValidatesStringsWithOffsets()
    {
        $this->expectException('Pop\Pdf\Build\Font\Exception');
        // The primary string is plain ASCII - only the TJ offset string
        // carries the Cyrillic, which must be validated too.
        $text = new Text('Hello', 12);
        $text->setFont(new Font('Arial'));
        $text->addStringWithOffset("\u{041F}\u{0420}\u{0418}", 100);
        $text->getPartialStream();
    }

    public function testGetPartialStreamWithStandardFontAllowsSupportedStringsWithOffsets()
    {
        $text = new Text('Hello', 12);
        $text->setFont(new Font('Arial'));
        $text->addStringWithOffset('World', 100);

        $stream = $text->getPartialStream();

        $this->assertStringContainsString('[(Hello)', $stream);
        $this->assertStringContainsString('(World)', $stream);
        $this->assertStringContainsString(']TJ', $stream);
    }

    public function testGetPartialStreamWithStandardFontAllowsWinAnsiPunctuation()
    {
        // U+00A0 no-break space, U+00AD soft hyphen, U+2018 left single quote
        // and U+2020 dagger are all real WinAnsi codepoints - they must not be
        // rejected by the glyph-coverage pre-flight check.
        $text = new Text("A\u{00A0}B\u{00AD}C\u{2018}D\u{2020}", 12);
        $text->setFont(new Font('Arial'));

        $this->assertStringContainsString('Tj', $text->getPartialStream());
    }

    public function testGetPartialStreamWithoutFontIsUnchanged()
    {
        // No font set at all (matches every pre-existing Text test in this
        // file) must keep behaving exactly as before - literal string, no
        // validation, no exception.
        $text = new Text('Hello World', 12);
        $stream = $text->getPartialStream();
        $this->assertStringContainsString('(Hello World)Tj', $stream);
    }

    public function testGetPartialStreamWithStandardFontTranscodesToWinAnsi()
    {
        $text = new Text('café', 12);
        $text->setFont(new Font('Arial'));

        $stream = $text->getPartialStream();

        // 'é' (U+00E9) must appear as the single WinAnsi byte 0xE9, not the
        // 2-byte UTF-8 sequence C3 A9 - the raw bytes previously written
        // would have mojibaked to "Ã©" in any PDF viewer.
        $this->assertStringContainsString("caf\xE9", $stream);
        $this->assertStringNotContainsString("caf\xC3\xA9", $stream);
    }

    public function testGetPartialStreamWithStandardFontTranscodesCurlyQuoteAndDagger()
    {
        $text = new Text("\u{2018}\u{2020}", 12);
        $text->setFont(new Font('Arial'));

        $stream = $text->getPartialStream();

        // U+2018 (left single quote) -> WinAnsi 0x91, U+2020 (dagger) -> WinAnsi 0x86
        $this->assertStringContainsString("\x91\x86", $stream);
    }

    public function testGetPartialStreamWithStandardFontTranscodesStringsWithOffsets()
    {
        $text = new Text('café', 12);
        $text->setFont(new Font('Arial'));
        $text->addStringWithOffset('résumé', 100);

        $stream = $text->getPartialStream();

        $this->assertStringContainsString("caf\xE9", $stream);
        $this->assertStringContainsString("r\xE9sum\xE9", $stream);
        $this->assertStringNotContainsString("\xC3\xA9", $stream);
    }

    public function testGetPartialStreamWithStandardFontTranscodesCharWrap()
    {
        $text = new Text(str_repeat('café ', 20), 12);
        $text->setFont(new Font('Arial'));
        $text->setCharWrap(24);

        $stream = $text->getPartialStream();

        $this->assertStringContainsString("caf\xE9", $stream);
        $this->assertStringNotContainsString("\xC3\xA9", $stream);
    }

    public function testGetPartialStreamWithStandardFontCharWrapUsesCharacterCountNotByteCount()
    {
        // "café" is 4 characters but 5 bytes. A byte-based wrap at width 9
        // could never fit two "café"s (11 bytes) on one line, even though
        // they're only 9 characters - the wrap must be character-based.
        $text = new Text(str_repeat('café ', 10), 12);
        $text->setFont(new Font('Arial'));
        $text->setCharWrap(9);

        $stream = $text->getPartialStream();

        $this->assertStringContainsString("caf\xE9 caf\xE9", $stream);
    }

    public function testGetNumberOfWrappedLinesCountsCharactersNotBytes()
    {
        $text = new Text(str_repeat('café ', 10), 12);
        $text->setCharWrap(9);
        $this->assertEquals(6, $text->getNumberOfWrappedLines());
    }

}
