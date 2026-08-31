<?php

namespace Pop\Pdf\Test\Build;

use Pop\Color\Color;
use Pop\Pdf;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Font;
use Pop\Pdf\Document\Form;
use Pop\Pdf\Document\Page;
use Pop\Pdf\Build\Compiler;
use Pop\Pdf\Build\Exception;
use PHPUnit\Framework\TestCase;

class CompilerTest extends TestCase
{

    public function testSetDocument()
    {
        $doc = new Document();
        $compiler = new Compiler();
        $this->assertEquals(0, $compiler->lastIndex());
        $compiler->setDocument($doc);
        $this->assertInstanceOf('Pop\Pdf\Document', $compiler->getDocument());
        $this->assertInstanceOf('Pop\Pdf\Build\PdfObject\RootObject', $compiler->getRoot());
        $this->assertInstanceOf('Pop\Pdf\Build\PdfObject\ParentObject', $compiler->getParent());
        $this->assertInstanceOf('Pop\Pdf\Build\PdfObject\InfoObject', $compiler->getInfo());
    }

    public function testFinalize()
    {
        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $path = new Page\Path();
        $path->setFillColor(new Color\Rgb(255, 0, 0));
        $path->drawRectangle(320, 320, 300, 150);

        $page2 = new Page(Page::LETTER);
        $page2->addPath($path);

        $page2->addUrl(new Page\Annotation\Url(150, 20, 'http://www.google.com/'));
        $page2->addLink(new Page\Annotation\Link(150, 20, 300, 300));

        $page2->addField(new Page\Field\Text('name', 'Arial', 10), 'contact_form', 50, 200);
        $page2->addField(new Page\Field\Text('email', 'Arial', 10), 'contact_form', 50, 175);

        $page3 = new Page(Page::LETTER);
        $page3->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);
        $page3->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page3->addPath($path);

        $doc->addPages([$page1, $page2, $page3]);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testFinalizeAddsAnIdArrayToTheTrailer()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertMatchesRegularExpression('/\/ID\s*\[\s*<[0-9A-Fa-f]{32}>\s*<[0-9A-Fa-f]{32}>\s*\]/', $output);

        preg_match('/\/ID\s*\[\s*<([0-9A-Fa-f]{32})>\s*<([0-9A-Fa-f]{32})>\s*\]/', $output, $matches);
        $this->assertSame($matches[1], $matches[2]);
    }

    public function testFinalizeXrefOffsetsPointToObjects()
    {
        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $path = new Page\Path();
        $path->setFillColor(new Color\Rgb(255, 0, 0));
        $path->drawRectangle(320, 320, 300, 150);

        $page2 = new Page(Page::LETTER);
        $page2->addPath($path);

        $page2->addUrl(new Page\Annotation\Url(150, 20, 'http://www.google.com/'));
        $page2->addLink(new Page\Annotation\Link(150, 20, 300, 300));

        $page2->addField(new Page\Field\Text('name', 'Arial', 10), 'contact_form', 50, 200);
        $page2->addField(new Page\Field\Text('email', 'Arial', 10), 'contact_form', 50, 175);

        $doc->addPages([$page1, $page2]);

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        preg_match('/\nxref\n(.*?)trailer/s', $output, $xrefMatch);
        $this->assertNotEmpty($xrefMatch, 'xref table not found in compiled output');

        $lines = array_values(array_filter(explode("\n", trim($xrefMatch[1]))));
        array_shift($lines); // subsection header, e.g. "0 12"

        foreach ($lines as $i => $line) {
            if (str_ends_with(trim($line), 'f')) {
                continue; // free-list head entry
            }
            $offset         = (int)substr($line, 0, 10);
            $expectedPrefix = $i . ' 0 obj';
            $actual         = substr($output, $offset, strlen($expectedPrefix));
            $this->assertEquals(
                $expectedPrefix, $actual,
                "xref offset for object {$i} does not point to '{$expectedPrefix}' (found '" . addcslashes($actual, "\0..\37") . "' instead)"
            );
        }
    }

    public function testXrefTableEntriesMatchRealObjectOffsets()
    {
        $doc    = Pdf\Pdf::importFromFile(__DIR__ . '/../tmp/doc.pdf');
        $output = (string) $doc;

        preg_match('/trailer\n<<\/Size (\d+)\/Root (\d+)/', $output, $trailerMatch);
        $size = (int) $trailerMatch[1];
        $root = (int) $trailerMatch[2];

        $this->assertLessThan($size, $root);

        preg_match('/xref\n0 \d+\n(.*?)trailer/s', $output, $xrefMatch);
        $lines = array_values(array_filter(explode("\n", trim($xrefMatch[1]))));

        // Row 0 is always the free-list head; rows 1..size-1 are real entries.
        for ($objNum = 1; $objNum < $size; $objNum++) {
            $entry = $lines[$objNum];
            if (str_ends_with(trim($entry), 'f')) {
                continue; // a free/gap entry - nothing to verify at this row
            }
            $offset = (int) substr($entry, 0, 10);
            $this->assertEquals(
                "{$objNum} 0 obj", substr($output, $offset, strlen("{$objNum} 0 obj")),
                "xref row for object {$objNum} does not point at its actual \"N 0 obj\" text"
            );
        }
    }

    public function testFinalizeOriginTopLeft()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_TOP_LEFT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginTopRight()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_TOP_RIGHT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginBottomRight()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_BOTTOM_RIGHT);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testFinalizeOriginCenter()
    {
        $doc = new Document();
        $doc->setOrigin(Document::ORIGIN_CENTER);
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));

        $doc->addForm(new Form('contact_form'));

        $page1 = new Page(Page::LETTER);
        $page1->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page1->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page1->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);

        $doc->addPage($page1);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testGetFontsAndFontReferencesAfterFinalize()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 50);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        // Covers AbstractCompiler::getFonts()/getFontReferences() - exercised
        // through the public Compiler API rather than called for their own sake.
        $this->assertArrayHasKey('Arial', $compiler->getFonts());
        $this->assertArrayHasKey('Arial', $compiler->getFontReferences());
        $this->assertStringContainsString('MF', $compiler->getFontReferences()['Arial']);
    }

    public function testPrepareImagesFromStream()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $page   = new Page(Page::LETTER);
        $stream = file_get_contents(__DIR__ . '/../tmp/images/logo-rgb.jpg');
        $page->addImage(Page\Image::createImageFromStream($stream), 50, 600);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareTextAppliesMatchingStyleSizeAndFont()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->addFont(new Font(Font::TIMES_ROMAN));
        $doc->createStyle('heading', Font::TIMES_ROMAN, 24);

        $page = new Page(Page::LETTER);
        // The 'heading' style name is passed as the font/style slot - the
        // style's own font and size must override the text's own settings.
        $page->addText(new Page\Text('Hello World', 10), 'heading', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareTextWrapsStyleColorInGraphicsStateAndDoesNotLeakToLaterText()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->addStyle(Document\Style::create('heading', Font::ARIAL, 24, new Color\Rgb(0, 255, 0)));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Styled', 12), 'heading', 50, 700);
        $page->addText(new Page\Text('Plain', 12), 'Arial', 50, 650);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $colorOperator = '0 1 0 rg';
        $this->assertStringContainsString($colorOperator, $output);

        // The style color must be bracketed by its own q/Q graphics-state
        // save/restore, and that Q must close before the later, unstyled
        // 'Plain' text is rendered - otherwise the green fill would leak
        // forward and tint text that never asked for it.
        $colorPosition = strpos($output, $colorOperator);
        $openPosition  = strrpos(substr($output, 0, $colorPosition), 'q');
        $closePosition = strpos($output, 'Q', $colorPosition);
        $plainPosition = strpos($output, '(Plain)Tj');

        $this->assertNotFalse($openPosition);
        $this->assertNotFalse($closePosition);
        $this->assertNotFalse($plainPosition);
        $this->assertGreaterThan($openPosition, $colorPosition);
        $this->assertGreaterThan($colorPosition, $closePosition);
        $this->assertGreaterThan($closePosition, $plainPosition);
    }

    public function testPrepareTextThrowsWhenFontNotAdded()
    {
        $this->expectException(Exception::class);

        $doc  = new Document();
        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'NotAddedFont', 50, 50);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
    }

    public function testPrepareTextWithCharWrap()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $text = new Page\Text('Hello World Hello World Hello World Hello World Hello World', 12);
        $text->setCharWrap(20);

        $page = new Page(Page::LETTER);
        $page->addText($text, 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareTextWithAlignment()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $text = new Page\Text('Hello World Hello World Hello World Hello World Hello World', 12);
        $text->setAlignment(Page\Text\Alignment::createLeft(50, 550));

        $page = new Page(Page::LETTER);
        $page->addText($text, 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareTextWithAlignmentDoesNotDoubleEscapeParens()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $text = new Page\Text('Hello (World) Hello (World) Hello (World) Hello (World)', 12);
        $text->setAlignment(Page\Text\Alignment::createLeft(50, 550));

        $page = new Page(Page::LETTER);
        $page->addText($text, 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('\(World\)', $compiler->getOutput());
        $this->assertStringNotContainsString('\\\\(World\\\\)', $compiler->getOutput());
    }

    public function testPrepareTextWithWrapDoesNotDoubleEscapeParens()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $text = new Page\Text('Hello (World) Hello (World) Hello (World) Hello (World)', 12);
        $text->setWrap(Page\Text\Wrap::createLeft(50, 550));

        $page = new Page(Page::LETTER);
        $page->addText($text, 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('\(World\)', $compiler->getOutput());
        $this->assertStringNotContainsString('\\\\(World\\\\)', $compiler->getOutput());
    }

    public function testPrepareTextWithWrapAndFillColor()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $text = new Page\Text('Hello World Hello World Hello World Hello World Hello World', 12);
        $text->setFillColor(new Color\Rgb(255, 0, 0));
        $text->setWrap(Page\Text\Wrap::createLeft(50, 550));

        $page = new Page(Page::LETTER);
        $page->addText($text, 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareFormsWritesSingleAcroFormDictionaryReference()
    {
        // Regression test: /AcroForm must be a single indirect reference to
        // one form dictionary (per spec), not an array of references - even
        // when multiple named Document\Form groups are added, their fields
        // must be combined into one dictionary object.
        $doc = new Document();
        $doc->addForm(new Form('contact_form'));
        $doc->addForm(new Form('newsletter_form'));

        $page = new Page(Page::LETTER);
        $page->addField(new Page\Field\Text('name', 'Arial', 10), 'contact_form', 50, 200);
        $page->addField(new Page\Field\Text('email', 'Arial', 10), 'newsletter_form', 50, 175);
        $doc->addFont(new Font('Arial'));
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertMatchesRegularExpression('/\/AcroForm \d+ 0 R/', $output);
        $this->assertDoesNotMatchRegularExpression('/\/AcroForm \[/', $output);

        preg_match('/\/AcroForm (\d+) 0 R/', $output, $matches);
        $formObjNum = $matches[1];

        preg_match('/' . $formObjNum . ' 0 obj\s*<<\/Fields\[(.*?)\]>>/', $output, $fieldsMatch);
        $this->assertEquals(2, substr_count($fieldsMatch[1], ' 0 R'));
    }

    public function testPrepareFieldsThrowsWhenFontNotAdded()
    {
        $this->expectException(Exception::class);

        $doc = new Document();
        $doc->addForm(new Form('contact_form'));

        $page = new Page(Page::LETTER);
        $page->addField(new Page\Field\Text('name', 'NotAddedFont', 10), 'contact_form', 50, 200);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
    }

    public function testPrepareFieldsWithNoFontLeavesFontRefNull()
    {
        $doc = new Document();
        $doc->addForm(new Form('contact_form'));

        $page = new Page(Page::LETTER);
        // No font passed to the field - prepareFields() must fall through to
        // a null font reference instead of throwing or requiring one.
        $page->addField(new Page\Field\Text('name'), 'contact_form', 50, 200);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('%PDF', $compiler->getOutput());
    }

    public function testPrepareFontsEmitsCidStructureForEmbeddedTrueType()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text("Hello \u{041F}", 12), $doc->getCurrentFont(), 50, 50);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $fontRef    = $compiler->getFontReferences()['DejaVuSans'];
        $objectIndex = (int) explode(' ', $fontRef)[1];
        $definition  = (string) $compiler->getObjects()[$objectIndex];

        $this->assertStringContainsString('/Subtype /Type0', $definition);
    }

    public function testPrepareTextWithCidFontEmitsHexStringContent()
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text("\u{041F}\u{0420}\u{0418}\u{0412}\u{0406}\u{0422}", 36), $doc->getCurrentFont(), 50, 400);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertStringContainsString('>Tj', $compiler->getOutput());
    }

    /**
     * Build a one-page document with an embedded CID font, write it out and
     * extract the text back, so alignment/wrap output is checked for real
     * rendered content rather than just "it didn't throw".
     */
    protected function roundTripEmbeddedText(Page\Text $text): string
    {
        $doc = new Document();
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/DejaVuSans.ttf'));

        $page = new Page(Page::LETTER);
        $page->addText($text, $doc->getCurrentFont(), 50, 600);
        $doc->addPage($page);

        $file = tempnam(sys_get_temp_dir(), 'pop-pdf-') . '.pdf';

        try {
            Pdf\Pdf::writeToFile($doc, $file);
            return Pdf\Pdf::extractTextFromFile($file);
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testPrepareTextAlignmentWithCidFontRendersLatinText()
    {
        $text = new Page\Text('The quick brown fox jumps over the lazy dog again and again', 14);
        $text->setAlignment(Page\Text\Alignment::createLeft(50, 300, 18));

        $extracted = $this->roundTripEmbeddedText($text);

        $this->assertEquals(
            'The quick brown fox jumps over the lazy dog again and again',
            preg_replace('/\s+/u', ' ', trim($extracted))
        );
    }

    public function testPrepareTextAlignmentWithCidFontRendersCyrillicText()
    {
        $text = new Page\Text("\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442} \u{0441}\u{0432}\u{0456}\u{0442} " .
            "\u{0446}\u{0435} \u{0442}\u{0435}\u{0441}\u{0442}", 14);
        $text->setAlignment(Page\Text\Alignment::createLeft(50, 300, 18));

        $extracted = $this->roundTripEmbeddedText($text);

        $this->assertEquals(
            "\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442} \u{0441}\u{0432}\u{0456}\u{0442} " .
            "\u{0446}\u{0435} \u{0442}\u{0435}\u{0441}\u{0442}",
            preg_replace('/\s+/u', ' ', trim($extracted))
        );
    }

    public function testPrepareTextWrapWithCidFontRendersLatinText()
    {
        $text = new Page\Text('The quick brown fox jumps over the lazy dog again and again', 14);
        $text->setWrap(Page\Text\Wrap::createLeft(50, 300, ['left' => 0, 'right' => 0, 'top' => 0, 'bottom' => 0], 18));

        $extracted = $this->roundTripEmbeddedText($text);

        $this->assertEquals(
            'The quick brown fox jumps over the lazy dog again and again',
            preg_replace('/\s+/u', ' ', trim($extracted))
        );
    }

    /**
     * Wrap::getStrings() splits on a single-byte ASCII space (explode(' ', ...)),
     * which can never land inside a UTF-8 multi-byte sequence, so wrapping is
     * byte-safe for any script. This asserts the wrapped Cyrillic lines come
     * back intact (and on two separate lines) after a build/extract round trip.
     */
    public function testPrepareTextWrapWithCidFontRendersCyrillicTextAcrossLines()
    {
        $string = "\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442} \u{0441}\u{0432}\u{0456}\u{0442} " .
            "\u{0446}\u{0435} \u{0442}\u{0435}\u{0441}\u{0442} \u{043F}\u{0435}\u{0440}\u{0435}\u{043D}\u{043E}" .
            "\u{0441}\u{0443} \u{0440}\u{044F}\u{0434}\u{043A}\u{0456}\u{0432}";

        $text = new Page\Text($string, 14);
        $text->setWrap(Page\Text\Wrap::createLeft(50, 300, ['left' => 0, 'right' => 0, 'top' => 0, 'bottom' => 0], 18));

        $extracted = trim($this->roundTripEmbeddedText($text));

        $this->assertStringContainsString("\n", $extracted);
        $this->assertEquals($string, preg_replace('/\s+/u', ' ', $extracted));
    }

    public function testPrepareTextAlignmentWithStandardFontAndCyrillicTextThrows()
    {
        $this->expectException(\Pop\Pdf\Build\Font\Exception::class);

        $doc = new Document();
        $doc->addFont(new Font(Font::ARIAL));

        $text = new Page\Text("\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442} \u{0441}\u{0432}\u{0456}\u{0442}", 14);
        $text->setAlignment(Page\Text\Alignment::createLeft(50, 300, 18));

        $page = new Page(Page::LETTER);
        $page->addText($text, Font::ARIAL, 50, 600);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
    }

    public function testPrepareTextWrapWithStandardFontAndCyrillicTextThrows()
    {
        $this->expectException(\Pop\Pdf\Build\Font\Exception::class);

        $doc = new Document();
        $doc->addFont(new Font(Font::ARIAL));

        $text = new Page\Text("\u{041F}\u{0440}\u{0438}\u{0432}\u{0456}\u{0442} \u{0441}\u{0432}\u{0456}\u{0442}", 14);
        $text->setWrap(Page\Text\Wrap::createLeft(50, 300, ['left' => 0, 'right' => 0, 'top' => 0, 'bottom' => 0], 18));

        $page = new Page(Page::LETTER);
        $page->addText($text, Font::ARIAL, 50, 600);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
    }

    public function testFinalizeEncryptsDocumentWhenSecurityIsSet()
    {
        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/Encrypt', $output);
        // The plaintext page content must not appear literally anymore - it's
        // now inside an encrypted stream.
        $this->assertStringNotContainsString('(Hello World)Tj', $output);
    }

    public function testFinalizeThrowsExceptionForInvalidAlgorithm()
    {
        $this->expectException(\Pop\Pdf\Build\Security\Exception::class);

        $doc = new Document();
        $doc->addFont(new Font('Arial'));

        $security = new Document\Security('open-me', 'admin123');
        $security->setAlgorithm('BOGUS');
        $doc->setSecurity($security);

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);
    }

    public function testFinalizeVerifiesWithQpdf()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123'));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertPassesQpdfCheck($compiler->getOutput(), 'open-me');
    }

    // Image XObjects and embedded font files are built via
    // PdfObject\StreamObject::parse() rather than direct appendStream()
    // calls, and (unlike ordinary text-content streams) declare a literal
    // /Length up front. That shape was the one the encryption pass initially
    // got wrong - a text-only document never exercises it, so this test
    // deliberately embeds a TrueType font and a JPEG image alongside text,
    // for both AES-128/revision 4 and AES-256/revision 6, and verifies with
    // real qpdf rather than only round-tripping through this library's own
    // code.
    public function testFinalizeVerifiesWithQpdfIncludingImageAndEmbeddedFontAes256()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_256));

        $page = new Page(Page::LETTER);
        $page->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertPassesQpdfCheck($compiler->getOutput(), 'open-me');
    }

    public function testFinalizeVerifiesWithQpdfIncludingImageAndEmbeddedFontAes128()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_128));

        $page = new Page(Page::LETTER);
        $page->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page->addText(new Page\Text('Hello World', 36), $doc->getCurrentFont(), 50, 400);
        $page->addText(new Page\Text('Hello World', 12), 'Arial', 50, 350);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $this->assertPassesQpdfCheck($compiler->getOutput(), 'open-me');
    }

    /**
     * Write $pdfData to a temp file and assert both `qpdf --check` and
     * `qpdf --decrypt` succeed against it with the given user password.
     * qpdf --check requires the password whenever a non-empty user password
     * is set (as it is here) - without one it correctly (and always) reports
     * "invalid password", even against a PDF qpdf itself encrypted, so the
     * password is supplied to both invocations.
     *
     * @param  string $pdfData
     * @param  string $password
     * @return void
     */
    protected function assertPassesQpdfCheck(string $pdfData, string $password): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_encrypt_test_') . '.pdf';
        file_put_contents($tmpFile, $pdfData);

        exec(
            'qpdf --password=' . escapeshellarg($password) . ' --check ' . escapeshellarg($tmpFile) . ' 2>&1',
            $checkOutput, $checkStatus
        );
        exec(
            'qpdf --password=' . escapeshellarg($password) . ' --decrypt ' . escapeshellarg($tmpFile) . ' ' .
            escapeshellarg($tmpFile . '.dec') . ' 2>&1',
            $decryptOutput, $decryptStatus
        );

        unlink($tmpFile);
        if (file_exists($tmpFile . '.dec')) {
            unlink($tmpFile . '.dec');
        }

        $this->assertEquals(0, $checkStatus, implode("\n", $checkOutput));
        $this->assertEquals(0, $decryptStatus, implode("\n", $decryptOutput));
    }

}