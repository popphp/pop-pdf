<?php

namespace Pop\Pdf\Test\Document\Page;

use Pop\Pdf\Document\Page\Field;
use Pop\Pdf\Document\Page\Color;
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
        $this->assertInstanceOf('Pop\Pdf\Document\Page\Color\Rgb', $field->getFontColor());
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
        $this->assertContains('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
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

        $this->assertContains('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testTextGrayFontColor()
    {
        $field = new Field\Text('name', 'Arial', 14);
        $field->setWidth(200);
        $field->setHeight(24);
        $field->setFontColor(new Color\Gray(100));
        $field->setMultiline()
            ->setPassword()
            ->setFileSelect()
            ->setDoNotSpellCheck()
            ->setDoNotScroll()
            ->setComb()
            ->setRichText();

        $this->assertContains('/FT /Tx', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
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

        $this->assertContains('/FT /Tx', $field->getStream(10, 2, null, 20, 200));
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
        $this->assertContains('/FT /Btn', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testButtonGrayFontColor()
    {
        $field = new Field\Button('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Gray(100));
        $field->setNoToggleToOff()
              ->setRadio()
              ->setPushButton()
              ->setRadiosInUnison();

        $this->assertContains('/FT /Btn', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
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

        $this->assertContains('/FT /Btn', $field->getStream(10, 2, null, 20, 200));
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
        $this->assertContains('/FT /Ch', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
    }

    public function testChoiceGrayFontColor()
    {
        $field = new Field\Choice('name');
        $field->setWidth(200);
        $field->setHeight(24);
        $field->addOption('Option');
        $field->setFontColor(new Color\Gray(100));
        $field->setCombo()
            ->setEdit()
            ->setSort()
            ->setMultiSelect()
            ->setDoNotSpellCheck()
            ->setCommitOnSelChange();

        $this->assertContains('/FT /Ch', $field->getStream(10, 2, '/MF1 1 0 R', 20, 200));
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

        $this->assertContains('/FT /Ch', $field->getStream(10, 2, null, 20, 200));
    }

}