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
namespace Pop\Pdf\Build\Html;

use Pop\Css;
use Pop\Color\Color;
use Pop\Dom\Child;
use Pop\Pdf\Build\Html\Form;
use Pop\Pdf\Build\Html\Table;
use Pop\Pdf\Document;

/**
 * Pdf HTML parser class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.1.0
 */
class Parser
{

    /**
     * PDF document
     * @var Document
     */
    protected Document $document;

    /**
     * HTML object or array of HTML objects
     * @var Child|array|null
     */
    protected Child|array|null $html = null;

    /**
     * CSS object
     * @var ?Css\Css
     */
    protected ?Css\Css $css = null;

    /**
     * Page size
     * @var string|array
     */
    protected string|array $pageSize = 'LETTER';

    /**
     * Page margins
     * @var array
     */
    protected array $pageMargins = [
        'top'    => 80,
        'right'  => 60,
        'bottom' => 60,
        'left'   => 60
    ];

    /**
     * Default styles
     * @var array
     */
    protected array $defaultStyles = [
        'font-family' => 'Arial',
        'font-size'   => 10,
        'font-weight' => 'normal',
        'color'       => [0, 0, 0],
        'line-height' => 14
    ];

    /**
     * Current x-position
     * @var int
     */
    protected int $x = 0;

    /**
     * Current y-position
     * @var int
     */
    protected int $y = 0;

    /**
     * Current page object
     * @var ?Document\Page
     */
    protected ?Document\Page $page = null;

    /**
     * HTML file directory
     * @var ?string
     */
    protected ?string $fileDir = null;

    /**
     * Text wrap object
     * @var ?Document\Page\Text\Wrap
     */
    protected ?Document\Page\Text\Wrap $textWrap = null;

    /**
     * Y-override
     * @var ?int
     */
    protected ?int $yOverride = null;

    /**
     * Constructor
     *
     * Instantiate the HTML parser object
     *
     * @param Document          $document
     * @param string|array|null $pageSize
     */
    public function __construct(Document $document = new Document(), string|array|null $pageSize = null)
    {
        $this->setDocument($document);
        $this->createDefaultStyles();
        if ($pageSize !== null) {
            if (is_array($pageSize) && (count($pageSize) == 2)) {
                $this->setPageSize($pageSize[0], $pageSize[1]);
            } else {
                $this->setPageSize($pageSize);
            }
        }
    }

    /**
     * Parse HTML string
     *
     * @param  string            $htmlString
     * @param  Document          $document
     * @param  string|array|null $pageSize
     * @return Parser
     */
    public static function parseString(
        string $htmlString, Document $document = new Document(), string|array|null $pageSize = null
    ): Parser
    {
        $html = new self($document, $pageSize);
        $html->parseHtml($htmlString);

        return $html;
    }

    /**
     * Parse $html from file
     *
     * @param  string            $htmlFile
     * @param  Document          $document
     * @param  string|array|null $pageSize
     * @throws Exception
     * @return Parser
     */
    public static function parseFile(
        string $htmlFile, Document $document = new Document(), string|array|null $pageSize = null
    ): Parser
    {
        $html = new self($document, $pageSize);
        $html->parseHtmlFile($htmlFile);

        return $html;
    }

    /**
     * Parse $html from URI
     *
     * @param string            $htmlUri
     * @param Document          $document
     * @param string|array|null $pageSize
     * @return Parser
     */
    public static function parseUri(
        string $htmlUri, Document $document = new Document(), string|array|null $pageSize = null
    ): Parser
    {
        $html = new self($document, $pageSize);
        $html->parseHtmlUri($htmlUri);

        return $html;
    }

    /**
     * Set document
     *
     * @param  Document $document
     * @return Parser
     */
    public function setDocument(Document $document): Parser
    {
        $this->document = $document;
        return $this;
    }

    /**
     * Parse HTML string
     *
     * @param  string  $htmlString
     * @param  ?string $basePath
     * @return Parser
     */
    public function parseHtml(string $htmlString, ?string $basePath = null): Parser
    {
        if ($basePath !== null) {
            $this->fileDir = $basePath;
        }
        $htmlString  = $this->extractStyleBlocks($htmlString);
        $this->html  = Child::parseString($this->normalizeHtmlEncoding($htmlString));
        return $this;
    }

    /**
     * Extract <style> blocks from raw HTML and feed them into the CSS engine
     *
     * Pop\Dom\Child::parseString() builds its tree from a DOMDocument, and
     * libxml's HTML parser stores <style>/<script> content as a CDATA
     * section rather than a text node - a node type Child::parseString()
     * doesn't walk, so a <style> block's CSS text is unreachable once the
     * string has been handed off to the DOM. Pulling it out of the raw
     * string first (and removing the tag so it never reaches the DOM, where
     * it would otherwise render as literal text) sidesteps that entirely.
     *
     * @param  string $htmlString
     * @return string
     */
    protected function extractStyleBlocks(string $htmlString): string
    {
        return (string)preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function ($matches) {
            $this->css->parseCss($matches[1]);
            return '';
        }, $htmlString);
    }

    /**
     * Normalize HTML string encoding
     *
     * Pop\Dom\Child::parseString() loads the string into a DOMDocument with
     * no charset hint, and DOMDocument::loadHTML() assumes ISO-8859-1 for
     * any markup that doesn't declare its own charset - misinterpreting
     * multi-byte UTF-8 (e.g. non-Latin scripts) and corrupting it into
     * mojibake. Converting non-ASCII characters to numeric HTML entities
     * beforehand sidesteps that assumption, since entities are plain ASCII
     * and decode correctly regardless of which encoding libxml assumes.
     *
     * @param  string $htmlString
     * @return string
     */
    protected function normalizeHtmlEncoding(string $htmlString): string
    {
        return mb_encode_numericentity($htmlString, [0x80, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
    }

    /**
     * Parse HTML string from file
     *
     * @param  string $htmlFile
     * @throws Exception
     * @return Parser
     */
    public function parseHtmlFile(string $htmlFile): Parser
    {
        if (!file_exists($htmlFile)) {
            throw new Exception('Error: That file does not exist.');
        }
        return $this->parseHtml(file_get_contents($htmlFile), dirname(realpath($htmlFile)));
    }

    /**
     * Parse HTML string from URI
     *
     * @param  string $htmlUri
     * @return Parser
     */
    public function parseHtmlUri(string $htmlUri): Parser
    {
        return $this->parseHtml(file_get_contents($htmlUri));
    }

    /**
     * Parse CSS string
     *
     * @param  string $cssString
     * @return Parser
     */
    public function parseCss(string $cssString): Parser
    {
        // $this->css is never null at this point - the constructor always
        // calls createDefaultStyles(), which unconditionally initializes it.
        $this->css->parseCss($cssString);
        return $this;
    }

    /**
     * Parse CSS file
     *
     * @param  string $cssFile
     * @throws \Pop\Css\Exception
     * @return Parser
     */
    public function parseCssFile(string $cssFile): Parser
    {
        // $this->css is never null here - see parseCss()'s comment.
        $this->css->parseCssFile($cssFile);
        return $this;
    }

    /**
     * Parse CSS URI
     *
     * @param  string $cssUri
     * @return Parser
     */
    public function parseCssUri(string $cssUri): Parser
    {
        // $this->css is never null here - see parseCss()'s comment.
        $this->css->parseCssUri($cssUri);
        return $this;
    }

    /**
     * Get document
     *
     * @return Document
     */
    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * Get document (alias)
     *
     * @return Document
     */
    public function document(): Document
    {
        return $this->document;
    }

    /**
     * Get the current page
     *
     * @return ?Document\Page
     */
    public function getPage(): ?Document\Page
    {
        return $this->page;
    }

    /**
     * Set page size
     *
     * @param  mixed $size
     * @param  mixed $height
     * @return Parser
     */
    public function setPageSize(mixed $size, mixed $height = null): Parser
    {
        $this->pageSize = ($height !== null) ? ['width' => $size, 'height' => $height] : $size;

        return $this;
    }

    /**
     * Get page size
     *
     * @return string|array
     */
    public function getPageSize(): string|array
    {
        return $this->pageSize;
    }

    /**
     * Set page margins
     *
     * @param  int $top
     * @param  int $right
     * @param  int $bottom
     * @param  int $left
     * @return Parser
     */
    public function setPageMargins(int $top, int $right, int $bottom, int $left): Parser
    {
        $this->pageMargins['top']    = $top;
        $this->pageMargins['right']  = $right;
        $this->pageMargins['bottom'] = $bottom;
        $this->pageMargins['left']   = $left;
        return $this;
    }

    /**
     * Set page top margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageTopMargin(int $margin): Parser
    {
        $this->pageMargins['top'] = $margin;
        return $this;
    }

    /**
     * Set page right margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageRightMargin(int $margin): Parser
    {
        $this->pageMargins['right'] = $margin;
        return $this;
    }

    /**
     * Set page bottom margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageBottomMargin(int $margin): Parser
    {
        $this->pageMargins['bottom'] = $margin;
        return $this;
    }

    /**
     * Set page left margin
     *
     * @param  int $margin
     * @return Parser
     */
    public function setPageLeftMargin(int $margin): Parser
    {
        $this->pageMargins['left'] = $margin;
        return $this;
    }

    /**
     * Set a default style
     *
     * @param  string $property
     * @param  string $value
     * @throws Exception
     * @return Parser
     */
    public function setDefaultStyle(string $property, string $value): Parser
    {
        $this->defaultStyles[$property] = match ($property) {
            'font-size', 'line-height' => $this->parseCssSizeToInt($property, $value),
            'color'                    => $this->parseCssColorToRgbArray($value),
            default                    => $value,
        };

        return $this;
    }

    /**
     * Parse a CSS size string (e.g. "26" or "26px") into an int
     *
     * @param  string $property
     * @param  string $value
     * @throws Exception
     * @return int
     */
    protected function parseCssSizeToInt(string $property, string $value): int
    {
        if (!preg_match('/^-?\d+(\.\d+)?/', trim($value))) {
            throw new Exception("Error: The value for '" . $property . "' must be numeric.");
        }

        return (int)$value;
    }

    /**
     * Set x-position
     *
     * @param  int $x
     * @return Parser
     */
    public function setX(int $x): Parser
    {
        $this->x = $x;
        return $this;
    }

    /**
     * Set y-position
     *
     * @param  int $y
     * @return Parser
     */
    public function setY(int $y): Parser
    {
        $this->y = $y;
        return $this;
    }

    /**
     * Set the y-position override for the next node (mirrors the mechanism
     * already used internally for multi-page paragraph overflow)
     *
     * @param  ?int $y
     * @return Parser
     */
    public function setYOverride(?int $y): Parser
    {
        $this->yOverride = $y;
        return $this;
    }

    /**
     * Get page margins
     *
     * @return array
     */
    public function getPageMargins(): array
    {
        return $this->pageMargins;
    }

    /**
     * Get page top margin
     *
     * @return int
     */
    public function getPageTopMargin(): int
    {
        return $this->pageMargins['top'];
    }

    /**
     * Get page right margin
     *
     * @return int
     */
    public function getPageRightMargin(): int
    {
        return $this->pageMargins['right'];
    }

    /**
     * Get page bottom margin
     *
     * @return int
     */
    public function getPageBottomMargin(): int
    {
        return $this->pageMargins['bottom'];
    }

    /**
     * Get page left margin
     *
     * @return int
     */
    public function getPageLeftMargin(): int
    {
        return $this->pageMargins['left'];
    }

    /**
     * Get x-position
     *
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Get y-position
     *
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Draw a background-filled and/or bordered box
     *
     * @param  float $x
     * @param  float $topY
     * @param  float $width
     * @param  float $height
     * @param  array $styles
     * @return void
     */
    public function drawBox(float $x, float $topY, float $width, float $height, array $styles): void
    {
        if (empty($styles['backgroundColor']) && empty($styles['borderWidth'])) {
            return;
        }

        if (!empty($styles['backgroundColor'])) {
            $path = new Document\Page\Path(Document\Page\Path::FILL);
            $path->setFillColor(new Color\Rgb($styles['backgroundColor'][0], $styles['backgroundColor'][1], $styles['backgroundColor'][2]));
            $path->drawRectangle($x, $topY - $height, $width, $height);
            $this->page->addPath($path);
        }

        if (!empty($styles['borderWidth'])) {
            $borderColor = $styles['borderColor'] ?? [0, 0, 0];
            $path = new Document\Page\Path(Document\Page\Path::STROKE);
            $path->setStrokeColor(new Color\Rgb($borderColor[0], $borderColor[1], $borderColor[2]));
            $path->setStroke($styles['borderWidth']);
            $path->drawRectangle($x, $topY - $height, $width, $height);
            $this->page->addPath($path);
        }
    }

    /**
     * Get a default style
     *
     * font-size/line-height are stored as int and color as an [r, g, b]
     * array (see setDefaultStyle()), so this can't be narrowed to ?string.
     *
     * @param  string $property
     * @return string|int|array|null
     */
    public function getDefaultStyle(string $property): string|int|array|null
    {
        return $this->defaultStyles[$property] ?? null;
    }

    /**
     * Get default styles
     *
     * @return array
     */
    public function getDefaultStyles(): array
    {
        return $this->defaultStyles;
    }

    /**
     * Get styles
     *
     * @return ?Css\Css
     */
    public function getCss(): ?Css\Css
    {
        return $this->css;
    }

    /**
     * Get HTML nodes
     *
     * @return array|Child|null
     */
    public function getHtml(): array|Child|null
    {
        return $this->html;
    }

    /**
     * Prepare for conversion of HTML into PDF objects
     *
     * @return array|Child|null
     */
    public function prepare(): array|Child|null
    {
        $htmlNodes = null;
        if ($this->html instanceof Child) {
            foreach ($this->html->getChildNodes() as $child) {
                if ($child->getNodeName() == 'head') {
                    foreach ($child->getChildNodes() as $c) {
                        if (($c->getNodeName() == 'link') && ($c->hasAttribute('href')) &&
                            ($c->hasAttribute('type')) && ($c->getAttribute('type') == 'text/css')) {
                            // $this->css is never null here - see
                            // parseCss()'s comment.
                            $this->css->parseCssFile($this->fileDir . '/' . $c->getAttribute('href'));
                        }
                    }
                } else if ($child->getNodeName() == 'body') {
                    $htmlNodes = $child;
                }
            }
        }

        if ($htmlNodes === null) {
            $htmlNodes = $this->html;
        }

        return $htmlNodes;
    }

    /**
     * Process conversion of HTML into PDF objects
     *
     * @throws Exception
     * @return Document
     */
    public function process(): Document
    {
        $htmlNodes = $this->prepare();

        if ($htmlNodes instanceof Child) {
            foreach ($htmlNodes->getChildNodes() as $i => $child) {
                $this->addNodeToDocument($child, $i);
            }
        } else {
            foreach ($htmlNodes as $i => $child) {
                $this->addNodeToDocument($child, $i);
            }
        }

        return $this->document;
    }

    /**
     * Add node to document
     *
     * @param  Child $child
     * @param  int   $i
     * @throws Exception|\Pop\Pdf\Exception
     * @return void
     */
    protected function addNodeToDocument(Child $child, int $i = 0): void
    {
        $styles   = $this->prepareNodeStyles($child->getNodeName(), $child->getAttributes());
        $currentX = $this->getCurrentX();

        if ($this->yOverride !== null) {
            $currentY        = $this->yOverride;
            $this->yOverride = null;
            // $this->y was left at whatever the last newPage() inside the
            // orphan loop set it to (0) - not where $currentY (the override)
            // actually is on the page. Resync it now, so the NODE AFTER this
            // one (which reads $this->y, not $currentY) advances from the
            // real position instead of an assumed-zero baseline.
            if ($this->page !== null) {
                $this->y = (int)round($this->page->getHeight() - $this->pageMargins['top'] - $currentY);
            }
        } else {
            $currentY = $this->getCurrentY(($i != 0) ? $styles['marginBottom'] ?? 0 : 0);
        }

        $wrapLength = ($this->x > $this->pageMargins['left']) ?
            $this->page->getWidth() - $this->pageMargins['right'] - $this->x :
            $this->page->getWidth() - $this->pageMargins['right'] - $this->pageMargins['left'];

        // Image node
        if ($child->getNodeName() == 'img') {
            $image = Document\Page\Image::createImageFromFile($this->fileDir . '/' . $child->getAttribute('src'));
            $width = null;
            $height = null;
            $align = null;

            if ($child->hasAttribute('width')) {
                $width = (strpos($child->getAttribute('width'), '%')) ?
                    $this->page->getWidth() * ((int)$child->getAttribute('width') / 100) : (int)$child->getAttribute('width');
            } else if ($child->hasAttribute('height')) {
                $height = (strpos($child->getAttribute('height'), '%')) ?
                    $this->page->getHeight() * ((int)$child->getAttribute('height') / 100) : (int)$child->getAttribute('height');
            } else if ($styles['width'] !== null) {
                $width = (strpos($styles['width'], '%')) ?
                    $this->page->getWidth() * ((int)$styles['width'] / 100) : (int)$styles['width'];
            } else if ($styles['height'] !== null) {
                $height = (strpos($styles['height'], '%')) ?
                    $this->page->getHeight() * ((int)$styles['height'] / 100) : (int)$styles['height'];
            }

            if ($width !== null) {
                $image->resizeToWidth($width);
            } else if ($height !== null) {
                $image->resizeToHeight($height);
            }

            if ($height === null) {
                $height = ($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight();
            }

            if ($child->hasAttribute('align')) {
                $align = strtoupper($child->getAttribute('align'));
            } else if (isset($styles['float'])) {
                $align = strtoupper($styles['float']);
            }

            if ($align == 'LEFT') {
                $box = [
                    'left' => $currentX,
                    'right' => $currentX + $width + ($styles['marginRight'] ?? 0),
                    'top' => $currentY,
                    'bottom' => $currentY - $height - ($styles['marginBottom'] ?? 0)
                ];
                $this->textWrap = new Document\Page\Text\Wrap('RIGHT', $this->pageMargins['left'], $this->page->getWidth() - $this->pageMargins['right'], $box);
            } else if ($align == 'RIGHT') {
                $box = [
                    'left' => $this->page->getWidth() - $this->pageMargins['right'] - $width - ($styles['marginLeft'] ?? 0),
                    'right' => $this->page->getWidth() - $this->pageMargins['right'],
                    'top' => $currentY,
                    'bottom' => $currentY - $height - ($styles['marginBottom'] ?? 0)
                ];
                $this->textWrap = new Document\Page\Text\Wrap('LEFT', $this->pageMargins['left'], $this->page->getWidth() - $this->pageMargins['right'], $box);
            }

            if ($this->textWrap !== null) {
                $newY = $currentY - (($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight());
                if ($align == 'RIGHT') {
                    $this->page->addImage($image, ($this->page->getWidth() - $this->pageMargins['right'] - $width), $newY);
                } else {
                    $this->page->addImage($image, $currentX, $newY);
                }
                $currentY -= $styles['lineHeight'];
                $this->y += (int)round($styles['lineHeight']);
            } else {
                $currentY -= ($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight();
                $this->y += (int)round(($image->getResizedHeight() !== null) ? $image->getResizedHeight() : $image->getHeight());
                $this->page->addImage($image, $currentX, $currentY);
                $currentY -= $styles['lineHeight'];
                $this->y += (int)round($styles['lineHeight']);
            }
        // Table node
        } else if ($child->getNodeName() == 'table') {
            $tableWidth = $this->page->getWidth() - $this->pageMargins['left'] - $this->pageMargins['right'];
            Table\Layout::render($this, $child, $styles, $this->pageMargins['left'], (int) $tableWidth, (float) $currentY);

        // Form node
        } else if ($child->getNodeName() == 'form') {
            Form\Layout::render($this, $child, $styles, $currentX, (float) $currentY);

        // Bare form control node (no <form> ancestor)
        } else if (in_array($child->getNodeName(), ['input', 'select', 'textarea', 'button'])) {
            Form\Layout::renderBareControl($this, $child, $currentX, (float) $currentY);

        // Text node
        } else {
            if (!empty($styles['backgroundColor']) || !empty($styles['borderWidth'])) {
                $boxWidth  = $wrapLength;
                $boxText   = trim((string)$child->getNodeValue());
                if ($boxText !== '') {
                    $fontObject = $this->document->getFont($styles['currentFont']);
                    $lines      = $this->getStringLines($boxText, $styles['fontSize'], $boxWidth, $fontObject);
                    $lineCount  = max(1, count($lines));
                } else {
                    $lineCount = 1;
                }
                $boxHeight = ($lineCount * $styles['lineHeight']) + $styles['paddingTop'] + $styles['paddingBottom'];
                $this->drawBox($currentX, $currentY + $styles['paddingTop'], $boxWidth, $boxHeight, $styles);
            }

            if ($this->textWrap !== null) {
                $box = $this->textWrap->getBox();
                if ($this->textWrap->isRight()) {
                    $startX = $box['right'];
                    $startY = $currentY;
                    $edgeX  = $wrapLength;
                    $edgeY  = $box['bottom'];
                } else {
                    $startX = $currentX;
                    $startY = $currentY;
                    $edgeX  = $box['left'] - 40;
                    $edgeY  = $box['bottom'];
                }
            } else {
                $startX = $currentX;
                $startY = $currentY;
                $edgeX  = $wrapLength;
                $edgeY  = $this->pageMargins['bottom'];
            }

            $textStream = new Document\Page\Text\Stream($startX, $startY, $edgeX, $edgeY);
            $textStream->setCurrentStyle(
                $styles['currentFont'],
                $styles['fontSize'],
                new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2]),
                $styles['textAlign']
            );
            $streamY = $styles['lineHeight'] ?? null;
            if (!empty($child->getNodeValue())) {
                $textStream->addText($child->getNodeValue(), $streamY);
            }

            if ($child->hasChildNodes()) {
                $this->addNestedInlineText($textStream, $child->getChildNodes(), $styles);
            }

            $this->page->addTextStream($textStream);

            $orphanStream = clone $textStream;
            $hasOrphans   = false;
            $previousOrphanContent = null;

            while ($orphanStream->hasOrphans($this->document->getFonts())) {
                $orphanStream = $orphanStream->getOrphanStream();

                // A single line whose own height (e.g. an oversized CSS
                // line-height) or a single word wider than the page can
                // never fit no matter how many fresh pages we try - detected
                // here as two consecutive iterations producing identical
                // remaining content (getOrphanStream() trimmed nothing both
                // times). Stop retrying rather than looping forever creating
                // empty pages: the previous iteration's best-effort draw
                // already happened, matching this project's established
                // no-splitting-when-it-can't-fit approach.
                $currentOrphanContent = implode('|', array_column($orphanStream->getTextStreams(), 'string'));
                if ($currentOrphanContent === $previousOrphanContent) {
                    break;
                }
                $previousOrphanContent = $currentOrphanContent;

                if ($orphanStream->getCurrentY() <= $this->pageMargins['bottom']) {
                    $currentY = $this->newPage();
                    $orphanStream->setCurrentY($currentY);
                }
                // A float's box (if any) doesn't carry across a page break -
                // once we've moved to a fresh page, the continuation always
                // gets the full page width/height, matching what happens
                // when no new page was needed above. Without this reset,
                // the OLD float-derived edgeX/edgeY/startX kept constraining
                // the stream on every subsequent page too.
                $orphanStream->setStartX($this->pageMargins['left']);
                $orphanStream->setEdgeX($wrapLength);
                $orphanStream->setEdgeY($this->pageMargins['bottom']);
                $orphanStream->setStartY($orphanStream->getCurrentY());

                $orphanStream->setCurrentX($currentX);
                $this->page->addTextStream($orphanStream);

                $orphanStream = clone $orphanStream;
                $hasOrphans = true;
            }

            if ($hasOrphans) {
                $this->yOverride = $orphanStream->getCurrentY();
            } else {
                $this->yOverride = null;
                $this->y += (int)round($textStream->measureHeight($this->document->getFonts())
                    + ((!empty($styles['marginBottom'])) ? $styles['marginBottom'] : 0));
            }
        }
    }

    /**
     * Recursively walk a node's descendants, adding each one's own text to the stream
     *
     * A text node's value attaches only to its immediate parent element, which can sit
     * arbitrarily deep below the block element addNodeToDocument() is rendering (e.g.
     * <p><b><i>text</i></b></p> - the text belongs to <i>, two levels below <b>). Only
     * descending into the immediate child, as opposed to recursing all the way down,
     * would silently drop that text.
     *
     * @param  Document\Page\Text\Stream $textStream
     * @param  array                     $nodes
     * @param  array                     $parentStyles
     * @return void
     */
    protected function addNestedInlineText(Document\Page\Text\Stream $textStream, array $nodes, array $parentStyles): void
    {
        foreach ($nodes as $node) {
            $styles = $this->prepareNodeStyles($node->getNodeName(), $node->getAttributes(), $parentStyles);
            $textStream->setCurrentStyle(
                $styles['currentFont'],
                $styles['fontSize'],
                new Color\Rgb($styles['color'][0], $styles['color'][1], $styles['color'][2])
            );
            $streamY = $styles['lineHeight'] ?? null;
            if (!empty($node->getNodeValue())) {
                $textStream->addText($node->getNodeValue(), $streamY, ($node->getNodeName() == 'br'));
            }

            if ($node->hasChildNodes()) {
                $this->addNestedInlineText($textStream, $node->getChildNodes(), $styles);
            }
        }
    }

    /**
     * Parse a CSS color string into an RGB array, tolerating a value that Color::parse()
     * already resolves to Color\Rgb (which has no toRgb() method to convert from itself)
     *
     * @param  string $colorString
     * @throws Exception
     * @return array
     */
    protected function parseCssColorToRgbArray(string $colorString): array
    {
        try {
            $rgbColor = $this->toRgbColor(Color::parse($colorString));
        } catch (Color\Exception $exception) {
            throw new Exception("Error: The color value '" . $colorString . "' is not in a supported CSS color format.");
        }

        return $rgbColor->toArray(false);
    }

    /**
     * Normalize any Color\ColorInterface value to Color\Rgb
     *
     * ColorInterface doesn't declare toRgb() (Color\Rgb has no such method to
     * convert from itself), so every other concrete color type it might
     * resolve to is matched explicitly here.
     *
     * @param  Color\ColorInterface $color
     * @return Color\Rgb
     */
    protected function toRgbColor(Color\ColorInterface $color): Color\Rgb
    {
        return match (true) {
            $color instanceof Color\Rgb      => $color,
            $color instanceof Color\Cmyk     => $color->toRgb(),
            $color instanceof Color\Grayscale => $color->toRgb(),
            $color instanceof Color\Hex      => $color->toRgb(),
            $color instanceof Color\Hsb      => $color->toRgb(),
            $color instanceof Color\Hsl      => $color->toRgb(),
            $color instanceof Color\Hsv      => $color->toRgb(),
            $color instanceof Color\Hwb      => $color->toRgb(),
            $color instanceof Color\Lab      => $color->toRgb(),
            $color instanceof Color\Lch      => $color->toRgb(),
            $color instanceof Color\Oklab    => $color->toRgb(),
            $color instanceof Color\Oklch    => $color->toRgb(),
            default => throw new Exception('Error: That CSS color type is not supported.'),
        };
    }

    /**
     * Create default styles
     *
     * @return void
     */
    protected function createDefaultStyles(): void
    {
        $h1 = new Css\Selector('h1');
        $h1['margin-bottom'] = '18px';
        $h1['font-size']     = '32px';
        $h1['font-weight']   = 'bold';

        $h2 = new Css\Selector('h2');
        $h2['margin-bottom'] = '18px';
        $h2['font-size']     = '28px';
        $h2['font-weight']   = 'bold';

        $h3 = new Css\Selector('h3');
        $h3['margin-bottom'] = '16px';
        $h3['font-size']     = '24px';
        $h3['font-weight']   = 'bold';

        $h4 = new Css\Selector('h4');
        $h4['margin-bottom'] = '14px';
        $h4['font-size']     = '20px';
        $h4['font-weight']   = 'bold';

        $h5 = new Css\Selector('h5');
        $h5['margin-bottom'] = '12px';
        $h5['font-size']     = '16px';
        $h5['font-weight']   = 'bold';

        $h6 = new Css\Selector('h6');
        $h6['margin-bottom'] = '10px';
        $h6['font-size']     = '14px';
        $h6['font-weight']   = 'bold';

        $p = new Css\Selector('p');
        $p['margin-bottom'] = '24px';
        $p['font-size']     = '12px';

        $a = new Css\Selector('a');
        $a['color'] = new Color\Rgb(0, 0, 255);

        $strong = new Css\Selector('strong');
        $strong['font-weight'] = 'bold';

        $em = new Css\Selector('em');
        $em['font-style'] = 'italic';

        $this->css = new Css\Css();
        $this->css->addSelectors([$h1, $h2, $h3, $h4, $h5, $h6, $p, $a, $strong, $em]);

        if (!($this->document->hasFont('Arial'))) {
            $this->document->addFont(new Document\Font('Arial'));
        }
        if (!($this->document->hasFont('Arial,Bold'))) {
            $this->document->addFont(new Document\Font('Arial,Bold'));
        }
        if (!($this->document->hasFont('Arial,Italic'))) {
            $this->document->addFont(new Document\Font('Arial,Italic'));
        }
        if (!($this->document->hasFont('Arial,BoldItalic'))) {
            $this->document->addFont(new Document\Font('Arial,BoldItalic'));
        }
        if (!($this->document->hasFont('TimesNewRoman'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman'));
        }
        if (!($this->document->hasFont('TimesNewRoman,Bold'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,Bold'));
        }
        if (!($this->document->hasFont('TimesNewRoman,Italic'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,Italic'));
        }
        if (!($this->document->hasFont('TimesNewRoman,BoldItalic'))) {
            $this->document->addFont(new Document\Font('TimesNewRoman,BoldItalic'));
        }
    }

    /**
     * Prepare node styles
     *
     * @param  string $name
     * @param  array  $attribs
     * @param  array  $currentStyles
     * @throws Exception
     * @return array
     */
    public function prepareNodeStyles(string $name, array $attribs = [], array $currentStyles = []): array
    {
        $styles = [
            'currentFont'   => null,
            'fontFamily'    => $currentStyles['fontFamily'] ?? $this->defaultStyles['font-family'],
            'fontSize'      => $currentStyles['fontSize'] ?? $this->defaultStyles['font-size'],
            'fontWeight'    => $currentStyles['fontWeight'] ?? $this->defaultStyles['font-weight'],
            'float'         => null,
            'width'         => null,
            'height'        => null,
            'color'         => $currentStyles['color'] ?? $this->defaultStyles['color'],
            'lineHeight'    => $currentStyles['lineHeight'] ?? $this->defaultStyles['line-height'],
            'marginTop'     => 0,
            'paddingTop'    => 0,
            'marginRight'   => 0,
            'paddingRight'  => 0,
            'marginBottom'  => 0,
            'paddingBottom' => 0,
            'marginLeft'      => 0,
            'paddingLeft'     => 0,
            'textAlign'       => null,
            'borderWidth'     => 0,
            'borderColor'     => [0, 0, 0],
            'backgroundColor' => null
        ];

        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            switch ($name) {
                case 'h1':
                    $styles['fontSize']   = round($styles['fontSize'] * 2.67); // 32
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h2':
                    $styles['fontSize']   = round($styles['fontSize'] * 2.33);  // 28
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h3':
                    $styles['fontSize']   = $styles['fontSize'] * 2;  // 24
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h4':
                    $styles['fontSize']   = round($styles['fontSize'] * 1.67); // 20
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h5':
                    $styles['fontSize']   = round($styles['fontSize'] * 1.33);  // 16
                    $styles['fontWeight'] = 'bold';
                    break;
                case 'h6':
                    $styles['fontWeight'] = 'bold';
                    break;
            }
        }

        if ($this->css->hasSelector($name)) {
            $styles = $this->applySelectorProperties($styles, $this->css[$name]);
        }

        if (isset($attribs['id']) && $this->css->hasSelector('#' . $attribs['id'])) {
            $styles = $this->applySelectorProperties($styles, $this->css['#' . $attribs['id']]);
        }

        if (isset($attribs['class']) && $this->css->hasSelector('.' . $attribs['class'])) {
            $styles = $this->applySelectorProperties($styles, $this->css['.' . $attribs['class']]);
        }

        // Inline style="..." takes precedence over tag/#id/.class selectors,
        // matching normal CSS cascade/specificity - applied last so it wins.
        if (!empty($attribs['style'])) {
            $inlineSelector = Css\Css::parseString('.__inline__{' . $attribs['style'] . '}')->getSelector('.__inline__');
            if ($inlineSelector !== null) {
                $styles = $this->applySelectorProperties($styles, $inlineSelector);
            }
        }

        if (str_contains($styles['fontFamily'], ',')) {
            $fonts = explode(',', $styles['fontFamily']);
            foreach ($fonts as $font) {
                $font = trim($font);
                if ($this->document->hasFont($font)) {
                    $styles['currentFont'] = $font;
                    break;
                } else if ($this->document->hasFont(str_replace(' ', '-', $font))) {
                    $styles['currentFont'] = str_replace(' ', '-', $font);
                } else if ($this->document->hasFont(str_replace(' ', ',', $font))) {
                    $styles['currentFont'] = str_replace(' ', ',', $font);
                } else if ($this->document->hasFont(str_replace(' ', '', $font))) {
                    $styles['currentFont'] = str_replace(' ', '', $font);
                }
            }
        } else {
            $styles['currentFont'] = $styles['fontFamily'];
        }

        if ($styles['currentFont'] === null) {
            throw new Exception('Error: No available font has been detected.');
        } else if ($styles['currentFont'] == 'sans-serif') {
            $styles['currentFont'] = 'Arial';
        } else if ($styles['currentFont'] == 'serif') {
            $styles['currentFont'] = 'TimesNewRoman';
        }

        if ($styles['fontWeight'] == 'bold') {
            if ($this->document->hasFont($styles['currentFont'] . 'Bold')) {
                $styles['currentFont'] .= 'Bold';
            } else if ($this->document->hasFont($styles['currentFont'] . '-Bold')) {
                $styles['currentFont'] .= '-Bold';
            } else if ($this->document->hasFont($styles['currentFont'] . ',Bold')) {
                $styles['currentFont'] .= ',Bold';
            }
        }

        if (!($this->document->hasFont($styles['currentFont']))) {
            $standardFonts = Document\Font::standardFonts();
            if (in_array($styles['currentFont'], $standardFonts)) {
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', '-', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', '-', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', ',', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', ',', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else if (in_array(str_replace(' ', '', $styles['currentFont']), $standardFonts)) {
                $styles['currentFont'] = str_replace(' ', '', $styles['currentFont']);
                $this->document->addFont(new Document\Font($styles['currentFont']));
            } else {
                throw new Exception('Error: The current font has not been added to the document.');
            }

            if ($styles['fontWeight'] == 'bold') {
                if ($this->document->hasFont($styles['currentFont'] . 'Bold')) {
                    $styles['currentFont'] .= 'Bold';
                } else if ($this->document->hasFont($styles['currentFont'] . '-Bold')) {
                    $styles['currentFont'] .= '-Bold';
                } else if ($this->document->hasFont($styles['currentFont'] . ',Bold')) {
                    $styles['currentFont'] .= ',Bold';
                } else if (in_array($styles['currentFont'] . 'Bold', $standardFonts)) {
                    $styles['currentFont'] .= 'Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                } else if (in_array($styles['currentFont'] . '-Bold', $standardFonts)) {
                    $styles['currentFont'] .= '-Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                } else if (in_array($styles['currentFont'] . ',Bold', $standardFonts)) {
                    $styles['currentFont'] .= ',Bold';
                    $this->document->addFont(new Document\Font($styles['currentFont']));
                }
            }
        }

        return $styles;
    }

    /**
     * Apply a CSS selector's properties onto a styles array
     *
     * Shared by the tag/#id/.class selector matches and by an inline
     * style="..." attribute (parsed into a one-off Selector) in
     * prepareNodeStyles(), so all four sources read the same property set.
     *
     * @param  array         $styles
     * @param  Css\Selector  $selector
     * @return array
     */
    protected function applySelectorProperties(array $styles, Css\Selector $selector): array
    {
        if ($selector->hasProperty('font-family')) {
            $styles['fontFamily'] = str_replace('"', '', $selector['font-family']);
        }
        if ($selector->hasProperty('font-size')) {
            $styles['fontSize'] = (int)$selector['font-size'];
        }
        if ($selector->hasProperty('font-weight')) {
            $styles['fontWeight'] = $selector['font-weight'];
        }
        if ($selector->hasProperty('color')) {
            $styles['color'] = $selector['color'];
            if (is_string($styles['color'])) {
                $styles['color'] = $this->parseCssColorToRgbArray($styles['color']);
            }
        }
        if ($selector->hasProperty('float')) {
            $styles['float'] = $selector['float'];
        }
        if ($selector->hasProperty('width')) {
            $styles['width'] = $selector['width'];
        }
        if ($selector->hasProperty('height')) {
            $styles['height'] = $selector['height'];
        }
        if ($selector->hasProperty('line-height')) {
            $styles['lineHeight'] = (int)$selector['line-height'];
        }
        if ((int)$selector['margin-top'] > 0) {
            $styles['marginTop'] = (int)$selector['margin-top'];
        }
        if ((int)$selector['padding-top'] > 0) {
            $styles['paddingTop'] = (int)$selector['padding-top'];
        }
        if ((int)$selector['margin-right'] > 0) {
            $styles['marginRight'] = (int)$selector['margin-right'];
        }
        if ((int)$selector['padding-right'] > 0) {
            $styles['paddingRight'] = (int)$selector['padding-right'];
        }
        if ((int)$selector['margin-bottom'] > 0) {
            $styles['marginBottom'] = (int)$selector['margin-bottom'];
        }
        if ((int)$selector['padding-bottom'] > 0) {
            $styles['paddingBottom'] = (int)$selector['padding-bottom'];
        }
        if ((int)$selector['margin-left'] > 0) {
            $styles['marginLeft'] = (int)$selector['margin-left'];
        }
        if ((int)$selector['padding-left'] > 0) {
            $styles['paddingLeft'] = (int)$selector['padding-left'];
        }
        if ($selector->hasProperty('text-align')) {
            $styles['textAlign'] = $selector['text-align'];
        }
        if ($selector->hasProperty('border-width')) {
            $styles['borderWidth'] = (int)$selector['border-width'];
        }
        if ($selector->hasProperty('border-color')) {
            $borderColor = $selector['border-color'];
            if (is_string($borderColor)) {
                $borderColor = $this->parseCssColorToRgbArray($borderColor);
            }
            $styles['borderColor'] = $borderColor;
        }
        if ($selector->hasProperty('background-color')) {
            $bgColor = $selector['background-color'];
            if (is_string($bgColor)) {
                $bgColor = $this->parseCssColorToRgbArray($bgColor);
            }
            $styles['backgroundColor'] = $bgColor;
        }

        return $styles;
    }

    /**
     * Get current X-position
     *
     * @return int
     */
    protected function getCurrentX(): int
    {
        if ($this->x < $this->pageMargins['left']) {
            $this->x = $this->pageMargins['left'];
        }
        return $this->x;
    }

    /**
     * Get current Y-position
     *
     * @param  int $marginBottom
     * @return int
     */
    public function getCurrentY($marginBottom = 0): int
    {
        if (!($this->document->hasPages())) {
            $this->page = (is_array($this->pageSize)) ?
                new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
        } else {
            $this->page = $this->document->getPage($this->document->getCurrentPage());
        }

        $currentY = $this->page->getHeight() - $this->pageMargins['top'] - $this->y - $marginBottom;

        if ($currentY <= $this->pageMargins['bottom']) {
            $this->page = (is_array($this->pageSize)) ?
                new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
            $this->document->addPage($this->page);
            $currentY = $this->resetY();
        }

        return $currentY;
    }

    /**
     * Reset Y-position
     *
     * @return int
     */
    protected function resetY(): int
    {
        // resetY()'s only call site is inside getCurrentY(), reached after
        // getCurrentY() has already created or fetched a page - the document
        // always has pages by the time resetY() runs.
        $this->page = $this->document->getPage($this->document->getCurrentPage());

        $this->y  = 0;

        return $this->page->getHeight() - $this->pageMargins['top'];
    }

    /**
     * Create new page
     *
     * @return int
     */
    public function newPage(): int
    {
        $this->page = (is_array($this->pageSize)) ?
            new Document\Page($this->pageSize['width'], $this->pageSize['height']) : new Document\Page($this->pageSize);
        $this->document->addPage($this->page);
        $this->y = 0;
        return $this->page->getHeight() - $this->pageMargins['top'] - $this->y;
    }

    /**
     * Get string lines
     *
     * @param  string        $string
     * @param  int           $fontSize
     * @param  int           $wrapLength
     * @param  Document\Font $fontObject
     * @return array
     */
    public function getStringLines(string $string, int $fontSize, int $wrapLength, Document\Font $fontObject): array
    {
        $strings   = [];
        $curString = '';
        $words     = explode(' ', $string);

        foreach ($words as $word) {
            $newString = ($curString != '') ? $curString . ' ' . $word : $word;
            if ($fontObject->getStringWidth($newString, $fontSize) <= $wrapLength) {
                $curString = $newString;
            } else {
                $strings[] = $curString;
                $curString = $word;
            }
        }
        if (!empty($curString)) {
            $strings[] = $curString;
        }

        return $strings;
    }

}
