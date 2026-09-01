<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Field;
use Pop\Color\Color;;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{

    public function testConstructor()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setFontColor(new Color\Rgb(0, 0, 0));
        $field->setValue('My Name');
        $field->setDefaultValue('My Default Name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setReadOnly();
        $field->setRequired();
        $field->setNoExport();
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Field\Text', $field);
        $this->assertInstanceOf('Pop\Color\Color\Rgb', $field->getFontColor());
        $this->assertEquals('name', $field->getName());
        $this->assertEquals('My Name', $field->getValue());
        $this->assertEquals('My Default Name', $field->getDefaultValue());
        $this->assertEquals('Arial', $field->getFont());
        $this->assertEquals(14, $field->getSize());
        $this->assertEquals(200, $field->getWidth());
        $this->assertEquals(24, $field->getHeight());
    }

    public function testText()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Rgb(0, 0, 0));
        $field->setMultiline()
              ->setPassword()
              ->setFileSelect()
              ->setDoNotSpellCheck()
              ->setDoNotScroll()
              ->setComb()
              ->setRichText();


        $this->assertTrue($field->isPassword());
        $this->assertTrue($field->isMultiline());
        $this->assertStringContainsString('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testTextCmykFontColor()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Cmyk(100, 0, 0, 0));
        $field->setMultiline()
            ->setPassword()
            ->setFileSelect()
            ->setDoNotSpellCheck()
            ->setDoNotScroll()
            ->setComb()
            ->setRichText();

        $this->assertStringContainsString('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testTextGrayFontColor()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Grayscale(100));
        $field->setMultiline()
            ->setPassword()
            ->setFileSelect()
            ->setDoNotSpellCheck()
            ->setDoNotScroll()
            ->setComb()
            ->setRichText();

        $this->assertStringContainsString('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testTextNoFont()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setMultiline()
            ->setPassword()
            ->setFileSelect()
            ->setDoNotSpellCheck()
            ->setDoNotScroll()
            ->setComb()
            ->setRichText();

        $this->assertStringContainsString('/FT /Tx', $field->getStream(10, 2, null, 20, 200));
    }

    public function testTextValueIsQuotedAndEscaped()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setValue('Hello (World)');
        $field->setDefaultValue('Default (Value)');

        $stream = $field->getStream(10, 2, '/MF1 1 0 R', 20, 200);

        $this->assertStringContainsString('/V (Hello \(World\))', $stream);
        $this->assertStringContainsString('/DV (Default \(Value\))', $stream);
    }

    public function testButton()
    {
        $field = new Field\Button('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Rgb(0, 0, 0));
        $field->setNoToggleToOff()
              ->setRadio()
              ->setPushButton()
              ->setRadiosInUnison();

        $this->assertTrue($field->isRadio());
        $this->assertTrue($field->isPushButton());
        $this->assertTrue($field->hasOptions());
        $this->assertStringContainsString('/FT /Btn', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testButtonGrayFontColor()
    {
        $field = new Field\Button('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Grayscale(100));
        $field->setNoToggleToOff()
              ->setRadio()
              ->setPushButton()
              ->setRadiosInUnison();

        $this->assertStringContainsString('/FT /Btn', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testButtonNoOptionsNoFont()
    {
        $field = new Field\Button('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Cmyk(100, 0, 0, 0));
        $field->setNoToggleToOff()
              ->setRadio()
              ->setPushButton()
              ->setRadiosInUnison();

        $this->assertStringContainsString('/FT /Btn', $field->getStream(10, 2, null, 20, 200));
    }

    public function testChoice()
    {
        $field = new Field\Choice('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Rgb(0, 0, 0));
        $field->setCombo()
              ->setEdit()
              ->setSort()
              ->setMultiSelect()
              ->setDoNotSpellCheck()
              ->setCommitOnSelChange();

        $this->assertTrue($field->isCombo());
        $this->assertTrue($field->isMultiSelect());
        $this->assertTrue($field->hasOptions());
        $this->assertStringContainsString('/FT /Ch', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testChoiceGrayFontColor()
    {
        $field = new Field\Choice('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Grayscale(100));
        $field->setCombo()
            ->setEdit()
            ->setSort()
            ->setMultiSelect()
            ->setDoNotSpellCheck()
            ->setCommitOnSelChange();

        $this->assertStringContainsString('/FT /Ch', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testChoiceNoOptionsNoFont()
    {
        $field = new Field\Choice('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Cmyk(100, 0, 0, 0));
        $field->setCombo()
            ->setEdit()
            ->setSort()
            ->setMultiSelect()
            ->setDoNotSpellCheck()
            ->setCommitOnSelChange();

        $this->assertStringContainsString('/FT /Ch', $field->getStream(10, 2, null, 20, 200));
    }

    public function testTextEncryptWithReplacesNameAndValueWithEncryptedEscapedBytes()
    {
        $field = new Field\Text('secret-field-name', 'Arial', 12);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setValue('secret-value');
        // strrev() is used as a stand-in "encryptor" precisely because it
        // transforms the bytes - unlike a mere "ENC[...]" wrapper, the
        // transformed output can never contain the original plaintext as a
        // substring, so assertStringNotContainsString() below is meaningful.
        $field->encryptWith(fn (string $data) => strrev($data));

        $stream = $field->getStream(5, 3, null, 10, 20);

        $this->assertStringNotContainsString('secret-field-name', $stream);
        $this->assertStringNotContainsString('secret-value', $stream);
        $this->assertStringContainsString(strrev('secret-field-name'), $stream);
        $this->assertStringContainsString(strrev('secret-value'), $stream);
    }

    public function testTextEncryptWithEncryptsTheComputedDaStringNotAPlaceholder()
    {
        // /DA's content depends on the font reference passed into getStream(),
        // not known at encryptWith() call time - this proves the encryptor is
        // applied to the REAL, fully-computed DA string, not something stale.
        $field = new Field\Text('name', 'Arial', 12);
        $field->setWidth(200);
        $field->setHeight(24);
        $seen  = [];
        $field->encryptWith(function (string $data) use (&$seen) {
            $seen[] = $data;
            return "ENC[{$data}]";
        });

        $stream = $field->getStream(5, 3, 'MF1 1 0 R', 10, 20);

        $this->assertStringContainsString('MF1 12 Tf', $seen[0] ?? '');
        $this->assertStringContainsString('ENC[', $stream);
    }

    public function testTextGetStreamUsesPlainValuesWhenNotEncrypted()
    {
        $field = new Field\Text('plain-name', 'Arial', 12);
        $field->setWidth(200);
        $field->setHeight(24);
        $stream = $field->getStream(5, 3, null, 10, 20);
        $this->assertStringContainsString('plain-name', $stream);
    }

    // /T, /TU, and /TM never escaped their value at all before
    // AbstractField::encryptLiteral() centralized escaping for every
    // literal string this class emits - a disclosed side effect of this
    // change. This confirms that gap is now closed even in the unencrypted
    // case (no encryptWith() call at all).
    public function testTextNameIsEscapedEvenWhenNotEncrypted()
    {
        $field = new Field\Text('Name (With) \\Parens', 'Arial', 12);
        $field->setWidth(200);
        $field->setHeight(24);

        $stream = $field->getStream(5, 3, null, 10, 20);

        $this->assertStringContainsString('/T(Name \(With\) \\\\Parens)', $stream);
        $this->assertStringContainsString('/TU(Name \(With\) \\\\Parens)', $stream);
        $this->assertStringContainsString('/TM(Name \(With\) \\\\Parens)', $stream);
    }

    public function testChoiceEncryptWithEncryptsNameAndOptionsButNotValue()
    {
        $field = new Field\Choice('choice-name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('secret-option');
        $field->setValue('/PlainNameValue');
        $field->encryptWith(fn (string $data) => strrev($data));

        $stream = $field->getStream(5, 3, 'MF1 1 0 R', 10, 20);

        $this->assertStringNotContainsString('choice-name', $stream);
        $this->assertStringNotContainsString('secret-option', $stream);
        $this->assertStringContainsString(strrev('choice-name'), $stream);
        $this->assertStringContainsString(strrev('secret-option'), $stream);
        // /V is a bare PDF Name here, not a string literal - must never be
        // routed through encryptLiteral().
        $this->assertStringContainsString('/V /PlainNameValue', $stream);
    }

    public function testChoiceNameIsEscapedEvenWhenNotEncrypted()
    {
        $field = new Field\Choice('Name (With) \\Parens');
        $field->setWidth(200);
        $field->setHeight(24);

        $stream = $field->getStream(5, 3, null, 10, 20);

        $this->assertStringContainsString('/T(Name \(With\) \\\\Parens)', $stream);
    }

    public function testButtonEncryptWithEncryptsNameAndOptionsButNotValue()
    {
        $field = new Field\Button('button-name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('secret-option');
        $field->setValue('/PlainNameValue');
        $field->encryptWith(fn (string $data) => strrev($data));

        $stream = $field->getStream(5, 3, 'MF1 1 0 R', 10, 20);

        $this->assertStringNotContainsString('button-name', $stream);
        $this->assertStringNotContainsString('secret-option', $stream);
        $this->assertStringContainsString(strrev('button-name'), $stream);
        $this->assertStringContainsString(strrev('secret-option'), $stream);
        // /V is a bare PDF Name here, not a string literal - must never be
        // routed through encryptLiteral().
        $this->assertStringContainsString('/V /PlainNameValue', $stream);
    }

    public function testButtonNameIsEscapedEvenWhenNotEncrypted()
    {
        $field = new Field\Button('Name (With) \\Parens');
        $field->setWidth(200);
        $field->setHeight(24);

        $stream = $field->getStream(5, 3, null, 10, 20);

        $this->assertStringContainsString('/T(Name \(With\) \\\\Parens)', $stream);
    }

    public function testButtonAppearanceStateRendersApAndAs()
    {
        $field = new Field\Button('subscribe');
        $field->setWidth(14);
        $field->setHeight(14);

        $stream = $field->getStream(10, 2, null, 20, 200, [
            'onName' => 'Yes', 'onRef' => '15 0 R', 'offRef' => '16 0 R', 'checked' => true
        ]);

        $this->assertStringContainsString('/AP << /N << /Yes 15 0 R /Off 16 0 R >> >>', $stream);
        $this->assertStringContainsString('/AS /Yes', $stream);
        // /V must agree with /AS - both are the same sanitized Name - so a
        // conforming reader renders the widget as checked.
        $this->assertStringContainsString('/V /Yes', $stream);
    }

    public function testButtonAppearanceStateUncheckedUsesOffState()
    {
        $field = new Field\Button('subscribe');
        $field->setWidth(14);
        $field->setHeight(14);

        $stream = $field->getStream(10, 2, null, 20, 200, [
            'onName' => 'Yes', 'onRef' => '15 0 R', 'offRef' => '16 0 R', 'checked' => false
        ]);

        $this->assertStringContainsString('/AS /Off', $stream);
        $this->assertStringContainsString('/V /Off', $stream);
    }

    public function testButtonAppearanceValueMatchesAsWhenOnNameHasMultipleWords()
    {
        $field = new Field\Button('choice');
        $field->setWidth(14);
        $field->setHeight(14);
        // A raw value like "Option A" is sanitized (e.g. by the compiler)
        // into a single-token Name before it ever reaches getStream() -
        // /V and /AS must both use that sanitized token, never the raw
        // multi-word string, since a bare "/V Option A" is not valid PDF
        // dictionary syntax.
        $stream = $field->getStream(10, 2, null, 20, 200, [
            'onName' => 'Option_A', 'onRef' => '15 0 R', 'offRef' => '16 0 R', 'checked' => true
        ]);

        $this->assertStringContainsString('/AS /Option_A', $stream);
        $this->assertStringContainsString('/V /Option_A', $stream);
        $this->assertStringNotContainsString('/V Option A', $stream);
    }

    public function testButtonPushButtonValueIsUnaffectedByAppearanceFix()
    {
        // Push buttons never pass an $appearance array, so their /V must
        // keep rendering the raw (bare-Name) $this->value exactly as before.
        $field = new Field\Button('submit');
        $field->setWidth(14);
        $field->setHeight(14);
        $field->setPushButton();
        $field->setValue('/PlainNameValue');

        $stream = $field->getStream(10, 2, null, 20, 200);

        $this->assertStringContainsString('/V /PlainNameValue', $stream);
    }

    public function testButtonWithParentIndexOmitsNameAndFlags()
    {
        $field = new Field\Button('plan');
        $field->setWidth(14);
        $field->setHeight(14);
        $field->setRadio();

        $stream = $field->getStream(10, 2, null, 20, 200, null, 3);

        $this->assertStringContainsString('/Parent 3 0 R', $stream);
        $this->assertStringNotContainsString('/T(plan)', $stream);
        $this->assertStringNotContainsString('/Ff', $stream);
    }

    public function testButtonGetParentFieldStream()
    {
        $field = new Field\Button('plan');
        $field->setRadio();

        $stream = $field->getParentFieldStream(7, 'us');

        $this->assertStringStartsWith('7 0 obj', $stream);
        $this->assertStringContainsString('/FT /Btn', $stream);
        $this->assertStringContainsString('/T(plan)', $stream);
        $this->assertStringContainsString('/V /us', $stream);
        $this->assertStringNotContainsString('/Rect', $stream);
    }

    public function testTextAppearanceRendersBorderAndBackground()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setBorderWidth(2);
        $field->setBorderColor([204, 204, 204]);
        $field->setBackgroundColor([255, 255, 0]);

        $stream = $field->getStream(10, 2, null, 20, 200);

        $this->assertEquals(2, $field->getBorderWidth());
        $this->assertEquals([204, 204, 204], $field->getBorderColor());
        $this->assertEquals([255, 255, 0], $field->getBackgroundColor());
        $this->assertStringContainsString('/MK <<', $stream);
        $this->assertStringContainsString('/BC [0.8 0.8 0.8]', $stream);
        $this->assertStringContainsString('/BG [1 1 0]', $stream);
        $this->assertStringContainsString('/BS << /W 2 >>', $stream);
    }

    public function testTextAppearanceOmittedWhenNotSet()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);

        $stream = $field->getStream(10, 2, null, 20, 200);

        $this->assertStringNotContainsString('/MK', $stream);
        $this->assertStringNotContainsString('/BS', $stream);
    }

    public function testChoiceAndButtonAlsoRenderAppearance()
    {
        $choice = new Field\Choice('name');
        $choice->setWidth(150);
        $choice->setHeight(20);
        $choice->setBorderColor([0, 0, 0]);
        $this->assertStringContainsString('/BC [0 0 0]', $choice->getStream(1, 1, null, 0, 0));

        $button = new Field\Button('name');
        $button->setWidth(14);
        $button->setHeight(14);
        $button->setBackgroundColor([255, 255, 255]);
        $this->assertStringContainsString('/BG [1 1 1]', $button->getStream(2, 1, null, 0, 0));
    }

    public function testChoiceAddOptionWithSeparateLabel()
    {
        $field = new Field\Choice('country');
        $field->addOption('us', 'United States');
        $field->addOption('same-value'); // no label - unchanged flat form

        $stream = $field->getStream(1, 1, null, 0, 0);

        $this->assertStringContainsString('/Opt [ [(us) (United States)] (same-value) ]', $stream);
    }

}