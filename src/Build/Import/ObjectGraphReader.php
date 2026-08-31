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
namespace Pop\Pdf\Build\Import;

use Pop\Pdf\Build\Exception;
use Pop\Pdf\Build\PdfObject;
use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Value;

/**
 * Pdf build import object graph reader class
 *
 * Reads one source PDF via Extract\Document, densely renumbers every object
 * under a given starting offset, rewrites indirect references throughout,
 * and translates the result into Build\PdfObject instances - the same shapes
 * Document::importObjects()/Page::importPageObject()/Build\Compiler already
 * consume. Used identically by Build\Parser (one source, offset 0) and
 * Build\Merger (N sources, increasing offsets).
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class ObjectGraphReader
{

    /**
     * Maximum page-tree recursion depth
     */
    protected const MAX_TREE_DEPTH = 64;

    /**
     * Read and translate one source document's entire object graph
     *
     * @param  Document $doc
     * @param  int      $offset
     * @throws Exception
     * @return array
     */
    public static function read(Document $doc, int $offset): array
    {
        $trailer = $doc->getTrailer();
        $rootRef = $trailer['Root'] ?? null;

        if (!($rootRef instanceof Value\Reference)) {
            throw new Exception('Error: Could not resolve the source PDF document catalog (Root).');
        }

        $root     = $doc->resolve($rootRef);
        $pagesRef = is_array($root) ? ($root['Pages'] ?? null) : null;

        if (!($pagesRef instanceof Value\Reference)) {
            throw new Exception('Error: Could not resolve the source PDF page tree (Pages).');
        }

        // /Root's /Pages must point to an actual /Type /Pages node, never
        // directly at a /Type /Page (a malformed shortcut some producers
        // take) - without this check, the rest of read() treats that
        // number as the top Pages node (excluding it from translation as a
        // leaf page), leaving it silently absent from pageObjects and
        // crashing downstream with a fatal, uncatchable Error rather than
        // a clean, catchable exception.
        $pagesNode = $doc->getObject($pagesRef->objNum);
        $pagesType = is_array($pagesNode) ? ($pagesNode['Type'] ?? null) : null;

        if (!is_array($pagesNode) || !($pagesType instanceof Value\Name) || ($pagesType->name !== 'Pages')) {
            throw new Exception('Error: The source PDF page tree root (Pages) is malformed or missing.');
        }

        $objectNumbers = $doc->getObjectNumbers();
        sort($objectNumbers);

        $map  = [];
        $next = $offset;
        foreach ($objectNumbers as $objNum) {
            $next++;
            $map[$objNum] = $next;
        }

        $leafPageObjNums = [];
        $inheritedByPage = [];
        $visited         = [];
        $inherited       = ['MediaBox' => null, 'Resources' => null, 'Rotate' => null];

        self::walkPagesTree($doc, $pagesRef->objNum, $inherited, $leafPageObjNums, $inheritedByPage, $visited, 0);

        $leafPageObjNumSet = array_flip($leafPageObjNums);

        $infoRef        = $trailer['Info'] ?? null;
        $infoObjNum     = ($infoRef instanceof Value\Reference) ? $infoRef->objNum : null;
        $topPagesObjNum = $pagesRef->objNum;

        // Widget annotations are excluded from a page's /Annots below (no
        // /AcroForm is carried over for them to register against), but that
        // by itself only stops the page from *pointing at* them - the
        // widget objects (and the source's own /AcroForm dictionary) are
        // still other object numbers in $objectNumbers, so without this
        // exclusion they fall through to translateGeneric() and get written
        // into the merged output as dead, unreferenced objects.
        $excludedFormObjNums = self::collectFormObjNums($doc, $root['AcroForm'] ?? null);

        $objects     = [];
        $pageObjects = [];
        $infoDict    = null;

        foreach ($objectNumbers as $objNum) {
            if (($objNum === $rootRef->objNum) || ($objNum === $topPagesObjNum) || ($objNum === $infoObjNum)
                || isset($excludedFormObjNums[$objNum])) {
                continue;
            }

            $newObjNum = $map[$objNum];

            if (isset($leafPageObjNumSet[$objNum])) {
                $node = $doc->getObject($objNum);
                $pageObjects[$newObjNum] = self::translatePage(
                    $doc, $newObjNum, is_array($node) ? $node : [], $inheritedByPage[$objNum], $map
                );
                continue;
            }

            $rewritten           = ReferenceRewriter::rewrite($doc->getObject($objNum), $map);
            $objects[$newObjNum] = self::translateGeneric($newObjNum, $rewritten);
        }

        if ($infoObjNum !== null) {
            $rewrittenInfo = ReferenceRewriter::rewrite($doc->getObject($infoObjNum), $map);
            $infoDict      = is_array($rewrittenInfo) ? $rewrittenInfo : null;
        }

        $topPagesNode      = $doc->getObject($topPagesObjNum);
        $topPagesRewritten = ReferenceRewriter::rewrite(is_array($topPagesNode) ? $topPagesNode : [], $map);

        $orderedPageObjects = [];
        foreach ($leafPageObjNums as $objNum) {
            $orderedPageObjects[] = $pageObjects[$map[$objNum]];
        }

        return [
            'objects'        => $objects,
            'pageObjects'    => $orderedPageObjects,
            'topPagesObjNum' => $map[$topPagesObjNum],
            'topPagesDict'   => $topPagesRewritten,
            'infoDict'       => $infoDict,
            'nextOffset'     => $next,
        ];
    }

    /**
     * Recursively walk one page-tree node, accumulating inherited attributes
     * and recording every leaf Page's object number in document order
     *
     * @param  Document $doc
     * @param  int      $objNum
     * @param  array    $inherited
     * @param  array    $leafPageObjNums
     * @param  array    $inheritedByPage
     * @param  array    $visited
     * @param  int      $depth
     * @return void
     */
    protected static function walkPagesTree(
        Document $doc, int $objNum, array $inherited, array &$leafPageObjNums,
        array &$inheritedByPage, array &$visited, int $depth
    ): void
    {
        if (($depth > self::MAX_TREE_DEPTH) || isset($visited[$objNum])) {
            return;
        }
        $visited[$objNum] = true;

        $node = $doc->getObject($objNum);
        if (!is_array($node)) {
            return;
        }

        foreach (['MediaBox', 'Resources', 'Rotate'] as $key) {
            if (isset($node[$key])) {
                $inherited[$key] = $doc->resolve($node[$key]);
            }
        }

        $type     = $node['Type'] ?? null;
        $typeName = ($type instanceof Value\Name) ? $type->name : null;

        if ($typeName === 'Page') {
            $leafPageObjNums[]        = $objNum;
            $inheritedByPage[$objNum] = $inherited;
            return;
        }

        $kids = $doc->resolve($node['Kids'] ?? null);
        if (!is_array($kids)) {
            return;
        }

        foreach ($kids as $kidRef) {
            if ($kidRef instanceof Value\Reference) {
                self::walkPagesTree($doc, $kidRef->objNum, $inherited, $leafPageObjNums, $inheritedByPage, $visited, $depth + 1);
            }
        }
    }

    /**
     * Collect the object numbers of a source PDF's /AcroForm dictionary and
     * every field/widget object reachable from its /Fields tree, so the
     * caller can omit them entirely rather than leaving them as orphaned
     * objects (see the exclusion built in read()).
     *
     * @param  Document $doc
     * @param  mixed    $acroFormRef
     * @return array
     */
    protected static function collectFormObjNums(Document $doc, mixed $acroFormRef): array
    {
        if (!($acroFormRef instanceof Value\Reference)) {
            return [];
        }

        $excluded = [$acroFormRef->objNum => true];

        $acroForm = $doc->resolve($acroFormRef);
        $fields   = is_array($acroForm) ? $doc->resolve($acroForm['Fields'] ?? null) : null;

        if (is_array($fields)) {
            $visited = [];
            foreach ($fields as $fieldRef) {
                if ($fieldRef instanceof Value\Reference) {
                    self::walkFieldTree($doc, $fieldRef->objNum, $excluded, $visited, 0);
                }
            }
        }

        return $excluded;
    }

    /**
     * Recursively walk one form field node, excluding it and every
     * descendant reachable via /Kids (a field hierarchy is a valid,
     * real-world PDF structure - a parent field with child widgets/fields)
     *
     * @param  Document $doc
     * @param  int      $objNum
     * @param  array    $excluded
     * @param  array    $visited
     * @param  int      $depth
     * @return void
     */
    protected static function walkFieldTree(Document $doc, int $objNum, array &$excluded, array &$visited, int $depth): void
    {
        if (($depth > self::MAX_TREE_DEPTH) || isset($visited[$objNum])) {
            return;
        }
        $visited[$objNum]  = true;
        $excluded[$objNum] = true;

        $node = $doc->getObject($objNum);
        $kids = is_array($node) ? $doc->resolve($node['Kids'] ?? null) : null;

        if (!is_array($kids)) {
            return;
        }

        foreach ($kids as $kidRef) {
            if ($kidRef instanceof Value\Reference) {
                self::walkFieldTree($doc, $kidRef->objNum, $excluded, $visited, $depth + 1);
            }
        }
    }

    /**
     * Translate a leaf Page node into a fully-populated PageObject
     *
     * @param  Document $doc
     * @param  int      $newObjNum
     * @param  array    $node
     * @param  array    $inherited
     * @param  array    $map
     * @return PdfObject\PageObject
     */
    protected static function translatePage(Document $doc, int $newObjNum, array $node, array $inherited, array $map): PdfObject\PageObject
    {
        $mediaBox  = $inherited['MediaBox'];
        $resources = $inherited['Resources'];
        $rotate    = $inherited['Rotate'];

        $width  = 612.0;
        $height = 792.0;
        if (is_array($mediaBox) && isset($mediaBox[2], $mediaBox[3])) {
            $width  = (float) $mediaBox[2];
            $height = (float) $mediaBox[3];
        }

        $parentNewNum = 0;
        if (($node['Parent'] ?? null) instanceof Value\Reference) {
            $parentNewNum = $map[$node['Parent']->objNum] ?? 0;
        }

        $pageObject = new PdfObject\PageObject($width, $height, $newObjNum);
        $pageObject->setParentIndex($parentNewNum);
        $pageObject->setImported(true);

        foreach (self::asReferenceList($node['Contents'] ?? null) as $ref) {
            if (isset($map[$ref->objNum])) {
                $pageObject->addContentIndex($map[$ref->objNum]);
            }
        }

        foreach (self::asReferenceList($node['Annots'] ?? null) as $ref) {
            $target  = $doc->resolve($ref);
            $subtype = is_array($target) ? ($target['Subtype'] ?? null) : null;

            if (($subtype instanceof Value\Name) && ($subtype->name === 'Widget')) {
                // Dropped - no /AcroForm is carried over for it to register
                // against (page/visual content only, per the design's scope).
                continue;
            }
            if (isset($map[$ref->objNum])) {
                $pageObject->addAnnotIndex($map[$ref->objNum]);
            }
        }

        $pageExtra = '';
        foreach ($node as $key => $value) {
            if (!in_array($key, ['Type', 'Parent', 'MediaBox', 'Annots', 'Contents', 'Resources', 'Rotate'], true)) {
                $pageExtra .= '/' . $key . ' ' . ObjectSerializer::serializeValue(ReferenceRewriter::rewrite($value, $map));
            }
        }
        if ($rotate !== null) {
            $pageExtra .= '/Rotate ' . ObjectSerializer::serializeValue(ReferenceRewriter::rewrite($rotate, $map));
        }
        $pageObject->setPageExtra($pageExtra);

        $otherResources = '';
        if (is_array($resources)) {
            foreach ($resources as $key => $value) {
                if (!in_array($key, ['ProcSet', 'XObject', 'Font'], true)) {
                    $otherResources .= '/' . $key . ' ' . ObjectSerializer::serializeValue(ReferenceRewriter::rewrite($value, $map));
                }
            }

            // /Font and /XObject may themselves be indirect references
            // (a separate, independent indirection from /Resources itself
            // already being indirect) - a valid, real-world PDF structure
            // (e.g. producers that share one Font dict object across many
            // pages/Resources dicts). Resolving here mirrors the same
            // pattern already used for content-stream interpretation
            // (Content\Interpreter's 'Tf' operator handling, which resolves
            // $resources['Font'] the same way before reading it).
            $fontResolved = $doc->resolve($resources['Font'] ?? null);
            $fontDict     = is_array($fontResolved) ? $fontResolved : [];
            foreach ($fontDict as $name => $ref) {
                if (($ref instanceof Value\Reference) && isset($map[$ref->objNum])) {
                    $pageObject->addFontReference('/' . $name . ' ' . $map[$ref->objNum] . ' 0 R');
                }
            }

            $xObjectResolved = $doc->resolve($resources['XObject'] ?? null);
            $xObjectDict      = is_array($xObjectResolved) ? $xObjectResolved : [];
            foreach ($xObjectDict as $name => $ref) {
                if (($ref instanceof Value\Reference) && isset($map[$ref->objNum])) {
                    $pageObject->addXObjectReference('/' . $name . ' ' . $map[$ref->objNum] . ' 0 R');
                }
            }
        }
        $pageObject->setOtherResources($otherResources);

        return $pageObject;
    }

    /**
     * Translate any non-page, non-Root, non-Info, non-top-Pages object into a generic passthrough
     *
     * @param  int   $newObjNum
     * @param  mixed $rewritten
     * @return PdfObject\StreamObject
     */
    protected static function translateGeneric(int $newObjNum, mixed $rewritten): PdfObject\StreamObject
    {
        $object = new PdfObject\StreamObject($newObjNum);

        if ($rewritten instanceof Value\Stream) {
            $object->setDefinition(ObjectSerializer::serializeDict($rewritten->dict));
            $object->appendStream("\n" . $rewritten->raw);

            // The leading "\n" just prepended is the mandatory
            // post-"stream"-keyword end-of-line marker (ISO 32000-1
            // 7.3.8.1), not real payload - mirrors StreamObject::parse()'s
            // own $leadingEolLength tracking, so Compiler's encryption pass
            // can strip exactly this many leading bytes before encrypting
            // regardless of whether $rewritten->dict['Length'] is a literal
            // integer or an (already-renumbered) indirect reference.
            $object->setLeadingEolLength(1);
        } else {
            // A top-level indirect object can be any PDF value, not just a
            // dict - most notably a plain array (e.g. a colorspace array
            // like [/Separation /Black /DeviceCMYK 25 0 R], a common
            // standalone indirect object in scanned/image-heavy PDFs).
            // serializeValue() already dispatches dict-vs-array correctly
            // via array_is_list(); calling serializeDict() unconditionally
            // for every array - as this used to - treated a list array's
            // integer keys (0,1,2,3) as dict key names, corrupting the
            // colorspace into a meaningless dict no reader could parse.
            $object->setDefinition(ObjectSerializer::serializeValue($rewritten));
        }

        $object->setImported(true);

        return $object;
    }

    /**
     * Normalize a /Contents or /Annots value into a flat list of References
     *
     * @param  mixed $value
     * @return array
     */
    protected static function asReferenceList(mixed $value): array
    {
        if ($value instanceof Value\Reference) {
            return [$value];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, static fn ($v) => $v instanceof Value\Reference));
        }
        return [];
    }

}
