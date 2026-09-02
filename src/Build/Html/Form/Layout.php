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

        // Precomputed once per <form>, so a radio group's total size is
        // known before its first option is rendered - see renderControl()'s
        // page-break-ahead-of-a-group check and its own docblock.
        $radioGroups        = self::collectRadioGroups($formNode);
        $handledRadioGroups = [];

        foreach ($formNode->getChildNodes() as $child) {
            [$consumedY, $currentY] = self::renderNode(
                $parser, $child, $formName, $startX, $consumedY, $currentY, $radioGroups, $handledRadioGroups
            );
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
     * Render one child of a <form> node - a control is converted to a
     * field; any other node has its own leaf text (if any) rendered, then
     * is recursed into so that controls/labels nested below wrapper
     * elements (a <div> or <p> wrapping a <label>+<input> pair, say) are
     * not silently dropped
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $formName
     * @param  int    $x
     * @param  float  $consumedY
     * @param  float  $currentY
     * @param  array  $radioGroups
     * @param  array  $handledRadioGroups
     * @return array
     */
    protected static function renderNode(
        Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY,
        array $radioGroups = [], array &$handledRadioGroups = []
    ): array
    {
        if (in_array($node->getNodeName(), ['input', 'select', 'textarea', 'button'])) {
            return self::renderControl($parser, $node, $formName, $x, $consumedY, $currentY, $radioGroups, $handledRadioGroups);
        }

        $text = trim((string) $node->getNodeValue());
        if ($text !== '') {
            [$consumedY, $currentY] = self::renderText($parser, $node, $text, $x, $consumedY, $currentY);
        }

        if ($node->hasChildNodes()) {
            foreach ($node->getChildNodes() as $child) {
                [$consumedY, $currentY] = self::renderNode(
                    $parser, $child, $formName, $x, $consumedY, $currentY, $radioGroups, $handledRadioGroups
                );
            }
        }

        return [$consumedY, $currentY];
    }

    /**
     * Render a node's own leaf text as simple, single-style per-line text,
     * baseline-aligned the same way Table\Layout::drawRow() places its own
     * cell text (one line-height below the cursor's top edge, not at the
     * raw, unadjusted cursor position)
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $text
     * @param  int    $x
     * @param  float  $consumedY
     * @param  float  $currentY
     * @return array
     */
    protected static function renderText(Parser $parser, Child $node, string $text, int $x, float $consumedY, float $currentY): array
    {
        $styles     = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        $fontObject = $parser->document()->getFont($styles['currentFont']);
        $wrapLength = $parser->getPage()->getWidth() - $parser->getPageRightMargin() - $x;
        $lines      = $parser->getStringLines($text, $styles['fontSize'], (int) $wrapLength, $fontObject);

        foreach ($lines as $line) {
            if ($currentY <= $parser->getPageBottomMargin()) {
                $currentY  = $parser->newPage();
                $consumedY = 0;
            }
            $parser->getPage()->addText(new Document\Page\Text($line, $styles['fontSize']), $styles['currentFont'], $x, (int) round($currentY - $styles['fontSize']));
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
     * @param  array  $radioGroups
     * @param  array  $handledRadioGroups
     * @return array
     */
    protected static function renderControl(
        Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY,
        array $radioGroups = [], array &$handledRadioGroups = []
    ): array
    {
        // Keep an HTML radio group intact on one page rather than letting it
        // straddle a page break - a split group produces two same-named
        // top-level AcroForm fields (invalid PDF field naming, and it breaks
        // radio exclusivity across the split), since groupRadioFields() only
        // ever sees one page's worth of fields at a time. This look-ahead
        // only runs once, at the FIRST not-yet-handled option of a given
        // group (tracked via $handledRadioGroups, threaded by reference
        // through the render recursion the same way $consumedY/$currentY are
        // threaded by value) - not once per option. If a single group is
        // larger than one full page's usable height, it is still allowed to
        // split (a narrow, accepted limitation - see the design spec's Error
        // Handling section) - forcing a page break here doesn't prevent
        // that, it only prevents an AVOIDABLE split.
        $isRadio = ($node->getNodeName() === 'input') && $node->hasAttribute('type')
            && (strtolower($node->getAttribute('type')) === 'radio') && $node->hasAttribute('name');

        if ($isRadio) {
            $groupName = $node->getAttribute('name');
            if (!isset($handledRadioGroups[$groupName]) && isset($radioGroups[$groupName]) && (count($radioGroups[$groupName]) >= 2)) {
                $handledRadioGroups[$groupName] = true;

                $groupHeight = 0.0;
                foreach ($radioGroups[$groupName] as $groupNode) {
                    $groupHeight += self::resolveHeight($parser, $groupNode, 14) + self::CONTROL_GAP;
                }

                if ($currentY - $groupHeight <= $parser->getPageBottomMargin()) {
                    $currentY  = $parser->newPage();
                    $consumedY = 0;
                }
            }
        }

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
     * Walk an entire <form> subtree (all descendants, not just direct
     * children) and collect every <input type="radio"> node that has a
     * `name` attribute, keyed by that name, in document order - used by
     * renderControl()'s look-ahead page-break check so a radio group's total
     * size is known before its first option is rendered, without having to
     * re-walk the subtree once per option
     *
     * @param  Child $formNode
     * @return array
     */
    protected static function collectRadioGroups(Child $formNode): array
    {
        $groups = [];
        self::collectRadioGroupsRecursive($formNode, $groups);
        return $groups;
    }

    /**
     * Recursive worker for collectRadioGroups()
     *
     * @param  Child $node
     * @param  array $groups
     * @return void
     */
    private static function collectRadioGroupsRecursive(Child $node, array &$groups): void
    {
        foreach ($node->getChildNodes() as $child) {
            if (($child->getNodeName() === 'input') && $child->hasAttribute('type')
                && (strtolower($child->getAttribute('type')) === 'radio') && $child->hasAttribute('name')) {
                $groups[$child->getAttribute('name')][] = $child;
            }
            if ($child->hasChildNodes()) {
                self::collectRadioGroupsRecursive($child, $groups);
            }
        }
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
            // Without a caption, a push button rendered as an unnamed,
            // captionless widget with nothing visible on the page (no CSS
            // border meant literally nothing rendered) - contradicting the
            // spec's "renders but performs no action" (it didn't render at
            // all). <button>...</button> uses its own text content;
            // <input type=submit|reset> uses its value attribute, falling
            // back to a sensible per-type default when absent.
            if ($tag === 'button') {
                $caption = trim((string) $node->getNodeValue());
            } else {
                $caption = $node->hasAttribute('value')
                    ? $node->getAttribute('value')
                    : (($type === 'reset') ? 'Reset' : 'Submit');
            }
            if ($caption !== '') {
                $field->setCaption($caption);
            }
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
            // Unlike checkbox (which has no siblings to collide with, so a
            // shared 'Yes' default is harmless), a valueless radio MUST be
            // left with a null value here - Compiler::prepareRadioGroup()'s
            // per-index fallback naming ('Option' . ($index + 1)) only
            // engages when getValue() === null. Defaulting to 'Yes' here
            // would give every valueless sibling in the group the same
            // export name, reproducing the exact all-options-checked bug
            // that fallback was added to prevent.
            if ($node->hasAttribute('value')) {
                $field->setValue($node->getAttribute('value'));
            }
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
     * CSS width, or a sane default - the width HTML attribute and CSS width
     * are both percent-aware (e.g. "50%"), resolved against the page width,
     * matching how Parser::addNodeToDocument()'s <img> handling already
     * treats a trailing '%' on width/height
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
        $pageWidth = $parser->getPage()->getWidth();
        if ($node->hasAttribute('width')) {
            return self::resolvePercentValue((string) $node->getAttribute('width'), $pageWidth);
        }
        $styles = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        return ($styles['width'] !== null) ? self::resolvePercentValue((string) $styles['width'], $pageWidth) : (float) $default;
    }

    /**
     * Resolve a control's height from its rows/height HTML attribute, CSS
     * height, or a sane default - the height HTML attribute and CSS height
     * are both percent-aware (e.g. "50%"), resolved against the page
     * height, matching how Parser::addNodeToDocument()'s <img> handling
     * already treats a trailing '%' on width/height
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
        $pageHeight = $parser->getPage()->getHeight();
        if ($node->hasAttribute('height')) {
            return self::resolvePercentValue((string) $node->getAttribute('height'), $pageHeight);
        }
        $styles = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        return ($styles['height'] !== null) ? self::resolvePercentValue((string) $styles['height'], $pageHeight) : (float) $default;
    }

    /**
     * Resolve a raw HTML-attribute/CSS size value, honoring a trailing '%'
     * as a percentage of the given basis (page width or height) rather
     * than a literal point value
     *
     * @param  string $value
     * @param  float  $basis
     * @return float
     */
    protected static function resolvePercentValue(string $value, float $basis): float
    {
        $value = trim($value);
        if (str_ends_with($value, '%')) {
            return $basis * ((float) rtrim($value, '%') / 100);
        }
        return (float) $value;
    }

}
