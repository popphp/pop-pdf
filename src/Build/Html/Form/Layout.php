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
namespace Pop\Pdf\Build\Html\Form;

use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Parser;
use Pop\Pdf\Document;
use Pop\Pdf\Document\Page\Field;

/**
 * Pdf HTML form layout class
 *
 * Converts a <form> subtree's controls into Document\Page\Field\* objects,
 * block-positioned the same way Build\Html\Table\Layout lays out table
 * rows - every control occupies its own rectangle and the cursor advances
 * past it. Label/text content inside a <form> is rendered with simple,
 * single-style per-line text placement, mirroring how Table\Layout already
 * renders cell text - not the full nested inline-styling ordinary
 * paragraphs get elsewhere in the parser.
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Layout
{

    /**
     * Vertical gap left after each control, in points
     */
    protected const CONTROL_GAP = 4;

    /**
     * Render a <form> node's controls onto the document
     *
     * @param  Parser $parser
     * @param  Child  $formNode
     * @param  array  $styles
     * @param  int    $startX
     * @param  float  $startY
     * @return void
     */
    public static function render(Parser $parser, Child $formNode, array $styles, int $startX, float $startY): void
    {
        $formName  = self::resolveFormName($parser, $formNode);
        $consumedY = (float) $parser->getY();
        $currentY  = $startY;

        foreach ($formNode->getChildNodes() as $child) {
            [$consumedY, $currentY] = self::renderNode($parser, $child, $formName, $startX, $consumedY, $currentY);
        }

        $parser->setY((int) round($consumedY));
        $parser->setYOverride((int) round($currentY));
    }

    /**
     * Render a single form control with no <form> ancestor
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  int    $startX
     * @param  float  $startY
     * @return void
     */
    public static function renderBareControl(Parser $parser, Child $node, int $startX, float $startY): void
    {
        $formName = self::resolveDefaultFormName($parser);
        [$consumedY, $currentY] = self::renderControl($parser, $node, $formName, $startX, (float) $parser->getY(), $startY);

        $parser->setY((int) round($consumedY));
        $parser->setYOverride((int) round($currentY));
    }

    /**
     * Render one child of a <form> - either a control (converted to a
     * field) or text content (rendered as simple per-line text)
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $formName
     * @param  int    $x
     * @param  float  $consumedY
     * @param  float  $currentY
     * @return array
     */
    protected static function renderNode(Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY): array
    {
        if (in_array($node->getNodeName(), ['input', 'select', 'textarea', 'button'])) {
            return self::renderControl($parser, $node, $formName, $x, $consumedY, $currentY);
        }

        $text = trim((string) $node->getNodeValue());
        if ($text === '') {
            return [$consumedY, $currentY];
        }

        $styles     = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        $fontObject = $parser->document()->getFont($styles['currentFont']);
        $wrapLength = $parser->getPage()->getWidth() - $parser->getPageRightMargin() - $x;
        $lines      = $parser->getStringLines($text, $styles['fontSize'], (int) $wrapLength, $fontObject);

        foreach ($lines as $line) {
            if ($currentY <= $parser->getPageBottomMargin()) {
                $currentY  = $parser->newPage();
                $consumedY = 0;
            }
            $parser->getPage()->addText(new Document\Page\Text($line, $styles['fontSize']), $styles['currentFont'], $x, (int) round($currentY));
            $consumedY += $styles['lineHeight'];
            $currentY  -= $styles['lineHeight'];
        }

        return [$consumedY, $currentY];
    }

    /**
     * Convert one form-control node into a Field object, size and position
     * it, and add it to the current page
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $formName
     * @param  int    $x
     * @param  float  $consumedY
     * @param  float  $currentY
     * @return array
     */
    protected static function renderControl(Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY): array
    {
        [$field, $width, $height] = self::buildField($parser, $node);
        self::applyAppearance($field, $parser, $node);

        if ($currentY - $height <= $parser->getPageBottomMargin()) {
            $currentY  = $parser->newPage();
            $consumedY = 0;
        }

        $field->setWidth((int) $width)->setHeight((int) $height);
        $parser->getPage()->addField($field, $formName, $x, (int) round($currentY - $height));

        $consumedY += $height + self::CONTROL_GAP;
        $currentY  -= $height + self::CONTROL_GAP;

        return [$consumedY, $currentY];
    }

    /**
     * Apply CSS border/background appearance onto a field, reusing the same
     * styles Parser::prepareNodeStyles() already resolves for boxed text -
     * border-width is the real gate (matching normal CSS semantics and
     * Parser::drawBox()'s own convention), since borderColor otherwise
     * always defaults to [0, 0, 0] whether or not a border was requested
     *
     * @param  Field\AbstractField $field
     * @param  Parser              $parser
     * @param  Child               $node
     * @return void
     */
    protected static function applyAppearance(Field\AbstractField $field, Parser $parser, Child $node): void
    {
        $styles = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());

        if ($styles['borderWidth'] > 0) {
            $field->setBorderWidth((int) $styles['borderWidth']);
            $field->setBorderColor($styles['borderColor']);
        }
        if ($styles['backgroundColor'] !== null) {
            $field->setBackgroundColor($styles['backgroundColor']);
        }
    }

    /**
     * Build the right Field subclass (sized, but not yet positioned) for
     * one HTML form-control node
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @return array
     */
    protected static function buildField(Parser $parser, Child $node): array
    {
        $tag  = $node->getNodeName();
        $type = $node->hasAttribute('type') ? strtolower($node->getAttribute('type')) : 'text';
        $name = $node->hasAttribute('name') ? $node->getAttribute('name') : self::autoFieldName($parser);

        if ($tag === 'textarea') {
            $field = new Field\Text($name);
            $field->setMultiline();
            $value = trim((string) $node->getNodeValue());
            if ($value !== '') {
                $field->setValue($value);
            }
            return [$field, self::resolveWidth($parser, $node, 300), self::resolveHeight($parser, $node, 60)];
        }

        if ($tag === 'select') {
            $field = new Field\Choice($name);
            if ($node->hasAttribute('multiple')) {
                $field->setMultiSelect();
            } else {
                $field->setCombo();
            }
            foreach ($node->getChildNodes() as $option) {
                if ($option->getNodeName() !== 'option') {
                    continue;
                }
                $optionValue = $option->hasAttribute('value') ? $option->getAttribute('value') : trim((string) $option->getNodeValue());
                $optionLabel = trim((string) $option->getNodeValue());
                $field->addOption($optionValue, $optionLabel);
                if ($option->hasAttribute('selected')) {
                    $field->setValue($optionValue);
                }
            }
            return [$field, self::resolveWidth($parser, $node, 150), self::resolveHeight($parser, $node, 20)];
        }

        if (($tag === 'button') || ($type === 'submit') || ($type === 'reset')) {
            $field = new Field\Button($name);
            $field->setPushButton();
            return [$field, self::resolveWidth($parser, $node, 80), self::resolveHeight($parser, $node, 24)];
        }

        if ($type === 'checkbox') {
            $field = new Field\Button($name);
            // setValue() always runs (checked or not) - Compiler now derives each
            // checkbox/radio's on-state export name from getValue(), independent of
            // setChecked(). Conflating the two (only calling setValue() when checked)
            // was a real, fixed bug discovered during this plan's own Task 4 review.
            $field->setValue($node->hasAttribute('value') ? $node->getAttribute('value') : 'Yes');
            if ($node->hasAttribute('checked')) {
                $field->setChecked();
            }
            return [$field, self::resolveWidth($parser, $node, 14), self::resolveHeight($parser, $node, 14)];
        }

        if ($type === 'radio') {
            $field = new Field\Button($name);
            $field->setRadio();
            $field->setValue($node->hasAttribute('value') ? $node->getAttribute('value') : 'Yes');
            if ($node->hasAttribute('checked')) {
                $field->setChecked();
            }
            return [$field, self::resolveWidth($parser, $node, 14), self::resolveHeight($parser, $node, 14)];
        }

        if ($type === 'hidden') {
            $field = new Field\Text($name);
            if ($node->hasAttribute('value')) {
                $field->setValue($node->getAttribute('value'));
            }
            return [$field, 0.0, 0.0];
        }

        // text, email, password, file, tel, url, search, or any unrecognized type
        $field = new Field\Text($name);
        if ($type === 'password') {
            $field->setPassword();
        } else if ($type === 'file') {
            $field->setFileSelect();
        }
        if ($node->hasAttribute('value')) {
            $field->setValue($node->getAttribute('value'));
        }
        return [$field, self::resolveWidth($parser, $node, 200), self::resolveHeight($parser, $node, 20)];
    }

    /**
     * Resolve (and create, if new) the Document\Form for a <form> node,
     * named from id -> name -> an auto-generated name
     *
     * @param  Parser $parser
     * @param  Child  $formNode
     * @return string
     */
    protected static function resolveFormName(Parser $parser, Child $formNode): string
    {
        $name = $formNode->hasAttribute('id') ? $formNode->getAttribute('id')
            : ($formNode->hasAttribute('name') ? $formNode->getAttribute('name') : null);

        if ($name === null) {
            $name = 'form' . (count($parser->document()->getForms()) + 1);
        }

        if ($parser->document()->getForm($name) === null) {
            $parser->document()->addForm(new Document\Form($name));
        }

        return $name;
    }

    /**
     * Resolve (and create, if new) the single implicit Document\Form used
     * for form controls with no <form> ancestor
     *
     * @param  Parser $parser
     * @return string
     */
    protected static function resolveDefaultFormName(Parser $parser): string
    {
        $name = '__default__';
        if ($parser->document()->getForm($name) === null) {
            $parser->document()->addForm(new Document\Form($name));
        }
        return $name;
    }

    /**
     * Auto-generate a field name for a control with no name attribute
     *
     * @param  Parser $parser
     * @return string
     */
    protected static function autoFieldName(Parser $parser): string
    {
        return 'field' . (count($parser->getPage()->getFields()) + 1);
    }

    /**
     * Resolve a control's width from its size/cols/width HTML attribute,
     * CSS width, or a sane default
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  int    $default
     * @return float
     */
    protected static function resolveWidth(Parser $parser, Child $node, int $default): float
    {
        if (($node->getNodeName() === 'input') && $node->hasAttribute('size')) {
            return ((float) $node->getAttribute('size')) * 7.0;
        }
        if (($node->getNodeName() === 'textarea') && $node->hasAttribute('cols')) {
            return ((float) $node->getAttribute('cols')) * 7.0;
        }
        if ($node->hasAttribute('width')) {
            return (float) $node->getAttribute('width');
        }
        $styles = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        return ($styles['width'] !== null) ? (float) $styles['width'] : (float) $default;
    }

    /**
     * Resolve a control's height from its rows/height HTML attribute, CSS
     * height, or a sane default
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  int    $default
     * @return float
     */
    protected static function resolveHeight(Parser $parser, Child $node, int $default): float
    {
        if (($node->getNodeName() === 'textarea') && $node->hasAttribute('rows')) {
            return ((float) $node->getAttribute('rows')) * 16.0;
        }
        if ($node->hasAttribute('height')) {
            return (float) $node->getAttribute('height');
        }
        $styles = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        return ($styles['height'] !== null) ? (float) $styles['height'] : (float) $default;
    }

}
