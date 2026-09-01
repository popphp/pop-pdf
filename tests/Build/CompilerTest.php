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

    // Regression test for the exact real-world bug: a document encrypted by
    // this library opened fine in Adobe Acrobat and Firefox but showed as a
    // blank page with a garbled title in poppler-based Linux readers, and
    // outright rejected the correct password in Chrome. Root cause was
    // /StmF /StdCF /StrF /Identity - legal PDF, but poppler/Chromium don't
    // honor /StmF independently of /StrF and fall back to treating the whole
    // file as RC4. qpdf alone can't catch this (it doesn't share that bug),
    // so this test shells out to real poppler-utils.
    public function testEncryptedDocumentIsCorrectlyIdentifiedByPoppler()
    {
        if ((shell_exec('which qpdf') === null) || (shell_exec('which pdfinfo') === null)) {
            $this->markTestSkipped('qpdf and poppler-utils are required for this test.');
        }

        // The exact reproduction that surfaced this bug in real-world testing:
        // a plain page, no annotations/fields/embedded fonts, a user password
        // only (no owner password - triggers the auto-generated-owner-password
        // path), default algorithm (AES-256).
        $document = new Document();
        $document->addFont(Font::ARIAL);
        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Hello World', 12), Font::ARIAL, 50, 742);
        $document->addPage($page);
        $document->setSecurity(new Document\Security('12user34'));

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_strf_test_') . '.pdf';
        $compiler = new Compiler();
        $compiler->finalize($document);
        file_put_contents($tmpFile, $compiler->getOutput());

        $info = shell_exec('pdfinfo -upw 12user34 ' . escapeshellarg($tmpFile) . ' 2>&1');
        $textOutput = [];
        exec('pdftotext -upw 12user34 ' . escapeshellarg($tmpFile) . ' - 2>&1', $textOutput, $textStatus);

        unlink($tmpFile);

        $this->assertStringContainsString('algorithm:AES-256', $info);
        $this->assertStringNotContainsString('algorithm:RC4', $info);
        $this->assertStringContainsString('Hello World', implode("\n", $textOutput));
        foreach ($textOutput as $line) {
            $this->assertStringNotContainsString('Syntax Error', $line);
        }
    }

    // /Info dictionary strings are now encrypted to match /StrF /StdCF
    // (restored, alongside this, from the earlier /StrF /Identity
    // workaround - see buildEncryptDictBody()'s docblock). A conforming
    // reader decrypts every literal string once /StrF /StdCF is declared, so
    // the plaintext title must no longer appear literally in the output, and
    // the crypt filter name for strings is /StdCF, not /Identity.
    public function testFinalizeEncryptsInfoStringsAndDeclaresStdCfStringFilter()
    {
        foreach ([Document\Security::AES_128, Document\Security::AES_256] as $algorithm) {
            $doc = new Document();
            $doc->addFont(new Font('Arial'));
            $doc->setSecurity(new Document\Security('open-me', 'admin123', null, $algorithm));
            $doc->setMetadata((new Document\Metadata())->setTitle('A Plaintext Title'));

            $page = new Page(Page::LETTER);
            $doc->addPage($page);

            $compiler = new Compiler();
            $compiler->finalize($doc);
            $output = $compiler->getOutput();

            $this->assertStringContainsString('/StmF /StdCF /StrF /StdCF', $output);
            $this->assertStringNotContainsString('/StrF /Identity', $output);
            $this->assertStringNotContainsString('A Plaintext Title', $output);
        }
    }

    // Annotation\Url's /URI is a literal PDF string, so once /StrF /StdCF is
    // declared (restored by the previous test's fix) it must actually be
    // encrypted - otherwise a conforming reader tries to AES-decrypt
    // plaintext bytes, corrupting the URL into garbage. This is verified
    // with real qpdf AND poppler (not just qpdf, which can't detect the
    // narrower "declared encrypted but not actually encrypted" bug on its
    // own the way poppler's stricter parser can).
    public function testFinalizeEncryptsAnnotationUrlStrings()
    {
        if ((shell_exec('which qpdf') === null) || (shell_exec('which pdftotext') === null)
            || (shell_exec('which pdfinfo') === null)) {
            $this->markTestSkipped('qpdf and poppler-utils are required for this test.');
        }

        foreach ([Document\Security::AES_128, Document\Security::AES_256] as $algorithm) {
            $doc = new Document();
            $doc->addFont(new Font('Arial'));
            $doc->setSecurity(new Document\Security('open-me', 'admin123', null, $algorithm));

            $page = new Page(Page::LETTER);
            $page->addUrl(new Page\Annotation\Url(150, 20, 'https://example.com/annotation-secret'), 50, 400);
            $doc->addPage($page);

            $compiler = new Compiler();
            $compiler->finalize($doc);
            $output = $compiler->getOutput();

            $this->assertStringNotContainsString('https://example.com/annotation-secret', $output);

            $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');
            $this->assertStringContainsString('https://example.com/annotation-secret', $decrypted);

            $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_url_compiler_test_') . '.pdf';
            file_put_contents($tmpFile, $output);

            $info = shell_exec('pdfinfo -upw open-me ' . escapeshellarg($tmpFile) . ' 2>&1');
            $textOutput = [];
            exec('pdftotext -upw open-me ' . escapeshellarg($tmpFile) . ' - 2>&1', $textOutput, $textStatus);

            unlink($tmpFile);

            $this->assertStringNotContainsString('algorithm:RC4', $info);
            $this->assertEquals(0, $textStatus, implode("\n", $textOutput));
            foreach ($textOutput as $line) {
                $this->assertStringNotContainsString('Syntax Error', $line);
            }
        }
    }

    // Form-field /T, /TU, /TM, /V, /DV, and /Opt strings are literal PDF
    // strings too, so once /StrF /StdCF is declared they must actually be
    // encrypted, the same way Annotation\Url's /URI is above - verified with
    // real qpdf AND poppler for the same reason: poppler's stricter parser
    // catches "declared encrypted but not actually encrypted" corruption
    // that qpdf alone cannot.
    public function testFinalizeEncryptsFormFieldStrings()
    {
        if ((shell_exec('which qpdf') === null) || (shell_exec('which pdftotext') === null)
            || (shell_exec('which pdfinfo') === null)) {
            $this->markTestSkipped('qpdf and poppler-utils are required for this test.');
        }

        foreach ([Document\Security::AES_128, Document\Security::AES_256] as $algorithm) {
            $doc = new Document();
            $doc->addFont(new Font('Arial'));
            $doc->setSecurity(new Document\Security('open-me', 'admin123', null, $algorithm));
            $doc->addForm(new Form('contact_form'));

            $page = new Page(Page::LETTER);

            $textField = new Page\Field\Text('secret-field-name', 'Arial', 10);
            $textField->setValue('secret-field-value');
            $textField->setWidth(200);
            $textField->setHeight(24);
            $page->addField($textField, 'contact_form', 50, 200);

            $choiceField = new Page\Field\Choice('secret-choice-name', 'Arial', 10);
            $choiceField->setWidth(200);
            $choiceField->setHeight(24);
            $choiceField->addOption('secret-choice-option');
            $page->addField($choiceField, 'contact_form', 50, 150);

            $doc->addPage($page);

            $compiler = new Compiler();
            $compiler->finalize($doc);
            $output = $compiler->getOutput();

            $this->assertStringNotContainsString('secret-field-name', $output);
            $this->assertStringNotContainsString('secret-field-value', $output);
            $this->assertStringNotContainsString('secret-choice-name', $output);
            $this->assertStringNotContainsString('secret-choice-option', $output);

            $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');
            $this->assertStringContainsString('secret-field-name', $decrypted);
            $this->assertStringContainsString('secret-field-value', $decrypted);
            $this->assertStringContainsString('secret-choice-name', $decrypted);
            $this->assertStringContainsString('secret-choice-option', $decrypted);

            $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_field_compiler_test_') . '.pdf';
            file_put_contents($tmpFile, $output);

            $info = shell_exec('pdfinfo -upw open-me ' . escapeshellarg($tmpFile) . ' 2>&1');
            $textOutput = [];
            exec('pdftotext -upw open-me ' . escapeshellarg($tmpFile) . ' - 2>&1', $textOutput, $textStatus);

            unlink($tmpFile);

            $this->assertStringNotContainsString('algorithm:RC4', $info);
            $this->assertEquals(0, $textStatus, implode("\n", $textOutput));
            foreach ($textOutput as $line) {
                $this->assertStringNotContainsString('Syntax Error', $line);
            }
        }
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

    // Compression and encryption interact: StreamObject::encode() prepends
    // the mandatory post-"stream" EOL to the deflate output, and the
    // encryption pass must strip exactly that one byte before encrypting
    // (Compiler splices its own EOL back in after "stream" once /Length is
    // computed from the untouched ciphertext). Get it wrong and a reader
    // decrypts a payload with a stray leading 0x0A and inflate fails with
    // "incorrect header check" - a failure NO non-qpdf test can see, since
    // this library's own code never inflates what it just wrote. Every other
    // encryption test in this file runs with compression off (the Document
    // default), so these two exist specifically to cover the combination,
    // alongside an image and an embedded font.
    public function testFinalizeVerifiesWithQpdfWithCompressionAndEncryptionAes256()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_256));

        $page = new Page(Page::LETTER);
        $page->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page->addText(new Page\Text('Hello Compressed World', 36), $doc->getCurrentFont(), 50, 400);
        $page->addText(new Page\Text('Hello Compressed World', 12), 'Arial', 50, 350);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $decrypted = $this->assertPassesQpdfCheck($compiler->getOutput(), 'open-me');

        // qpdf --check would still pass on a page whose content stream it
        // could not inflate only if it never tried; asserting the recovered,
        // inflated content stream back proves it really round-tripped.
        $this->assertStringContainsString('Hello Compressed World', $decrypted);
    }

    public function testFinalizeVerifiesWithQpdfWithCompressionAndEncryptionAes128()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_128));

        $page = new Page(Page::LETTER);
        $page->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page->addText(new Page\Text('Hello Compressed World', 36), $doc->getCurrentFont(), 50, 400);
        $page->addText(new Page\Text('Hello Compressed World', 12), 'Arial', 50, 350);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $decrypted = $this->assertPassesQpdfCheck($compiler->getOutput(), 'open-me');
        $this->assertStringContainsString('Hello Compressed World', $decrypted);
    }

    // The OWNER password must open the document too, not just the user
    // password - it is the half of the /O // /U pair that the revision-4
    // Algorithm 3/7 and revision-6 Algorithm 2.B/9 math is easiest to get
    // subtly wrong on, since every other test here authenticates with the
    // user password and would pass with a broken /O.
    public function testFinalizeVerifiesWithQpdfUsingTheOwnerPasswordAes256()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_256));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Owner Password World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $decrypted = $this->assertPassesQpdfCheck($compiler->getOutput(), 'admin123');
        $this->assertStringContainsString('Owner Password World', $decrypted);
    }

    public function testFinalizeVerifiesWithQpdfUsingTheOwnerPasswordAes128()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->addFont(new Font('Arial'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_128));

        $page = new Page(Page::LETTER);
        $page->addText(new Page\Text('Owner Password World', 12), 'Arial', 50, 700);
        $doc->addPage($page);

        $compiler = new Compiler();
        $compiler->finalize($doc);

        $decrypted = $this->assertPassesQpdfCheck($compiler->getOutput(), 'admin123');
        $this->assertStringContainsString('Owner Password World', $decrypted);
    }

    // An embedded font's /CIDSystemInfo carries literal strings
    // ("(Adobe)"/"(Identity)"). Restoring /StrF /StdCF (this task) without
    // also encrypting those strings (a later task in this plan -
    // encryptEmbeddedFontStrings(), not yet implemented) is a KNOWN,
    // temporary regression: a conforming reader now dutifully "decrypts"
    // that plaintext, consuming its first 16 bytes as an AES IV and emptying
    // it out. This is intentional and expected at this point in the plan -
    // /StrF /StdCF must be truthful for every OTHER string category (/Info,
    // fixed by this task) to stop poppler/Chrome from misdetecting the
    // cipher and refusing the whole file, which is a strictly worse, more
    // common failure than this narrower one. The task that adds
    // encryptEmbeddedFontStrings() must flip this assertion back to
    // "survives intact".
    public function testFinalizeCorruptsEmbeddedFontCidSystemInfoStringsThroughQpdfPendingFontStringEncryption()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        foreach ([Document\Security::AES_128, Document\Security::AES_256] as $algorithm) {
            $doc = new Document();
            $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
            $doc->setSecurity(new Document\Security('open-me', 'admin123', null, $algorithm));

            $page = new Page(Page::LETTER);
            $page->addText(new Page\Text('Embedded Font World', 24), $doc->getCurrentFont(), 50, 600);
            $doc->addPage($page);

            $compiler = new Compiler();
            $compiler->finalize($doc);
            $output = $compiler->getOutput();

            // Plaintext going in ...
            $this->assertStringContainsString('/Registry (Adobe) /Ordering (Identity)', $output);

            // ... and, until encryptEmbeddedFontStrings() lands, corrupted by
            // qpdf's decrypt round-trip because /StrF /StdCF is now truthful
            // for /Info but not yet for embedded-font strings. Note: an
            // embedded TrueType font's ToUnicode CMap *stream* also contains
            // a textual "/Registry (Adobe) /Ordering (UCS)" - that one is
            // stream-encrypted (unaffected by this gap) and survives
            // correctly, so this asserts against the dict-only marker
            // "(Identity)" (only the CIDSystemInfo dict says Identity; the
            // CMap stream says UCS) rather than "(Adobe)", which appears in
            // both places and would give a false pass.
            $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');
            $this->assertStringNotContainsString(
                '/Ordering (Identity)', $decrypted,
                "{$algorithm}: /CIDSystemInfo /Ordering unexpectedly survived - " .
                "has encryptEmbeddedFontStrings() been implemented? Update this test."
            );
        }
    }

    // Everything else here drives Compiler directly; this drives the public
    // facade end to end, the way an application actually would.
    public function testPdfWriteToFileProducesAQpdfCleanEncryptedFile()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $doc = new Document();
        $doc->setCompression(true);
        $doc->addFont(new Font('Arial'));
        $doc->embedFont(new Font(__DIR__ . '/../tmp/fonts/times.ttf'));
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_256));

        $page = new Page(Page::LETTER);
        $page->addImage(Page\Image::createImageFromFile(__DIR__ . '/../tmp/images/logo-rgb.jpg'), 50, 600);
        $page->addText(new Page\Text('Facade World', 24), $doc->getCurrentFont(), 50, 400);
        // A standard-font run too: embedded-font text is emitted as
        // hex-encoded glyph ids, so only a standard-font run leaves the
        // literal string in the content stream for the assertion below.
        $page->addText(new Page\Text('Facade World', 12), 'Arial', 50, 350);
        $doc->addPage($page);

        $file = tempnam(sys_get_temp_dir(), 'pop_pdf_facade_test_') . '.pdf';
        Pdf\Pdf::writeToFile($doc, $file);

        $this->assertFileExists($file);
        $written = (string)file_get_contents($file);
        unlink($file);

        $this->assertStringContainsString('/Encrypt', $written);
        $decrypted = $this->assertPassesQpdfCheck($written, 'open-me');
        $this->assertStringContainsString('Facade World', $decrypted);
    }

    // Build\Parser/Build\Merger deliberately leave a source PDF's declared
    // /Length untouched when it is an indirect reference (e.g. "6 0 R"),
    // which is common in PDFs produced by other tools - so an imported or
    // merged document's content stream can reach the encryption pass with
    // an indirect /Length intact. That is exactly the shape a regex that
    // infers "how many bytes to keep" from the /Length text itself gets
    // wrong (it would capture "6", an unrelated object number, not a byte
    // count) - StreamObject::getLeadingEolLength() sidesteps that by having
    // parse()/translateGeneric() record the padding directly instead.
    public function testFinalizeEncryptsImportedDocumentWithIndirectLengthContentStream()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $content = "BT /F1 12 Tf 50 700 Td (Hello Indirect Length) Tj ET";
        $pdfData = $this->buildIndirectLengthContentPdf($content);

        $doc = Pdf\Pdf::importRawData($pdfData);
        $doc->setSecurity(new Document\Security('open-me', 'admin123'));

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/Encrypt', $output);
        $this->assertStringNotContainsString($content, $output);

        $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');
        $this->assertStringContainsString($content, $decrypted);
    }

    public function testFinalizeEncryptsMergedDocumentWithIndirectLengthContentStream()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $content  = "BT /F1 12 Tf 50 700 Td (Hello Merged Indirect Length) Tj ET";
        $content2 = "BT /F1 12 Tf 50 650 Td (Second Merged Source) Tj ET";

        // Merging requires at least 2 source documents - both built with an
        // indirect-length content stream, so the shape under test survives
        // the merge's own object-graph rewriting from more than one source.
        $doc = Pdf\Pdf::mergeRawData(
            [$this->buildIndirectLengthContentPdf($content), $this->buildIndirectLengthContentPdf($content2)],
            new Document()
        );
        $doc->setSecurity(new Document\Security('open-me', 'admin123', null, Document\Security::AES_128));

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/Encrypt', $output);
        $this->assertStringNotContainsString($content, $output);
        $this->assertStringNotContainsString($content2, $output);

        $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');
        $this->assertStringContainsString($content, $decrypted);
        $this->assertStringContainsString($content2, $decrypted);
    }

    // Round 2's fix (StreamObject::getLeadingEolLength()) correctly strips
    // leading padding before encrypting regardless of whether /Length is
    // literal or indirect - but that is a separate concern from the
    // POST-encryption /Length rewrite for Image/Length1 objects (which
    // StreamObject::__toString() never recomputes dynamically for those two
    // object types). An imported image XObject with an indirect /Length
    // (e.g. "/Length 7 0 R", common in PDFs produced by other tools) needs
    // that indirect span replaced with a fresh literal matching the
    // encrypted byte count, exactly like a literal /Length does - otherwise
    // the object it points to keeps declaring the OLD, pre-encryption
    // length, and a strict reader can't even attempt AES-CBC on a
    // non-block-aligned buffer.
    public function testFinalizeEncryptsImportedImageWithIndirectLength()
    {
        if (shell_exec('which qpdf') === null) {
            $this->markTestSkipped('qpdf is not installed - install it to run this interoperability check.');
        }

        $jpegBytes        = (string)file_get_contents(__DIR__ . '/../tmp/images/logo-rgb.jpg');
        [$width, $height] = getimagesize(__DIR__ . '/../tmp/images/logo-rgb.jpg');
        $pdfData          = $this->buildImageIndirectLengthPdf($jpegBytes, $width, $height);

        $doc = Pdf\Pdf::importRawData($pdfData);
        $doc->setSecurity(new Document\Security('open-me', 'admin123'));

        $compiler = new Compiler();
        $compiler->finalize($doc);
        $output = $compiler->getOutput();

        $this->assertStringContainsString('/Encrypt', $output);
        $this->assertStringNotContainsString($jpegBytes, $output);

        $decrypted = $this->assertPassesQpdfCheck($output, 'open-me');

        // The recovered image bytes must be byte-for-byte identical to the
        // source JPEG, not merely "some bytes qpdf could recover" - qpdf's
        // own stream-length-recovery heuristic can mask a corrupt /Length
        // by re-deriving the boundary from the JPEG's own EOI marker, so
        // passing --check alone would not prove the declared /Length is
        // actually correct.
        $imageStart = strpos($decrypted, "\xFF\xD8\xFF");
        $this->assertNotFalse($imageStart, 'Decrypted output does not contain a JPEG SOI marker.');
        $this->assertStringContainsString($jpegBytes, substr($decrypted, $imageStart, strlen($jpegBytes) + 64));
    }

    /**
     * Build a minimal single-page raw PDF containing one JPEG image XObject
     * whose /Length is an INDIRECT reference (e.g. "/Length 7 0 R", pointing
     * at a separate object holding the plain integer byte count) rather
     * than a literal one - the shape that exposed a stale post-encryption
     * /Length rewrite for Image/Length1 objects specifically.
     *
     * @param  string $jpegBytes
     * @param  int    $width
     * @param  int    $height
     * @return string
     */
    protected function buildImageIndirectLengthPdf(string $jpegBytes, int $width, int $height): string
    {
        $content = "q {$width} 0 0 {$height} 50 600 cm /Im0 Do Q";

        $objs = [];
        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objs[2] = "2 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";
        $objs[4] = "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] " .
            "/Resources << /XObject << /Im0 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";
        $objs[5] = "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} " .
            "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length 7 0 R >>\nstream\n" .
            $jpegBytes . "\nendstream\nendobj\n";
        $objs[6] = "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
        $objs[7] = "7 0 obj\n" . strlen($jpegBytes) . "\nendobj\n";
        ksort($objs);

        $header  = "%PDF-1.4\n";
        $offsets = [];
        $cur     = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $maxObj  = max(array_keys($objs));
        $xref    = "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $header . $body . $xref;
    }

    /**
     * Build a minimal single-page raw PDF whose content stream declares an
     * INDIRECT /Length (e.g. "/Length 6 0 R", pointing at a separate object
     * holding the plain integer) rather than a literal one - the shape that
     * regressed the encryption pass's leading-padding stripping logic.
     *
     * @param  string $content
     * @return string
     */
    protected function buildIndirectLengthContentPdf(string $content): string
    {
        $objs = [];
        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objs[2] = "2 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";
        $objs[3] = "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objs[4] = "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] " .
            "/Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objs[5] = "5 0 obj\n<< /Length 6 0 R >>\nstream\n{$content}\nendstream\nendobj\n";
        $objs[6] = "6 0 obj\n" . strlen($content) . "\nendobj\n";
        ksort($objs);

        $header  = "%PDF-1.4\n";
        $offsets = [];
        $cur     = $header;
        foreach ($objs as $n => $o) {
            $offsets[$n] = strlen($cur);
            $cur .= $o;
        }
        $body    = implode('', $objs);
        $xrefPos = strlen($header . $body);
        $maxObj  = max(array_keys($objs));
        $xref    = "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $xref .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $xref .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $header . $body . $xref;
    }

    /**
     * Write $pdfData to a temp file and assert both `qpdf --check` and
     * `qpdf --decrypt` succeed against it with the given user password.
     * qpdf --check requires the password whenever a non-empty user password
     * is set (as it is here) - without one it correctly (and always) reports
     * "invalid password", even against a PDF qpdf itself encrypted, so the
     * password is supplied to both invocations.
     *
     * Returns the decrypted file's contents, so callers can additionally
     * assert on the recovered plaintext (e.g. exact content-stream bytes).
     *
     * @param  string $pdfData
     * @param  string $password
     * @return string
     */
    protected function assertPassesQpdfCheck(string $pdfData, string $password): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop_pdf_encrypt_test_') . '.pdf';
        file_put_contents($tmpFile, $pdfData);

        exec(
            'qpdf --password=' . escapeshellarg($password) . ' --check ' . escapeshellarg($tmpFile) . ' 2>&1',
            $checkOutput, $checkStatus
        );
        // --decode-level=generalized --compress-streams=n keeps the
        // decrypted output's streams uncompressed, so a caller asserting on
        // the recovered plaintext (e.g. an exact content-stream string) sees
        // it directly rather than qpdf's own re-flate-compressed bytes.
        exec(
            'qpdf --password=' . escapeshellarg($password) . ' --decrypt --decode-level=generalized ' .
            '--compress-streams=n ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($tmpFile . '.dec') . ' 2>&1',
            $decryptOutput, $decryptStatus
        );

        $decrypted = file_exists($tmpFile . '.dec') ? (string)file_get_contents($tmpFile . '.dec') : '';

        unlink($tmpFile);
        if (file_exists($tmpFile . '.dec')) {
            unlink($tmpFile . '.dec');
        }

        $this->assertEquals(0, $checkStatus, implode("\n", $checkOutput));
        $this->assertEquals(0, $decryptStatus, implode("\n", $decryptOutput));

        return $decrypted;
    }

}