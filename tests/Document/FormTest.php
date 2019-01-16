<?php

namespace Pop\Pdf\Test\Document;

use Pop\Pdf\Document\Form;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{

    public function testAddFieldIndex()
    {
        $form = new Form('contact');
        $form->addFieldIndex(1);
        $this->assertEquals(1, count($form->getFieldIndices()));
        $this->assertEquals(1, $form->getNumberOfFields());
        $this->assertContains('1 0 obj', $form->getStream(1));
        $this->assertContains('<</Fields', $form->getStream(1));
    }

}