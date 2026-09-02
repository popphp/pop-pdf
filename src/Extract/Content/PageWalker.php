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
namespace Pop\Pdf\Extract\Content;

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Budget;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract content page walker class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class PageWalker
{

    /**
     * Maximum page-tree recursion depth
     */
    protected const MAX_TREE_DEPTH = 64;

    /**
     * Walk a document's page tree into a flat, 0-indexed array of PageInfo objects (index 0 = page 1)
     *
     * @param  Document $doc
     * @param  ?array   $pageNumbers
     * @param  ?int     $pageLimit
     * @throws Exception
     * @return array
     */
    public static function walk(Document $doc, ?array $pageNumbers = null, ?int $pageLimit = null): array
    {
        $root  = $doc->getRoot();
        $pages = $doc->resolve($root['Pages'] ?? null);

        if (!is_array($pages)) {
            throw new Exception('Error: Could not resolve the PDF page tree (Pages).');
        }

        $result    = [];
        $inherited = ['Resources' => null, 'MediaBox' => null, 'Rotate' => null];
        $visited   = [];
        $pageCount = 0;

        // Precompute a flipped lookup set once (O(n)) instead of letting
        // walkNode() do an in_array() scan of $pageNumbers (O(n)) for every
        // single page visited - that turned page-subset extraction into
        // O(pages * count($pageNumbers)) instead of O(pages).
        $pageNumberSet = ($pageNumbers !== null) ? array_flip($pageNumbers) : null;

        self::walkNode($doc, $pages, $inherited, $result, $visited, 0, $pageNumberSet, $pageLimit, $pageCount);

        return $result;
    }

    /**
     * Recursively walk one page-tree node, appending PageInfo objects for each Page found
     *
     * @param  Document $doc
     * @param  array    $node
     * @param  array    $inherited
     * @param  array    $result
     * @param  array    $visited
     * @param  int      $depth
     * @param  ?array   $pageNumberSet flipped [pageNumber => key] lookup set, or null for "all pages"
     * @param  ?int     $pageLimit
     * @param  int      $pageCount
     * @return void
     */
    protected static function walkNode(
        Document $doc, array $node, array $inherited, array &$result, array &$visited, int $depth,
        ?array $pageNumberSet, ?int $pageLimit, int &$pageCount
    ): void
    {
        if ($depth > self::MAX_TREE_DEPTH) {
            return;
        }

        foreach (['Resources', 'MediaBox', 'Rotate'] as $key) {
            if (isset($node[$key])) {
                $inherited[$key] = $node[$key];
            }
        }

        $type     = $node['Type'] ?? null;
        $typeName = ($type instanceof Value\Name) ? $type->name : null;

        if ($typeName === 'Page') {
            $pageCount++;

            $needed = true;
            if ($pageNumberSet !== null) {
                $needed = isset($pageNumberSet[$pageCount]);
            } elseif (is_int($pageLimit) && ($pageLimit > 0)) {
                $needed = ($pageCount <= $pageLimit);
            }

            if (!$needed) {
                // A page the caller didn't ask for (outside $pageNumbers, or
                // past $pageLimit) still gets a PageInfo entry so page
                // count/1-indexing stays correct - it just skips the
                // expensive resolve/decompress work entirely, since a
                // caller passing e.g. pageLimit=1 on untrusted input expects
                // that to actually bound the work done, not just the output
                // returned (confirmed during Phase D's final review: without
                // this, a 40-page PDF with 5MB/page content still cost 5.7s
                // and 211MB even when only page 1 was requested).
                $result[] = new PageInfo($node, [], null, null, '');
                return;
            }

            try {
                $resources = $doc->resolve($inherited['Resources']);
                $mediaBox  = $doc->resolve($inherited['MediaBox']);
                $rotate    = $doc->resolve($inherited['Rotate']);
                $content   = self::resolveContent($doc, $node);
            } catch (Exception $e) {
                // A single malformed/unsupported page (bad stream filter,
                // circular reference in its own Resources/Contents/MediaBox)
                // must not abort extraction of every OTHER page in the
                // document - degrade this page to empty content instead of
                // letting the exception unwind the whole walk(), and keep
                // this page's PageInfo entry (rather than skipping it) so
                // page count/1-indexing stays correct for $pages selectors
                // and pageLimit.
                $resources = [];
                $mediaBox  = null;
                $rotate    = null;
                $content   = '';
            }

            $result[] = new PageInfo(
                $node,
                is_array($resources) ? $resources : [],
                $mediaBox,
                $rotate,
                $content
            );

            return;
        }

        $kids = $doc->resolve($node['Kids'] ?? null);

        if (!is_array($kids)) {
            return;
        }

        foreach ($kids as $kidRef) {
            if ($kidRef instanceof Value\Reference) {
                if (isset($visited[$kidRef->objNum])) {
                    continue;
                }
                $visited[$kidRef->objNum] = true;
            }

            $kid = $doc->resolve($kidRef);

            if (is_array($kid)) {
                self::walkNode($doc, $kid, $inherited, $result, $visited, $depth + 1, $pageNumberSet, $pageLimit, $pageCount);
            }
        }
    }

    /**
     * Resolve and decode a page's /Contents into a single content stream string
     *
     * @param  Document $doc
     * @param  array    $page
     * @throws Exception
     * @return string
     */
    protected static function resolveContent(Document $doc, array $page): string
    {
        $contents = $doc->resolve($page['Contents'] ?? null);
        $budget   = $doc->getDecodeBudget();

        if ($contents instanceof Value\Stream) {
            return self::decodeStream($contents, $budget);
        }

        if (is_array($contents)) {
            $parts = [];

            foreach ($contents as $ref) {
                $stream = $doc->resolve($ref);
                if ($stream instanceof Value\Stream) {
                    $parts[] = self::decodeStream($stream, $budget);
                }
            }

            return implode("\n", $parts);
        }

        return '';
    }

    /**
     * Decode a content stream through its filter(s)
     *
     * @param  Value\Stream $stream
     * @param  ?Budget      $budget
     * @throws Exception
     * @return string
     */
    protected static function decodeStream(Value\Stream $stream, ?Budget $budget = null): string
    {
        return Registry::decode($stream->raw, $stream->dict['Filter'] ?? null, $stream->dict['DecodeParms'] ?? null, $budget);
    }

}
