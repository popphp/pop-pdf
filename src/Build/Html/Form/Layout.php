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
use Pop\Pdf\Build\Html\Exception;
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

        // Registers every font any node in the subtree could ever need
        // BEFORE either the dry-run measurement pass or the real render
        // loop below reads a single style - see warmUpFonts()'s own
        // docblock for why this has to happen first, unconditionally, over
        // the whole subtree.
        foreach ($formNode->getChildNodes() as $child) {
            self::warmUpFonts($parser, $child);
        }

        // Precomputed once per <form>, so a radio group's total size is
        // known before its first option is rendered - see renderControl()'s
        // page-break-ahead-of-a-group check and its own docblock. $sequence
        // is a dry-run linearization of the whole subtree (see
        // linearizeForm()) used to measure a group's REAL footprint -
        // everything rendered between its first and last option, not just
        // the options themselves.
        $radioGroups        = self::collectRadioGroups($formNode);
        $handledRadioGroups = [];
        $sequence           = self::linearizeForm($parser, $formNode, $startX);

        foreach ($formNode->getChildNodes() as $child) {
            [$consumedY, $currentY] = self::renderNode(
                $parser, $child, $formName, $startX, $consumedY, $currentY, $radioGroups, $handledRadioGroups, $sequence
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
     * @param  array  $sequence
     * @return array
     */
    protected static function renderNode(
        Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY,
        array $radioGroups = [], array &$handledRadioGroups = [], array $sequence = []
    ): array
    {
        if (in_array($node->getNodeName(), ['input', 'select', 'textarea', 'button'])) {
            return self::renderControl($parser, $node, $formName, $x, $consumedY, $currentY, $radioGroups, $handledRadioGroups, $sequence);
        }

        $text = trim((string) $node->getNodeValue());
        if ($text !== '') {
            [$consumedY, $currentY] = self::renderText($parser, $node, $text, $x, $consumedY, $currentY);
        }

        if ($node->hasChildNodes()) {
            foreach ($node->getChildNodes() as $child) {
                [$consumedY, $currentY] = self::renderNode(
                    $parser, $child, $formName, $x, $consumedY, $currentY, $radioGroups, $handledRadioGroups, $sequence
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
        [$lines, $styles] = self::resolveTextLines($parser, $node, $text, $x);

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
     * Resolve a leaf-text node's wrapped lines and the styles used to draw
     * them - the shared calculation behind both renderText()'s actual
     * drawing and measureTextHeight()'s dry-run measurement, so the two
     * passes can never disagree about how a node's text wraps
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $text
     * @param  int    $x
     * @return array
     */
    protected static function resolveTextLines(Parser $parser, Child $node, string $text, int $x): array
    {
        $styles     = $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        $fontObject = $parser->document()->getFont($styles['currentFont']);
        $wrapLength = $parser->getPage()->getWidth() - $parser->getPageRightMargin() - $x;
        $lines      = $parser->getStringLines($text, $styles['fontSize'], (int) $wrapLength, $fontObject);

        return [$lines, $styles];
    }

    /**
     * Dry-run counterpart to renderText() - the total height a leaf-text
     * node's wrapped lines will consume, without drawing anything; used by
     * linearizeNode() to build the measurement sequence
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  string $text
     * @param  int    $x
     * @return float
     */
    protected static function measureTextHeight(Parser $parser, Child $node, string $text, int $x): float
    {
        [$lines, $styles] = self::resolveTextLines($parser, $node, $text, $x);
        return count($lines) * $styles['lineHeight'];
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
     * @param  array  $sequence
     * @return array
     */
    protected static function renderControl(
        Parser $parser, Child $node, string $formName, int $x, float $consumedY, float $currentY,
        array $radioGroups = [], array &$handledRadioGroups = [], array $sequence = []
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
        //
        // The group's needed height is measured via measureRadioGroupSpan()
        // against $sequence - a dry-run linearization of the whole <form>
        // (see linearizeForm()) - as everything rendered from the group's
        // first option through its last, INCLUSIVE. Real radio-button HTML
        // almost always has a <label> (or other text) between/around each
        // option, so summing only the options' own heights (the original,
        // buggy version of this look-ahead) under-measured the group and
        // still let it straddle a page break whenever labels were present.
        $isRadio = ($node->getNodeName() === 'input') && $node->hasAttribute('type')
            && (strtolower($node->getAttribute('type')) === 'radio') && $node->hasAttribute('name');

        if ($isRadio) {
            $groupName = $node->getAttribute('name');
            if (!isset($handledRadioGroups[$groupName]) && isset($radioGroups[$groupName]) && (count($radioGroups[$groupName]) >= 2)) {
                $handledRadioGroups[$groupName] = true;

                $groupHeight = self::measureRadioGroupSpan($sequence, $radioGroups[$groupName]);

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
     * Walk an entire <form> subtree and call Parser::prepareNodeStyles()
     * UNCONDITIONALLY on every single node - text nodes and control nodes
     * alike, regardless of any early-return branch elsewhere (e.g.
     * resolveHeight()'s explicit height/width attribute check) that would
     * otherwise skip it for that node.
     *
     * This exists because prepareNodeStyles() is NOT read-only: for any
     * comma-separated font-family stack (e.g. "Courier, Arial"), it resolves
     * to whichever stack entry is ALREADY registered on the document at the
     * moment it runs (order-sensitive), and it registers (addFont()) any
     * standard CSS font it resolves to that isn't registered yet - a real,
     * permanent side effect on $parser->document(). linearizeForm() (the
     * dry-run measurement pass) and the real render loop below both call
     * prepareNodeStyles() - directly or via resolveTextLines()/
     * resolveControlHeight()/applyAppearance() - but NOT on the same set of
     * nodes (resolveHeight() skips it for a control with an explicit
     * height/width, while applyAppearance() always calls it) and NOT at the
     * same point in time (the measurement pass runs to completion before the
     * render loop starts). Two passes, two different moments to resolve the
     * same font stack against a document whose registered-fonts set can
     * change in between: a node could measure against one font and render
     * against another, changing its wrapped line count and therefore its
     * real height out from under the measurement that was supposed to
     * account for it - silently reproducing the exact "group's real
     * footprint under-measured, group still splits across a page" bug this
     * whole look-ahead exists to prevent, through a new mechanism.
     *
     * Warming up every node's fonts ONCE, before either pass begins, makes
     * every font resolution in both passes read from the SAME, already-final
     * registered-fonts set - so the two passes can never disagree, no matter
     * what order anything else happens in. Must call prepareNodeStyles() on
     * a superset of whatever either pass calls it on for styling purposes;
     * this walks the whole subtree, so it always is.
     *
     * "Superset", not "exact set", is the operative word: this visits nodes
     * (wrapper elements, <option>s) that neither pass ever actually styles
     * for real, because they never render leaf text and aren't a control.
     * Before this method existed, such a node's own bad font-family
     * declaration was simply never looked at, so it was silently harmless.
     * prepareNodeStyles() throws (Html\Exception) when a declared
     * font-family can't resolve to any registered/standard font - every
     * addFont() call inside it happens in the same branch as the
     * corresponding throw (never after), so a call that throws never
     * partially registers a font. Swallowing that exception here is
     * therefore safe and lossless: it only stops THIS warm-up call from
     * failing the whole document over a style nothing ever draws. A node
     * that genuinely renders text or is a control still calls
     * prepareNodeStyles() for real later (via the actual measurement/render
     * pass) and throws exactly as it always has - this catch only guards the
     * warm-up call, never the recursion into the node's children, since a
     * node's own bad style says nothing about whether its children's styles
     * are resolvable.
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @return void
     */
    protected static function warmUpFonts(Parser $parser, Child $node): void
    {
        try {
            $parser->prepareNodeStyles($node->getNodeName(), $node->getAttributes());
        } catch (Exception) {
            // Intentionally swallowed - see docblock above.
        }

        if ($node->hasChildNodes()) {
            foreach ($node->getChildNodes() as $child) {
                self::warmUpFonts($parser, $child);
            }
        }
    }

    /**
     * Build a dry-run, ordered linearization of an entire <form> subtree -
     * one [node, heightContribution] pair per node that would either be
     * rendered as a control or contribute its own leaf text - mirroring
     * renderNode()'s traversal exactly, but only accumulating height instead
     * of drawing anything (no side effects: never touches $parser's page or
     * cursor state). Built ONCE per <form>, before the real render loop
     * starts, so it can be reused by measureRadioGroupSpan() without
     * re-walking the subtree per group/option. Same "measure separately from
     * render" idea as Table\Layout::measureRowHeight() vs. drawRow().
     *
     * @param  Parser $parser
     * @param  Child  $formNode
     * @param  int    $x
     * @return array
     */
    protected static function linearizeForm(Parser $parser, Child $formNode, int $x): array
    {
        $sequence = [];
        foreach ($formNode->getChildNodes() as $child) {
            self::linearizeNode($parser, $child, $x, $sequence);
        }
        return $sequence;
    }

    /**
     * Recursive worker for linearizeForm() - mirrors renderNode()'s own
     * branching (control tag vs. leaf text vs. recurse into children)
     * exactly, appending a [node, heightContribution] pair per accounted-for
     * node instead of rendering it
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @param  int    $x
     * @param  array  $sequence
     * @return void
     */
    private static function linearizeNode(Parser $parser, Child $node, int $x, array &$sequence): void
    {
        if (in_array($node->getNodeName(), ['input', 'select', 'textarea', 'button'])) {
            $sequence[] = [$node, self::resolveControlHeight($parser, $node) + self::CONTROL_GAP];
            return;
        }

        $text = trim((string) $node->getNodeValue());
        if ($text !== '') {
            $sequence[] = [$node, self::measureTextHeight($parser, $node, $text, $x)];
        }

        if ($node->hasChildNodes()) {
            foreach ($node->getChildNodes() as $child) {
                self::linearizeNode($parser, $child, $x, $sequence);
            }
        }
    }

    /**
     * Measure a radio group's real vertical span against a linearized
     * $sequence (see linearizeForm()): the index of the FIRST entry whose
     * node belongs to the group through the index of the LAST such entry,
     * summing every entry's height contribution in between INCLUSIVE - not
     * just the radio entries, everything interleaved (labels, other text,
     * anything else). Any content BEFORE the group's first option is
     * excluded on purpose - it has already been rendered (and already
     * reflected in the caller's $currentY) by the time renderControl()'s
     * look-ahead runs at that first option.
     *
     * @param  array $sequence
     * @param  array $groupNodes
     * @return float
     */
    protected static function measureRadioGroupSpan(array $sequence, array $groupNodes): float
    {
        $groupIds = [];
        foreach ($groupNodes as $groupNode) {
            $groupIds[spl_object_id($groupNode)] = true;
        }

        $firstIndex = null;
        $lastIndex  = null;

        foreach ($sequence as $index => $entry) {
            if (isset($groupIds[spl_object_id($entry[0])])) {
                $firstIndex ??= $index;
                $lastIndex = $index;
            }
        }

        if ($firstIndex === null) {
            return 0.0;
        }

        $span = 0.0;
        for ($i = $firstIndex; $i <= $lastIndex; $i++) {
            $span += $sequence[$i][1];
        }

        return $span;
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
            return [$field, self::resolveWidth($parser, $node, 300), self::resolveControlHeight($parser, $node)];
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
            return [$field, self::resolveWidth($parser, $node, 150), self::resolveControlHeight($parser, $node)];
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
            return [$field, self::resolveWidth($parser, $node, 80), self::resolveControlHeight($parser, $node)];
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
            return [$field, self::resolveWidth($parser, $node, 14), self::resolveControlHeight($parser, $node)];
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
            return [$field, self::resolveWidth($parser, $node, 14), self::resolveControlHeight($parser, $node)];
        }

        if ($type === 'hidden') {
            $field = new Field\Text($name);
            if ($node->hasAttribute('value')) {
                $field->setValue($node->getAttribute('value'));
            }
            return [$field, 0.0, self::resolveControlHeight($parser, $node)];
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
        return [$field, self::resolveWidth($parser, $node, 200), self::resolveControlHeight($parser, $node)];
    }

    /**
     * Resolve a form-control node's own height, honoring its rows/height
     * HTML attribute or CSS height the same way resolveHeight() always has,
     * but falling back to the RIGHT per-tag/type default (see
     * controlDefaultHeight()) instead of a single hardcoded number - the
     * single source of truth buildField() (real render) and
     * linearizeNode()'s dry-run measurement pass both call, so the two can
     * never disagree about how tall a control is
     *
     * @param  Parser $parser
     * @param  Child  $node
     * @return float
     */
    protected static function resolveControlHeight(Parser $parser, Child $node): float
    {
        // A hidden field never renders, so - like the original hardcoded
        // buildField() branch - its height is always 0, regardless of any
        // height attribute/CSS a caller might have set on it.
        if (($node->getNodeName() === 'input') && $node->hasAttribute('type')
            && (strtolower($node->getAttribute('type')) === 'hidden')) {
            return 0.0;
        }

        return self::resolveHeight($parser, $node, self::controlDefaultHeight($node));
    }

    /**
     * The sane per-tag/type default height buildField() has always used per
     * control kind, factored out so resolveControlHeight() is the only place
     * that needs to know it
     *
     * @param  Child $node
     * @return int
     */
    protected static function controlDefaultHeight(Child $node): int
    {
        $tag  = $node->getNodeName();
        $type = $node->hasAttribute('type') ? strtolower($node->getAttribute('type')) : 'text';

        if ($tag === 'textarea') {
            return 60;
        }
        if ($tag === 'select') {
            return 20;
        }
        if (($tag === 'button') || ($type === 'submit') || ($type === 'reset')) {
            return 24;
        }
        if (($type === 'checkbox') || ($type === 'radio')) {
            return 14;
        }
        if ($type === 'hidden') {
            return 0;
        }

        // text, email, password, file, tel, url, search, or any unrecognized type
        return 20;
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
