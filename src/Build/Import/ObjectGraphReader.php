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
 * @version    6.2.0
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

        // The source's /AcroForm dictionary and every node of its field tree
        // (fields and their widgets) are not translated as generic objects -
        // the field tree is carried over as an ImportedForm instead, which
        // the compiler writes from, keeping only the nodes whose widgets are
        // on pages the document still has.
        $fieldParents        = [];
        $excludedFormObjNums = self::collectFormObjNums($doc, $root['AcroForm'] ?? null, $fieldParents);
        $formObjects         = self::pruneFormResources($doc, $objectNumbers);

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
                $node      = $doc->getObject($objNum);
                $node      = is_array($node) ? $node : [];
                $inherited = $inheritedByPage[$objNum];

                if (is_array($inherited['Resources'])) {
                    $inherited['Resources'] = ResourceUsage::prune(
                        $doc, self::asReferenceList($doc, $node['Contents'] ?? null), $inherited['Resources']
                    );
                }

                $pageObjects[$newObjNum] = self::translatePage(
                    $doc, $newObjNum, $node, $inherited, $map, $excludedFormObjNums
                );
                $pageObjects[$newObjNum]->setImportedObjectIndices(
                    self::collectPageObjNums($doc, $node, $inherited, $map, $formObjects, $excludedFormObjNums)
                );
                continue;
            }

            $rewritten           = ReferenceRewriter::rewrite($formObjects[$objNum] ?? $doc->getObject($objNum), $map);
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
            'form'           => self::readForm(
                $doc, $root['AcroForm'] ?? null, $fieldParents, $map, $formObjects, $excludedFormObjNums
            ),
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
     * caller can keep them out of the generic objects (see the exclusion
     * built in read()), and record each field tree node's parent.
     *
     * @param  Document $doc
     * @param  mixed    $acroFormRef
     * @param  array    $parents
     * @return array
     */
    protected static function collectFormObjNums(Document $doc, mixed $acroFormRef, array &$parents): array
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
                    self::walkFieldTree($doc, $fieldRef->objNum, null, $excluded, $parents, $visited, 0);
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
     * @param  ?int     $parent
     * @param  array    $excluded
     * @param  array    $parents
     * @param  array    $visited
     * @param  int      $depth
     * @return void
     */
    protected static function walkFieldTree(
        Document $doc, int $objNum, ?int $parent, array &$excluded, array &$parents, array &$visited, int $depth
    ): void
    {
        if (($depth > self::MAX_TREE_DEPTH) || isset($visited[$objNum])) {
            return;
        }
        $visited[$objNum]  = true;
        $excluded[$objNum] = true;
        $parents[$objNum]  = $parent;

        $node = $doc->getObject($objNum);
        $kids = is_array($node) ? $doc->resolve($node['Kids'] ?? null) : null;

        if (!is_array($kids)) {
            return;
        }

        foreach ($kids as $kidRef) {
            if ($kidRef instanceof Value\Reference) {
                self::walkFieldTree($doc, $kidRef->objNum, $objNum, $excluded, $parents, $visited, $depth + 1);
            }
        }
    }

    /**
     * Read a source PDF's AcroForm into an ImportedForm - each field tree
     * node's dictionary (renumbered, its /Kids resolved to a plain array)
     * and the objects it uses, the top-level fields, and the document-level
     * settings. /XFA is left out, since its XML would no longer match the
     * fields, and a signature field's /V is cleared, since rewriting the
     * file invalidates the signature it holds.
     *
     * @param  Document $doc
     * @param  mixed    $acroFormRef
     * @param  array    $parents
     * @param  array    $map
     * @param  array    $formObjects
     * @param  array    $formObjNums
     * @return ?ImportedForm
     */
    protected static function readForm(
        Document $doc, mixed $acroFormRef, array $parents, array $map, array $formObjects, array $formObjNums
    ): ?ImportedForm
    {
        $acroForm = ($acroFormRef instanceof Value\Reference) ? $doc->resolve($acroFormRef) : null;

        if (!is_array($acroForm) || (count($parents) === 0)) {
            return null;
        }

        $form = new ImportedForm();

        foreach ($parents as $objNum => $parent) {
            $dict = $doc->getObject($objNum);
            if (!is_array($dict)) {
                continue;
            }

            if (isset($dict['Kids'])) {
                $dict['Kids'] = $doc->resolve($dict['Kids']);
            }
            if (self::fieldType($doc, $objNum, $parents) === 'Sig') {
                unset($dict['V']);
            }

            $walk = $dict;
            unset($walk['Kids'], $walk['Parent']);

            $form->addNode(
                $map[$objNum], ReferenceRewriter::rewrite($dict, $map), ($parent !== null) ? $map[$parent] : null,
                self::collectObjNums($doc, [$walk], $map, $formObjects, $formObjNums)
            );
        }

        $fields = [];
        foreach (self::asReferenceList($doc, $acroForm['Fields'] ?? null) as $ref) {
            if (array_key_exists($ref->objNum, $parents)) {
                $fields[] = $map[$ref->objNum];
            }
        }
        $form->setFields($fields);

        $settings = [];
        foreach (['DR', 'DA', 'Q', 'SigFlags', 'NeedAppearances'] as $key) {
            if (isset($acroForm[$key])) {
                $settings[$key] = $doc->resolve($acroForm[$key]);
            }
        }
        // /DR's categories (/Font etc.) may be indirect too - resolved so
        // the compiler can merge them by name across sources
        if (isset($settings['DR']) && is_array($settings['DR'])) {
            foreach ($settings['DR'] as $category => $value) {
                $settings['DR'][$category] = $doc->resolve($value);
            }
        }

        $form->setSettings(
            ReferenceRewriter::rewrite($settings, $map), self::collectObjNums($doc, [$settings], $map, $formObjects, $formObjNums)
        );

        return $form;
    }

    /**
     * Get a field's type, inherited from its nearest ancestor that sets /FT
     *
     * @param  Document $doc
     * @param  int      $objNum
     * @param  array    $parents
     * @return ?string
     */
    protected static function fieldType(Document $doc, int $objNum, array $parents): ?string
    {
        $current = $objNum;

        while ($current !== null) {
            $node = $doc->getObject($current);
            $type = is_array($node) ? ($node['FT'] ?? null) : null;
            if ($type instanceof Value\Name) {
                return $type->name;
            }
            $current = $parents[$current] ?? null;
        }

        return null;
    }

    /**
     * Collect the new object numbers of every object one leaf Page node uses -
     * its content, resources (including inherited ones), annotations and
     * everything those reference in turn. The walk never steps into another
     * Page or Pages node, and never follows the page's own /Parent, or every
     * page would reach every other page's objects through the page tree. The
     * page's resources come from $inherited (its own or inherited, already
     * pruned to what it uses), not from its /Resources entry. Form field
     * widgets are not followed either - the ImportedForm tracks what each
     * of them uses.
     *
     * @param  Document $doc
     * @param  array    $node
     * @param  array    $inherited
     * @param  array    $map
     * @param  array    $formObjects
     * @param  array    $formObjNums
     * @return array
     */
    protected static function collectPageObjNums(
        Document $doc, array $node, array $inherited, array $map, array $formObjects, array $formObjNums
    ): array
    {
        unset($node['Parent'], $node['Resources']);

        return self::collectObjNums($doc, [$node, $inherited], $map, $formObjects, $formObjNums);
    }

    /**
     * Collect the new object numbers of every object a set of values
     * references, directly or through other objects - never stepping into a
     * Page or Pages node, or into a form field tree node
     *
     * @param  Document $doc
     * @param  array    $pending
     * @param  array    $map
     * @param  array    $formObjects
     * @param  array    $formObjNums
     * @return array
     */
    protected static function collectObjNums(
        Document $doc, array $pending, array $map, array $formObjects, array $formObjNums
    ): array
    {
        $objNums = [];
        $visited = [];

        while (count($pending) > 0) {
            $value = array_pop($pending);

            if ($value instanceof Value\Stream) {
                $pending[] = $value->dict;
            } else if (is_array($value)) {
                foreach ($value as $item) {
                    $pending[] = $item;
                }
            } else if (($value instanceof Value\Reference) && !isset($visited[$value->objNum])
                && !isset($formObjNums[$value->objNum])) {
                $visited[$value->objNum] = true;

                $object = $formObjects[$value->objNum] ?? $doc->getObject($value->objNum);
                $dict   = ($object instanceof Value\Stream) ? $object->dict : $object;
                $type   = is_array($dict) ? ($dict['Type'] ?? null) : null;

                if (($type instanceof Value\Name) && in_array($type->name, ['Page', 'Pages'], true)) {
                    continue;
                }
                if (isset($map[$value->objNum])) {
                    $objNums[] = $map[$value->objNum];
                }
                $pending[] = $object;
            }
        }

        return $objNums;
    }

    /**
     * Prune the /Resources of every form XObject that has its own to what
     * its own content uses - some producers give all their page templates
     * one shared resource dictionary, the same way they do for pages. Each
     * pruned form is returned, keyed by object number, with its /Resources
     * replaced by the pruned dictionary, to be used in place of the original.
     *
     * @param  Document $doc
     * @param  array    $objectNumbers
     * @return array
     */
    protected static function pruneFormResources(Document $doc, array $objectNumbers): array
    {
        $forms = [];

        foreach ($objectNumbers as $objNum) {
            $object  = $doc->getObject($objNum);
            $subtype = ($object instanceof Value\Stream) ? ($object->dict['Subtype'] ?? null) : null;

            if (!($subtype instanceof Value\Name) || ($subtype->name !== 'Form')) {
                continue;
            }

            $resources = $doc->resolve($object->dict['Resources'] ?? null);
            if (is_array($resources)) {
                $pruned         = ResourceUsage::prune($doc, [new Value\Reference($objNum, 0)], $resources);
                $forms[$objNum] = new Value\Stream(['Resources' => $pruned] + $object->dict, $object->raw);
            }
        }

        return $forms;
    }

    /**
     * Translate a leaf Page node into a fully-populated PageObject
     *
     * @param  Document $doc
     * @param  int      $newObjNum
     * @param  array    $node
     * @param  array    $inherited
     * @param  array    $map
     * @param  array    $formObjNums
     * @return PdfObject\PageObject
     */
    protected static function translatePage(
        Document $doc, int $newObjNum, array $node, array $inherited, array $map, array $formObjNums
    ): PdfObject\PageObject
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

        foreach (self::asReferenceList($doc, $node['Contents'] ?? null) as $ref) {
            if (isset($map[$ref->objNum])) {
                $pageObject->addContentIndex($map[$ref->objNum]);
            }
        }

        foreach (self::asReferenceList($doc, $node['Annots'] ?? null) as $ref) {
            $target  = $doc->resolve($ref);
            $subtype = is_array($target) ? ($target['Subtype'] ?? null) : null;

            if (($subtype instanceof Value\Name) && ($subtype->name === 'Widget') && !isset($formObjNums[$ref->objNum])) {
                // Dropped - a widget outside the /AcroForm field tree has no
                // field for it to belong to in the output's /AcroForm.
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
            // The source's /Length can be wrong, and StreamObject keeps an
            // image's /Length as given - write the length of the data read
            $dict           = $rewritten->dict;
            $dict['Length'] = strlen($rewritten->raw);

            $object->setDefinition(ObjectSerializer::serializeDict($dict));
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
     * @param  Document $doc
     * @param  mixed    $value
     * @return array
     */
    protected static function asReferenceList(Document $doc, mixed $value): array
    {
        // The value may be a reference to an array object of references
        // rather than to a single object - treating that array object itself
        // as the one content stream or annotation leaves the page blank, or
        // without its annotations
        if ($value instanceof Value\Reference) {
            $resolved = $doc->getObject($value->objNum);
            if (!is_array($resolved) || !array_is_list($resolved)) {
                return [$value];
            }
            $value = $resolved;
        }
        if (is_array($value)) {
            return array_values(array_filter($value, static fn ($v) => $v instanceof Value\Reference));
        }
        return [];
    }

}
