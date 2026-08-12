<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

use Pop\Pdf\Document\AbstractDocument;
use Pop\Pdf\Extract\Document as ExtractDocument;
use Pop\Pdf\Extract\Value;

/**
 * Pdf merger class
 *
 * Combines whole PDF documents into one, natively - no external
 * dependencies. Each source is read via the same ObjectGraphReader used by
 * Build\Parser, at an increasing per-source object-number offset, then each
 * source's entire original /Pages subtree is spliced under one new master
 * /Pages node.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Merger
{

    /**
     * Merge PDF files into one document
     *
     * @param  array $files
     * @throws Exception
     * @return AbstractDocument
     */
    public function mergeFiles(array $files): AbstractDocument
    {
        $sources = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new Exception("Error: The PDF file '{$file}' does not exist.");
            }
            try {
                $sources[] = ExtractDocument::fromFile($file);
            } catch (\Pop\Pdf\Extract\Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->mergeSources($sources);
    }

    /**
     * Merge raw PDF data streams into one document
     *
     * @param  array $dataList
     * @throws Exception
     * @return AbstractDocument
     */
    public function mergeData(array $dataList): AbstractDocument
    {
        $sources = [];

        foreach ($dataList as $data) {
            try {
                $sources[] = new ExtractDocument($data);
            } catch (\Pop\Pdf\Extract\Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->mergeSources($sources);
    }

    /**
     * Merge a set of already-parsed Extract\Document sources
     *
     * @param  array $sources
     * @throws Exception
     * @return AbstractDocument
     */
    protected function mergeSources(array $sources): AbstractDocument
    {
        if (count($sources) < 2) {
            throw new Exception('Error: Merging requires at least 2 source PDF documents.');
        }

        try {
            $allObjects = [];
            $allPages   = [];
            $graphs     = [];
            $offset     = 0;

            foreach ($sources as $source) {
                $graph        = Import\ObjectGraphReader::read($source, $offset);
                $graphs[]     = $graph;
                $allObjects  += $graph['objects'];
                $allPages     = array_merge($allPages, $graph['pageObjects']);
                $offset       = $graph['nextOffset'];
            }
        } catch (\Pop\Pdf\Extract\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $masterObjNum = $offset + 1;
        $rootObjNum   = $masterObjNum + 1;
        $infoObjNum   = $rootObjNum + 1;
        $masterKids   = [];

        foreach ($graphs as $graph) {
            $dict            = $graph['topPagesDict'];
            $dict['Parent']  = new Value\Reference($masterObjNum, 0);

            $streamObject = new PdfObject\StreamObject($graph['topPagesObjNum']);
            $streamObject->setDefinition(Import\ObjectSerializer::serializeDict($dict));
            $streamObject->setImported(true);

            $allObjects[$graph['topPagesObjNum']] = $streamObject;
            $masterKids[]                         = $graph['topPagesObjNum'];
        }

        $masterParent = new PdfObject\ParentObject($masterObjNum);
        $masterParent->setKids($masterKids);
        $masterParent->setCount(count($allPages));
        $masterParent->setImported(true);
        $allObjects[$masterObjNum] = $masterParent;

        // Compiler::setDocument() synthesizes its own default RootObject
        // (hardcoded index 1, pointing at hardcoded Pages index 2) and
        // InfoObject (hardcoded index 3) whenever the document's imported
        // objects don't already include one - unconditionally overwriting
        // whatever real merged object landed at that number. Both must be
        // supplied explicitly, at guaranteed-collision-free numbers beyond
        // everything already allocated (this exact bug was found and fixed
        // in Build\Parser during Task 6's review - Merger repeats the same
        // assembly shape and needs the same fix built in from the start).
        $root = new PdfObject\RootObject($rootObjNum);
        $root->setParentIndex($masterObjNum);
        $root->setImported(true);
        $allObjects[$rootObjNum] = $root;

        $info = new PdfObject\InfoObject($infoObjNum);
        $info->setImported(true);
        $allObjects[$infoObjNum] = $info;

        $document = new \Pop\Pdf\Document();
        $document->importObjects($allObjects);

        foreach ($allPages as $pageObject) {
            $page = new \Pop\Pdf\Document\Page($pageObject->getWidth(), $pageObject->getHeight(), $pageObject->getIndex());
            $page->importPageObject($pageObject);
            $document->addPage($page);
        }

        return $document;
    }

}
