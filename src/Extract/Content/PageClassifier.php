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
use Pop\Pdf\Extract\ObjectParser;
use Pop\Pdf\Extract\Tokenizer;
use Pop\Pdf\Extract\Value;

/**
 * Pdf extract content page classifier class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class PageClassifier
{

    /**
     * Operators that paint/mark the page
     */
    protected const PAINT_OPERATORS = ['f', 'F', 'f*', 'S', 's', 'B', 'B*', 'b', 'b*', 'sh'];

    /**
     * Text-showing operators
     */
    protected const TEXT_OPERATORS = ['Tj', 'TJ', "'", '"'];

    /**
     * Determine if a page's content is nothing but a single drawn image
     *
     * @param  Document $doc
     * @param  PageInfo $page
     * @return bool
     */
    public static function isImageOnly(Document $doc, PageInfo $page): bool
    {
        $tokenizer    = new Tokenizer($page->content);
        $objectParser = new ObjectParser($tokenizer);
        $operandStack = [];
        $imageCount   = 0;

        while (true) {
            $savedPos = $tokenizer->getPosition();
            $peek     = $tokenizer->next();

            if ($peek['type'] === 'eof') {
                break;
            }

            if (($peek['type'] === 'keyword') && ($peek['value'] === 'BI')) {
                // An inline image is unexpected extra content for this
                // classifier's purposes (real scan-to-PDF output uses a
                // full-page XObject image, not inline images) - disqualify
                // immediately rather than skipping over it.
                return false;
            }

            $tokenizer->setPosition($savedPos);

            try {
                $value = $objectParser->parseValue();
            } catch (Exception $e) {
                // Malformed operand - skip it and keep scanning, matching
                // Interpreter::interpret()'s established resilience pattern.
                // The tokenizer's position has already advanced past the
                // offending token, so this always makes forward progress.
                $operandStack = [];
                continue;
            }

            if (!($value instanceof Value\Keyword)) {
                $operandStack[] = $value;
                continue;
            }

            $op = $value->keyword;

            if (in_array($op, self::TEXT_OPERATORS, true)) {
                return false;
            }

            if (in_array($op, self::PAINT_OPERATORS, true)) {
                return false;
            }

            if ($op === 'Do') {
                $name = end($operandStack);
                if (!($name instanceof Value\Name)) {
                    $operandStack = [];
                    continue;
                }

                $isImage = self::resolveIsImage($doc, $page, $name->name);

                if ($isImage === null) {
                    // Unresolvable XObject (missing resource, circular
                    // reference, etc.) - can't prove this page is safe, so
                    // the safe default is "not image-only", not "assume
                    // it's fine".
                    return false;
                }

                if (!$isImage) {
                    // A Form XObject draw is unexpected extra content -
                    // disqualify outright rather than recursing into it.
                    return false;
                }

                $imageCount++;

                if ($imageCount > 1) {
                    // Already disqualified (more than one image) - no need
                    // to keep scanning the rest of a potentially very long
                    // content stream just to reach the same conclusion.
                    return false;
                }
            }

            $operandStack = [];
        }

        return $imageCount === 1;
    }

    /**
     * Resolve a Do operand's XObject and determine if it's an Image (true), a Form (false), or unresolvable (null)
     *
     * @param  Document $doc
     * @param  PageInfo $page
     * @param  string   $name
     * @return ?bool
     */
    protected static function resolveIsImage(Document $doc, PageInfo $page, string $name): ?bool
    {
        try {
            $xobjects = $doc->resolve($page->resources['XObject'] ?? null);

            if (!is_array($xobjects) || !isset($xobjects[$name])) {
                return null;
            }

            $xobject = $doc->resolve($xobjects[$name]);

            if (!($xobject instanceof Value\Stream)) {
                return null;
            }

            $subtype = $xobject->dict['Subtype'] ?? null;

            if (!($subtype instanceof Value\Name)) {
                return null;
            }

            if ($subtype->name === 'Image') {
                return true;
            }

            if ($subtype->name === 'Form') {
                return false;
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

}
