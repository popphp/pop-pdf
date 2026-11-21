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

use Pop\Pdf\Extract\Document;
use Pop\Pdf\Extract\Filter\Registry;
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Value;

/**
 * Pdf build import resource usage class
 *
 * Prunes a page's /Resources down to the named resources its content
 * streams actually use. Some producers give every page one shared resource
 * dictionary listing every page's images and templates - without pruning,
 * a single imported page would carry all of them. If any content stream
 * can't be read, the resources are left whole rather than risk dropping
 * something the page draws.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.2.0
 */
class ResourceUsage
{

    /**
     * Maximum form XObject nesting depth followed
     */
    protected const MAX_FORM_DEPTH = 16;

    /**
     * The operators that name a resource - the resource category, and which
     * operand is the name (-1 for the last operand, as /scn and /SCN take
     * any number of color components before an optional pattern name)
     */
    protected const OPERATORS = [
        'Do'  => ['XObject', 0],
        'Tf'  => ['Font', 0],
        'gs'  => ['ExtGState', 0],
        'cs'  => ['ColorSpace', 0],
        'CS'  => ['ColorSpace', 0],
        'sh'  => ['Shading', 0],
        'scn' => ['Pattern', -1],
        'SCN' => ['Pattern', -1],
        'BDC' => ['Properties', 1],
        'DP'  => ['Properties', 1],
    ];

    /**
     * Prune a page's resources to the named resources its content uses
     *
     * @param  Document $doc
     * @param  array    $contentRefs
     * @param  array    $resources
     * @return array
     */
    public static function prune(Document $doc, array $contentRefs, array $resources): array
    {
        $used = [];

        try {
            $streams = [];
            foreach ($contentRefs as $ref) {
                $stream = $doc->resolve($ref);
                if ($stream instanceof Value\Stream) {
                    $streams[] = $stream;
                }
            }
            self::collect($doc, $streams, $resources, $used, [], 0);
        } catch (\Pop\Pdf\Extract\Exception $e) {
            return $resources;
        }

        foreach (array_unique(array_column(self::OPERATORS, 0)) as $category) {
            $dict = $doc->resolve($resources[$category] ?? null);
            if (is_array($dict)) {
                $resources[$category] = array_intersect_key($dict, $used[$category] ?? []);
            }
        }

        return $resources;
    }

    /**
     * Collect the resource names used by a set of content streams, following
     * any form XObject that has no /Resources of its own, since the names
     * inside it are looked up in these same resources
     *
     * @param  Document $doc
     * @param  array    $streams
     * @param  array    $resources
     * @param  array    $used
     * @param  array    $formStack
     * @param  int      $depth
     * @throws \Pop\Pdf\Extract\Exception
     * @return void
     */
    protected static function collect(
        Document $doc, array $streams, array $resources, array &$used, array $formStack, int $depth
    ): void
    {
        // A page's content streams are one stream split into parts - an
        // operator's operands may sit in one part and the operator in the next
        $content = '';
        foreach ($streams as $stream) {
            $content .= Registry::decode(
                $stream->raw, $doc->resolve($stream->dict['Filter'] ?? null),
                $doc->resolve($stream->dict['DecodeParms'] ?? null), $doc->getDecodeBudget()
            ) . "\n";
        }

        $tokenizer    = new Tokenizer($content);
        $objectParser = new ObjectParser($tokenizer);
        $operands     = [];

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                break;
            }

            if (($peek['type'] === 'keyword') && ($peek['value'] === 'BI')) {
                self::collectInlineImage($tokenizer, $objectParser, $used);
                $operands = [];
                continue;
            }

            $tokenizer->setPosition($savedPos);
            $value = $objectParser->parseValue();

            if (!($value instanceof Value\Keyword)) {
                $operands[] = $value;
                continue;
            }

            if (isset(self::OPERATORS[$value->keyword])) {
                [$category, $position] = self::OPERATORS[$value->keyword];
                $name = ($position === -1) ? end($operands) : ($operands[$position] ?? null);

                if ($name instanceof Value\Name) {
                    $used[$category][$name->name] = true;

                    if ($category === 'XObject') {
                        self::collectForm($doc, $name->name, $resources, $used, $formStack, $depth);
                    }
                }
            }

            $operands = [];
        }
    }

    /**
     * Follow a form XObject drawn with Do, if it has no /Resources of its own
     *
     * @param  Document $doc
     * @param  string   $name
     * @param  array    $resources
     * @param  array    $used
     * @param  array    $formStack
     * @param  int      $depth
     * @throws \Pop\Pdf\Extract\Exception
     * @return void
     */
    protected static function collectForm(
        Document $doc, string $name, array $resources, array &$used, array $formStack, int $depth
    ): void
    {
        $xObjects = $doc->resolve($resources['XObject'] ?? null);
        $ref      = is_array($xObjects) ? ($xObjects[$name] ?? null) : null;

        if (!($ref instanceof Value\Reference) || isset($formStack[$ref->objNum]) || ($depth >= self::MAX_FORM_DEPTH)) {
            return;
        }

        $form    = $doc->resolve($ref);
        $subtype = ($form instanceof Value\Stream) ? ($form->dict['Subtype'] ?? null) : null;

        if (($subtype instanceof Value\Name) && ($subtype->name === 'Form') && !isset($form->dict['Resources'])) {
            $formStack[$ref->objNum] = true;
            self::collect($doc, [$form], $resources, $used, $formStack, $depth + 1);
        }
    }

    /**
     * Read an inline image's dictionary for a named color space, and skip
     * past its image data to the EI that ends it
     *
     * @param  Tokenizer    $tokenizer
     * @param  ObjectParser $objectParser
     * @param  array        $used
     * @throws \Pop\Pdf\Extract\Exception
     * @return void
     */
    protected static function collectInlineImage(Tokenizer $tokenizer, ObjectParser $objectParser, array &$used): void
    {
        $values = [];

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                return;
            }
            if (($peek['type'] === 'keyword') && ($peek['value'] === 'ID')) {
                break;
            }

            $tokenizer->setPosition($savedPos);
            $values[] = $objectParser->parseValue();
        }

        foreach (array_chunk($values, 2) as $pair) {
            if (($pair[0] instanceof Value\Name) && in_array($pair[0]->name, ['CS', 'ColorSpace'], true)
                && (($pair[1] ?? null) instanceof Value\Name)) {
                $used['ColorSpace'][$pair[1]->name] = true;
            }
        }

        $tokenizer->skipInlineImageData();
    }

}
