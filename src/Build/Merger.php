<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Build;

use Pop\Pdf\Document;
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
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Merger
{

    /**
     * Merge PDF files into one document
     *
     * @param  array    $files
     * @param  Document $document
     * @param  array    $passwords per-source decryption passwords, keyed the same way as $files
     *                             (e.g. [1 => 'secret'] to supply a password only for $files[1]);
     *                             a source with no entry (or a null entry) is opened with no password.
     * @throws Exception
     * @return AbstractDocument
     */
    public function mergeFiles(array $files, Document $document = new Document(), array $passwords = []): AbstractDocument
    {
        $sources = [];

        foreach ($files as $index => $file) {
            if (!file_exists($file)) {
                throw new Exception("Error: The PDF file '{$file}' does not exist.");
            }
            try {
                $sources[] = ExtractDocument::fromFile($file, $passwords[$index] ?? null);
            } catch (\Pop\Pdf\Extract\Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->mergeSources($sources, $document);
    }

    /**
     * Merge raw PDF data streams into one document
     *
     * @param  array    $dataList
     * @param  Document $document
     * @param  array    $passwords per-source decryption passwords, keyed the same way as $dataList
     *                             (e.g. [1 => 'secret'] to supply a password only for $dataList[1]);
     *                             a source with no entry (or a null entry) is opened with no password.
     * @throws Exception
     * @return AbstractDocument
     */
    public function mergeData(array $dataList, Document $document = new Document(), array $passwords = []): AbstractDocument
    {
        $sources = [];

        foreach ($dataList as $index => $data) {
            try {
                $sources[] = new ExtractDocument($data, $passwords[$index] ?? null);
            } catch (\Pop\Pdf\Extract\Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->mergeSources($sources, $document);
    }

    /**
     * Merge a set of already-parsed Extract\Document sources
     *
     * @param  array    $sources
     * @param  Document $document
     * @throws Exception
     * @return AbstractDocument
     */
    protected function mergeSources(array $sources, Document $document = new Document()): AbstractDocument
    {
        if (count($sources) < 2) {
            throw new Exception('Error: Merging requires at least 2 source PDF documents.');
        }

        try {
            $graphs           = [];
            $objectLists      = [];
            $pageObjectLists  = [];
            $offset           = 0;

            foreach ($sources as $source) {
                $graph              = Import\ObjectGraphReader::read($source, $offset);
                $graphs[]           = $graph;
                $objectLists[]      = $graph['objects'];
                $pageObjectLists[]  = $graph['pageObjects'];
                $offset             = $graph['nextOffset'];
            }

            // Object arrays are keyed by object number, and array_merge()
            // would renumber integer keys, so a union preserving those keys
            // is required. array_replace() lets a later argument overwrite
            // an earlier one on key collision, which is the opposite of the
            // '+=' union semantics being replicated here (first source
            // wins), so the collected lists are combined in reverse order.
            $allObjects = array_replace(...array_reverse($objectLists));
            $allPages   = array_merge(...$pageObjectLists);
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

        // Deferred rather than set directly: a target document passed in by
        // the caller may already have its own pages, which only get their
        // kid indices assigned later during compilation - deferring these
        // lets Compiler::finalize() append them after that happens, so the
        // target document's existing pages land before the merged content
        // rather than after it.
        $masterParent = new PdfObject\ParentObject($masterObjNum);
        $masterParent->setDeferredKids($masterKids);
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

        $document->importObjects($allObjects);

        foreach ($allPages as $pageObject) {
            $page = new \Pop\Pdf\Document\Page($pageObject->getWidth(), $pageObject->getHeight(), $pageObject->getIndex());
            $page->importPageObject($pageObject);
            $document->addPage($page);
        }

        return $document;
    }

}
