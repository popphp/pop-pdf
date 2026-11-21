<?php

namespace Pop\Pdf\Test\Build\Import;

use Pop\Pdf\Build\Import\ImportedForm;
use Pop\Pdf\Extract\Value;
use PHPUnit\Framework\TestCase;

class ImportedFormTest extends TestCase
{

    /**
     * A text field (1, its own widget) and a radio group (2) whose widgets
     * 3 and 4 sit on different pages
     */
    protected function buildForm(): ImportedForm
    {
        $form = new ImportedForm();
        $form->addNode(1, ['T' => 'name'], null, [10]);
        $form->addNode(2, ['T' => 'plan', 'Kids' => [new Value\Reference(3, 0), new Value\Reference(4, 0)]], null, []);
        $form->addNode(3, ['Parent' => new Value\Reference(2, 0)], 2, [11]);
        $form->addNode(4, ['Parent' => new Value\Reference(2, 0)], 2, [12]);
        $form->setFields([1, 2]);
        $form->setSettings(['NeedAppearances' => true], [20]);

        return $form;
    }

    public function testKeptNodesAreTheWidgetsAndTheirAncestors()
    {
        $form = $this->buildForm();

        // 99 is some other annotation, not part of the form
        $this->assertEquals([3, 2], array_keys($form->getKeptNodes([3, 99])));
        $this->assertEquals([1, 4, 2], array_keys($form->getKeptNodes([1, 4])));
        $this->assertEquals([], $form->getKeptNodes([99]));
    }

    public function testKeptFieldsKeepTheSourceOrder()
    {
        $form = $this->buildForm();

        $this->assertEquals([1, 2], $form->getKeptFields($form->getKeptNodes([4, 1])));
        $this->assertEquals([2], $form->getKeptFields($form->getKeptNodes([3])));
    }

    public function testNodeDictionaryKidsAreNarrowedToKeptNodes()
    {
        $form = $this->buildForm();
        $dict = $form->getNodeDictionary(2, $form->getKeptNodes([4]));

        $this->assertCount(1, $dict['Kids']);
        $this->assertEquals(4, $dict['Kids'][0]->objNum);
    }

    public function testUsedObjectsIncludeSettingsOnlyWhenANodeIsKept()
    {
        $form = $this->buildForm();

        $used = $form->getUsedObjectIndices($form->getKeptNodes([3]));
        sort($used);
        $this->assertEquals([11, 20], $used);
        $this->assertEquals([], $form->getUsedObjectIndices([]));
        $this->assertEquals(['NeedAppearances' => true], $form->getSettings());
    }

}
